<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class CreateTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => 'required|exists:vehicles,id',
            'departure_city' => 'required|string|max:100',
            'departure_address' => 'required|string|max:255',
            'departure_latitude' => 'nullable|numeric|between:-90,90',
            'departure_longitude' => 'nullable|numeric|between:-180,180',
            'arrival_city' => 'required|string|max:100',
            'arrival_address' => 'required|string|max:255',
            'arrival_latitude' => 'nullable|numeric|between:-90,90',
            'arrival_longitude' => 'nullable|numeric|between:-180,180',
            'departure_date' => 'required|date|after:now',
            'arrival_date' => 'nullable|date|after:departure_date',
            'available_seats' => 'required|integer|min:1|max:8',
            'price_per_seat' => 'required|numeric|min:100|max:50000',
            'total_distance' => 'nullable|numeric|min:1|max:2000',
            'estimated_duration' => 'nullable|integer|min:10|max:1440',
            'instant_booking' => 'boolean',
            'smoking_allowed' => 'boolean',
            'pets_allowed' => 'boolean',
            'max_2_in_back' => 'integer|min:1|max:3',
            'additional_info' => 'nullable|string|max:500',
            'stops' => 'nullable|array|max:5',
            'stops.*.city' => 'required_with:stops|string|max:100',
            'stops.*.address' => 'required_with:stops|string|max:255',
            'stops.*.latitude' => 'nullable|numeric|between:-90,90',
            'stops.*.longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Le véhicule est obligatoire',
            'vehicle_id.exists' => 'Le véhicule sélectionné n\'existe pas',

            'departure_city.required' => 'La ville de départ est obligatoire',
            'departure_address.required' => 'L\'adresse de départ est obligatoire',
            'arrival_city.required' => 'La ville d\'arrivée est obligatoire',
            'arrival_address.required' => 'L\'adresse d\'arrivée est obligatoire',

            'departure_date.required' => 'La date de départ est obligatoire',
            'departure_date.after' => 'La date de départ doit être dans le futur',
            'arrival_date.after' => 'La date d\'arrivée doit être après la date de départ',

            'available_seats.required' => 'Le nombre de places disponibles est obligatoire',
            'available_seats.min' => 'Il faut au moins 1 place disponible',
            'available_seats.max' => 'Maximum 8 places disponibles',

            'price_per_seat.required' => 'Le prix par place est obligatoire',
            'price_per_seat.min' => 'Le prix minimum est de 100 FCFA',
            'price_per_seat.max' => 'Le prix maximum est de 50 000 FCFA',

            'total_distance.min' => 'La distance doit être d\'au moins 1 km',
            'total_distance.max' => 'La distance maximum est de 2000 km',

            'estimated_duration.min' => 'La durée minimum est de 10 minutes',
            'estimated_duration.max' => 'La durée maximum est de 24 heures (1440 minutes)',

            'additional_info.max' => 'Les informations additionnelles ne peuvent pas dépasser 500 caractères',

            'stops.max' => 'Maximum 5 points d\'arrêt autorisés',
            'stops.*.city.required_with' => 'La ville est obligatoire pour chaque point d\'arrêt',
            'stops.*.address.required_with' => 'L\'adresse est obligatoire pour chaque point d\'arrêt',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'véhicule',
            'departure_city' => 'ville de départ',
            'departure_address' => 'adresse de départ',
            'arrival_city' => 'ville d\'arrivée',
            'arrival_address' => 'adresse d\'arrivée',
            'departure_date' => 'date de départ',
            'arrival_date' => 'date d\'arrivée',
            'available_seats' => 'places disponibles',
            'price_per_seat' => 'prix par place',
            'total_distance' => 'distance totale',
            'estimated_duration' => 'durée estimée',
            'additional_info' => 'informations additionnelles',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer et normaliser les données
        if ($this->has('departure_city')) {
            $this->merge([
                'departure_city' => trim($this->departure_city)
            ]);
        }

        if ($this->has('arrival_city')) {
            $this->merge([
                'arrival_city' => trim($this->arrival_city)
            ]);
        }

        if ($this->has('departure_address')) {
            $this->merge([
                'departure_address' => trim($this->departure_address)
            ]);
        }

        if ($this->has('arrival_address')) {
            $this->merge([
                'arrival_address' => trim($this->arrival_address)
            ]);
        }

        // Convertir les booléens
        $this->merge([
            'instant_booking' => $this->boolean('instant_booking', true),
            'smoking_allowed' => $this->boolean('smoking_allowed', false),
            'pets_allowed' => $this->boolean('pets_allowed', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Vérifier que la ville de départ et d'arrivée sont différentes
            if ($this->departure_city && $this->arrival_city) {
                if (strtolower($this->departure_city) === strtolower($this->arrival_city)) {
                    $validator->errors()->add('arrival_city', 'La ville d\'arrivée doit être différente de la ville de départ');
                }
            }

            // Vérifier que la date de départ n'est pas trop dans le futur (max 3 mois)
            if ($this->departure_date) {
                $departureDate = Carbon::parse($this->departure_date);
                if ($departureDate->gt(now()->addMonths(3))) {
                    $validator->errors()->add('departure_date', 'La date de départ ne peut pas être plus de 3 mois dans le futur');
                }
            }
        });
    }
}
