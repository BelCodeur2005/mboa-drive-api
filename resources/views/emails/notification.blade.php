<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }} - {{ $appName }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #2E7D32, #4CAF50);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .notification-type {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        .type-high { background: #FFE0E0; color: #D32F2F; }
        .type-normal { background: #E3F2FD; color: #1976D2; }
        .message {
            font-size: 16px;
            color: #333;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .action-button {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #2E7D32, #4CAF50);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
        }
        .action-button:hover {
            background: linear-gradient(135deg, #1B5E20, #388E3C);
        }
        .data-section {
            background: #F5F5F5;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .data-item {
            margin-bottom: 10px;
        }
        .data-label {
            font-weight: bold;
            color: #666;
        }
        .footer {
            background: #F8F8F8;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        .footer a {
            color: #4CAF50;
            text-decoration: none;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                border-radius: 5px;
            }
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">🚗 {{ $appName }}</div>
            <h1>{{ $notification->title }}</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Type de notification -->
            <span class="notification-type {{ $notification->priority === 'high' ? 'type-high' : 'type-normal' }}">
                @switch($notification->type)
                    @case('booking_confirmed') ✅ Réservation @break
                    @case('booking_cancelled') ❌ Annulation @break
                    @case('trip_starting_soon') ⏰ Rappel trajet @break
                    @case('payment_received') 💰 Paiement @break
                    @case('new_message') 💬 Message @break
                    @case('new_rating') ⭐ Évaluation @break
                    @default 🔔 Notification @break
                @endswitch
            </span>

            <!-- Message principal -->
            <div class="message">
                Bonjour <strong>{{ $user->name }}</strong>,<br><br>
                {{ $notification->message }}
            </div>

            <!-- Données additionnelles -->
            @if($notification->data && count($notification->data) > 0)
            <div class="data-section">
                <h3 style="margin-top: 0; color: #333;">📋 Détails</h3>

                @if($notification->type === 'booking_confirmed')
                    @if(isset($notification->data['departure_time']))
                    <div class="data-item">
                        <span class="data-label">📅 Heure de départ :</span>
                        {{ \Carbon\Carbon::parse($notification->data['departure_time'])->format('d/m/Y à H:i') }}
                    </div>
                    @endif

                    @if(isset($notification->data['driver_name']))
                    <div class="data-item">
                        <span class="data-label">👤 Conducteur :</span>
                        {{ $notification->data['driver_name'] }}
                    </div>
                    @endif

                    @if(isset($notification->data['driver_phone']))
                    <div class="data-item">
                        <span class="data-label">📞 Téléphone :</span>
                        <a href="tel:{{ $notification->data['driver_phone'] }}">{{ $notification->data['driver_phone'] }}</a>
                    </div>
                    @endif

                    @if(isset($notification->data['pickup_address']))
                    <div class="data-item">
                        <span class="data-label">📍 Point de prise en charge :</span>
                        {{ $notification->data['pickup_address'] }}
                    </div>
                    @endif
                @endif

                @if($notification->type === 'payment_received')
                    @if(isset($notification->data['amount']))
                    <div class="data-item">
                        <span class="data-label">💵 Montant reçu :</span>
                        {{ number_format($notification->data['net_amount'] ?? $notification->data['amount'], 0, ',', ' ') }} FCFA
                    </div>
                    @endif

                    @if(isset($notification->data['payment_method']))
                    <div class="data-item">
                        <span class="data-label">💳 Méthode :</span>
                        {{ $notification->data['payment_method'] }}
                    </div>
                    @endif

                    @if(isset($notification->data['commission']))
                    <div class="data-item">
                        <span class="data-label">📊 Commission :</span>
                        {{ number_format($notification->data['commission'], 0, ',', ' ') }} FCFA
                    </div>
                    @endif
                @endif
            </div>
            @endif

            <!-- Bouton d'action -->
            @if($actionUrl)
            <div style="text-align: center;">
                <a href="{{ $actionUrl }}" class="action-button">
                    @switch($notification->type)
                        @case('booking_confirmed') Voir ma réservation @break
                        @case('trip_starting_soon') Voir mon trajet @break
                        @case('payment_received') Voir mes paiements @break
                        @case('new_message') Lire le message @break
                        @default Voir les détails @break
                    @endswitch
                </a>
            </div>
            @endif

            <!-- Conseils selon le type -->
            @switch($notification->type)
                @case('booking_confirmed')
                <div style="margin-top: 30px; padding: 15px; background: #E8F5E8; border-left: 4px solid #4CAF50; border-radius: 4px;">
                    <strong>💡 Conseils :</strong><br>
                    • Soyez à l'heure au point de rendez-vous<br>
                    • Ayez votre téléphone chargé<br>
                    • Contactez le conducteur si besoin
                </div>
                @break

                @case('trip_starting_soon')
                <div style="margin-top: 30px; padding: 15px; background: #FFF3E0; border-left: 4px solid #FF9800; border-radius: 4px;">
                    <strong>⚠️ N'oubliez pas :</strong><br>
                    • Vérifiez le niveau de carburant<br>
                    • Confirmez avec vos passagers<br>
                    • Activez votre GPS
                </div>
                @break
            @endswitch
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                Cette notification a été envoyée le {{ $notification->created_at->format('d/m/Y à H:i') }}<br>
                <a href="{{ config('app.url') }}/notifications/settings">Gérer mes notifications</a> |
                <a href="{{ config('app.url') }}/support">Support</a>
            </p>

            <p style="margin-top: 20px; color: #999; font-size: 12px;">
                © {{ date('Y') }} {{ $appName }} - Covoiturage au Cameroun<br>
                <a href="{{ config('app.url') }}/unsubscribe?token={{ base64_encode($user->email) }}">Se désabonner</a>
            </p>
        </div>
    </div>
</body>
</html>
