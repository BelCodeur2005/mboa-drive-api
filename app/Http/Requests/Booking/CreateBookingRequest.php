<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
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
            'trip_id' => 'required|exists:trips,id',
            'seats_booked' => 'required|integer|min:1|max:8',
            'passenger_message' => 'nullable|string|max:500',
            
            // Points de prise en charge personnalisés (optionnels)
            'pickup_address' => 'nullable|string|max:255',
            'pickup_latitude' => 'nullable|numeric|between:-90,90',
            'pickup_longitude' => 'nullable|numeric|between:-180,180',
            
            'dropoff_address' => 'nullable|string|max:255',
            'dropoff_latitude' => 'nullable|numeric|between:-90,90',
            'dropoff_longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'trip_id.required' => 'Le trajet est obligatoire',
            'trip_id.exists' => 'Le trajet sélectionné n\'existe pas',
            
            'seats_booked.required' => 'Le nombre de places est obligatoire',
            'seats_booked.min' => 'Vous devez réserver au moins 1 place',
            'seats_booked.max' => 'Vous ne pouvez pas réserver plus de 8 places',
            
            'passenger_message.max' => 'Le message ne peut pas dépasser 500 caractères',
            
            'pickup_address.max' => 'L\'adresse de prise en charge ne peut pas dépasser 255 caractères',
            'pickup_latitude.between' => 'La latitude doit être comprise entre -90 et 90',
            'pickup_longitude.between' => 'La longitude doit être comprise entre -180 et 180',
            
            'dropoff_address.max' => 'L\'adresse de dépôt ne peut pas dépasser 255 caractères',
            'dropoff_latitude.between' => 'La latitude doit être comprise entre -90 et 90',
            'dropoff_longitude.between' => 'La longitude doit être comprise entre -180 et 180',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'trip_id' => 'trajet',
            'seats_booked' => 'nombre de places',
            'passenger_message' => 'message au conducteur',
            'pickup_address' => 'adresse de prise en charge',
            'dropoff_address' => 'adresse de dépôt',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer le message
        if ($this->has('passenger_message')) {
            $this->merge([
                'passenger_message' => trim($this->passenger_message)
            ]);
        }

        // Nettoyer les adresses
        if ($this->has('pickup_address')) {
            $this->merge([
                'pickup_address' => trim($this->pickup_address)
            ]);
        }

        if ($this->has('dropoff_address')) {
            $this->merge([
                'dropoff_address' => trim($this->dropoff_address)
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Si des coordonnées GPS sont fournies, l'adresse correspondante doit l'être aussi
            if (($this->pickup_latitude || $this->pickup_longitude) && !$this->pickup_address) {
                $validator->errors()->add('pickup_address', 'L\'adresse de prise en charge est requise avec les coordonnées GPS');
            }

            if (($this->dropoff_latitude || $this->dropoff_longitude) && !$this->dropoff_address) {
                $validator->errors()->add('dropoff_address', 'L\'adresse de dépôt est requise avec les coordonnées GPS');
            }

            // Si latitude fournie, longitude requise et vice versa
            if ($this->pickup_latitude && !$this->pickup_longitude) {
                $validator->errors()->add('pickup_longitude', 'La longitude est requise avec la latitude');
            }

            if ($this->pickup_longitude && !$this->pickup_latitude) {
                $validator->errors()->add('pickup_latitude', 'La latitude est requise avec la longitude');
            }

            if ($this->dropoff_latitude && !$this->dropoff_longitude) {
                $validator->errors()->add('dropoff_longitude', 'La longitude est requise avec la latitude');
            }

            if ($this->dropoff_longitude && !$this->dropoff_latitude) {
                $validator->errors()->add('dropoff_latitude', 'La latitude est requise avec la longitude');
            }
        });
    }
}