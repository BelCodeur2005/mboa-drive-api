<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Initialiser un paiement
     */
    public function initialize(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'payment_method' => 'required|in:mobile_money,cash,bank_card',
                'provider' => 'required_if:payment_method,mobile_money|in:mtn,orange',
            ]);

            $user = $request->user();
            $booking = Booking::with(['trip.driver'])->findOrFail($request->booking_id);

            // Vérifier que l'utilisateur est le passager de cette réservation
            if ($booking->passenger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à payer cette réservation'
                ], 403);
            }

            // Vérifier que la réservation est confirmée
            if ($booking->status !== 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette réservation n\'est pas confirmée'
                ], 422);
            }

            // Vérifier qu'un paiement n'est pas déjà en cours
            $existingPayment = Payment::where('booking_id', $booking->id)
                                     ->whereIn('status', ['pending', 'processing', 'completed'])
                                     ->first();

            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un paiement existe déjà pour cette réservation',
                    'data' => new PaymentResource($existingPayment)
                ], 422);
            }

            // Créer le paiement
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payer_id' => $user->id,
                'receiver_id' => $booking->trip->driver_id,
                'amount' => $booking->total_amount,
                'payment_method' => $request->payment_method,
                'provider' => $request->provider,
                'status' => 'pending',
                'commission_rate' => config('app.commission_rate', 10)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement initialisé avec succès',
                'data' => new PaymentResource($payment->load(['payer', 'receiver', 'booking']))
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paiement Mobile Money
     */
    public function mobileMoney(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'phone' => 'required|string|regex:/^[0-9+\-\s]+$/',
                'provider' => 'required|in:mtn,orange',
                'amount' => 'required|numeric|min:100'
            ]);

            $user = $request->user();
            $booking = Booking::findOrFail($request->booking_id);

            if ($booking->passenger_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à payer cette réservation'
                ], 403);
            }

            // Vérifier le montant
            if ($request->amount != $booking->total_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant ne correspond pas au prix de la réservation'
                ], 422);
            }

            // Trouver ou créer le paiement
            $payment = Payment::where('booking_id', $booking->id)->first();
            if (!$payment) {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'payer_id' => $user->id,
                    'receiver_id' => $booking->trip->driver_id,
                    'amount' => $request->amount,
                    'payment_method' => 'mobile_money',
                    'provider' => $request->provider,
                    'phone_number' => $request->phone,
                    'status' => 'processing',
                    'commission_rate' => config('app.commission_rate', 10)
                ]);
            } else {
                $payment->update([
                    'phone_number' => $request->phone,
                    'status' => 'processing',
                    'provider' => $request->provider
                ]);
            }

            // TODO: Intégrer l'API Mobile Money réelle
            // Pour l'instant, on simule le processus

            if ($request->provider === 'mtn') {
                $response = $this->processMTNMobileMoney($payment, $request->phone, $request->amount);
            } else {
                $response = $this->processOrangeMoney($payment, $request->phone, $request->amount);
            }

            if ($response['success']) {
                $payment->update([
                    'status' => 'completed',
                    'external_reference' => $response['reference'],
                    'provider_response' => $response,
                    'confirmed_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement Mobile Money effectué avec succès',
                    'data' => new PaymentResource($payment->fresh(['payer', 'receiver', 'booking']))
                ]);
            } else {
                $payment->markAsFailed($response['message']);

                return response()->json([
                    'success' => false,
                    'message' => 'Échec du paiement Mobile Money: ' . $response['message']
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement Mobile Money',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirmer un paiement cash
     */
    public function confirmCashPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'booking_id' => 'required|exists:bookings,id',
                'amount' => 'required|numeric|min:100',
                'confirmed_by' => 'required|in:driver,passenger'
            ]);

            $user = $request->user();
            $booking = Booking::with(['trip.driver'])->findOrFail($request->booking_id);

            // Vérifier que l'utilisateur est impliqué dans cette réservation
            if ($booking->passenger_id !== $user->id && $booking->trip->driver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à confirmer ce paiement'
                ], 403);
            }

            // Trouver ou créer le paiement
            $payment = Payment::where('booking_id', $booking->id)->first();
            if (!$payment) {
                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'payer_id' => $booking->passenger_id,
                    'receiver_id' => $booking->trip->driver_id,
                    'amount' => $request->amount,
                    'payment_method' => 'cash',
                    'status' => 'pending',
                    'commission_rate' => config('app.commission_rate', 10)
                ]);
            }

            // Logique de confirmation mutuelle pour les paiements cash
            $confirmationData = $payment->provider_response ?? [];
            $confirmationData[$request->confirmed_by . '_confirmed'] = true;
            $confirmationData[$request->confirmed_by . '_confirmed_at'] = now()->toISOString();
            $confirmationData[$request->confirmed_by . '_confirmed_by'] = $user->id;

            $payment->update([
                'provider_response' => $confirmationData
            ]);

            // Si les deux parties ont confirmé, marquer comme complété
            if (isset($confirmationData['driver_confirmed']) && isset($confirmationData['passenger_confirmed'])) {
                $payment->markAsCompleted();
                $message = 'Paiement cash confirmé par les deux parties';
            } else {
                $message = 'Votre confirmation a été enregistrée. En attente de la confirmation de l\'autre partie.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => new PaymentResource($payment->fresh(['payer', 'receiver', 'booking']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation du paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historique des paiements
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = Payment::with(['payer', 'receiver', 'booking.trip'])
                           ->where(function ($q) use ($user) {
                               $q->where('payer_id', $user->id)
                                 ->orWhere('receiver_id', $user->id);
                           });

            // Filtrer par statut
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filtrer par méthode de paiement
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Filtrer par période
            if ($request->filled('period')) {
                $period = $request->period;
                match($period) {
                    'last_month' => $query->where('created_at', '>=', now()->subMonth()),
                    'last_3_months' => $query->where('created_at', '>=', now()->subMonths(3)),
                    'last_year' => $query->where('created_at', '>=', now()->subYear()),
                    default => null
                };
            }

            $payments = $query->orderBy('created_at', 'desc')->paginate(15);

            // Calculer les statistiques
            $stats = [
                'total_paid' => Payment::where('payer_id', $user->id)->where('status', 'completed')->sum('amount'),
                'total_received' => Payment::where('receiver_id', $user->id)->where('status', 'completed')->sum('net_amount'),
                'pending_payments' => Payment::where('payer_id', $user->id)->where('status', 'pending')->count(),
                'completed_payments' => Payment::where('payer_id', $user->id)->where('status', 'completed')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => PaymentResource::collection($payments),
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statut d'un paiement
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est impliqué dans ce paiement
            if ($payment->payer_id !== $user->id && $payment->receiver_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à voir ce paiement'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => new PaymentResource($payment->load(['payer', 'receiver', 'booking.trip']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simuler le processus MTN Mobile Money
     * TODO: Remplacer par l'intégration réelle de l'API MTN
     */
    private function processMTNMobileMoney(Payment $payment, string $phone, float $amount): array
    {
        // Simulation pour les tests
        // En production, utiliser l'API MTN Mobile Money

        $success = rand(1, 10) > 2; // 80% de chance de succès pour les tests

        if ($success) {
            return [
                'success' => true,
                'reference' => 'MTN' . time() . rand(1000, 9999),
                'message' => 'Paiement Mobile Money MTN réussi',
                'provider' => 'mtn',
                'phone' => $phone,
                'amount' => $amount
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Fonds insuffisants ou numéro incorrect',
                'provider' => 'mtn'
            ];
        }
    }

    /**
     * Simuler le processus Orange Money
     * TODO: Remplacer par l'intégration réelle de l'API Orange
     */
    private function processOrangeMoney(Payment $payment, string $phone, float $amount): array
    {
        // Simulation pour les tests
        // En production, utiliser l'API Orange Money

        $success = rand(1, 10) > 2; // 80% de chance de succès pour les tests

        if ($success) {
            return [
                'success' => true,
                'reference' => 'OM' . time() . rand(1000, 9999),
                'message' => 'Paiement Orange Money réussi',
                'provider' => 'orange',
                'phone' => $phone,
                'amount' => $amount
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Transaction échouée - Solde insuffisant',
                'provider' => 'orange'
            ];
        }
    }
}
