<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class UpdateTripRequest extends FormRequest
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
            'departure_address' => 'sometimes|string|max:255',
            'departure_latitude' => 'sometimes|numeric|between:-90,90',
            'departure_longitude' => 'sometimes|numeric|between:-180,180',
            'arrival_address' => 'sometimes|string|max:255',
            'arrival_latitude' => 'sometimes|numeric|between:-90,90',
            'arrival_longitude' => 'sometimes|numeric|between:-180,180',
            'departure_date' => 'sometimes|date|after:now',
            'arrival_date' => 'sometimes|date|after:departure_date',
            'available_seats' => 'sometimes|integer|min:1|max:8',
            'price_per_seat' => 'sometimes|numeric|min:100|max:50000',
            'total_distance' => 'sometimes|numeric|min:1|max:2000',
            'estimated_duration' => 'sometimes|integer|min:10|max:1440',
            'instant_booking' => 'sometimes|boolean',
            'smoking_allowed' => 'sometimes|boolean',
            'pets_allowed' => 'sometimes|boolean',
            'max_2_in_back' => 'sometimes|integer|min:1|max:3',
            'additional_info' => 'sometimes|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'departure_address.max' => 'L\'adresse de départ ne peut pas dépasser 255 caractères',
            'arrival_address.max' => 'L\'adresse d\'arrivée ne peut pas dépasser 255 caractères',

            'departure_date.after' => 'La date de départ doit être dans le futur',
            'arrival_date.after' => 'La date d\'arrivée doit être après la date de départ',

            'available_seats.min' => 'Il faut au moins 1 place disponible',
            'available_seats.max' => 'Maximum 8 places disponibles',

            'price_per_seat.min' => 'Le prix minimum est de 100 FCFA',
            'price_per_seat.max' => 'Le prix maximum est de 50 000 FCFA',

            'total_distance.min' => 'La distance doit être d\'au moins 1 km',
            'total_distance.max' => 'La distance maximum est de 2000 km',

            'estimated_duration.min' => 'La durée minimum est de 10 minutes',
            'estimated_duration.max' => 'La durée maximum est de 24 heures (1440 minutes)',

            'additional_info.max' => 'Les informations additionnelles ne peuvent pas dépasser 500 caractères',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer les adresses
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

        if ($this->has('additional_info')) {
            $this->merge([
                'additional_info' => trim($this->additional_info)
            ]);
        }

        // Convertir les booléens
        if ($this->has('instant_booking')) {
            $this->merge(['instant_booking' => $this->boolean('instant_booking')]);
        }
        if ($this->has('smoking_allowed')) {
            $this->merge(['smoking_allowed' => $this->boolean('smoking_allowed')]);
        }
        if ($this->has('pets_allowed')) {
            $this->merge(['pets_allowed' => $this->boolean('pets_allowed')]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
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
