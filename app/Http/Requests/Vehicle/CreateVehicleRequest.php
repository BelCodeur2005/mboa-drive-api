<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class CreateVehicleRequest extends FormRequest
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
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'color' => 'required|string|max:30',
            'license_plate' => 'required|string|max:20|unique:vehicles,license_plate',
            'year' => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'seats' => 'required|integer|min:2|max:9',
            'fuel_type' => 'required|in:petrol,diesel,hybrid,electric',
            'air_conditioning' => 'boolean',
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'brand.required' => 'La marque du véhicule est obligatoire',
            'brand.max' => 'La marque ne peut pas dépasser 50 caractères',
            
            'model.required' => 'Le modèle du véhicule est obligatoire',
            'model.max' => 'Le modèle ne peut pas dépasser 50 caractères',
            
            'color.required' => 'La couleur du véhicule est obligatoire',
            'color.max' => 'La couleur ne peut pas dépasser 30 caractères',
            
            'license_plate.required' => 'La plaque d\'immatriculation est obligatoire',
            'license_plate.unique' => 'Cette plaque d\'immatriculation est déjà enregistrée',
            'license_plate.max' => 'La plaque d\'immatriculation ne peut pas dépasser 20 caractères',
            
            'year.required' => 'L\'année du véhicule est obligatoire',
            'year.min' => 'L\'année doit être supérieure à 1990',
            'year.max' => 'L\'année ne peut pas être supérieure à ' . (date('Y') + 1),
            
            'seats.required' => 'Le nombre de places est obligatoire',
            'seats.min' => 'Le véhicule doit avoir au moins 2 places',
            'seats.max' => 'Le véhicule ne peut pas avoir plus de 9 places',
            
            'fuel_type.required' => 'Le type de carburant est obligatoire',
            'fuel_type.in' => 'Le type de carburant doit être : essence, diesel, hybride ou électrique',
            
            'description.max' => 'La description ne peut pas dépasser 500 caractères',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'brand' => 'marque',
            'model' => 'modèle',
            'color' => 'couleur',
            'license_plate' => 'plaque d\'immatriculation',
            'year' => 'année',
            'seats' => 'nombre de places',
            'fuel_type' => 'type de carburant',
            'air_conditioning' => 'climatisation',
            'description' => 'description',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer et normaliser les données
        if ($this->has('brand')) {
            $this->merge([
                'brand' => ucfirst(strtolower(trim($this->brand)))
            ]);
        }

        if ($this->has('model')) {
            $this->merge([
                'model' => ucfirst(strtolower(trim($this->model)))
            ]);
        }

        if ($this->has('color')) {
            $this->merge([
                'color' => ucfirst(strtolower(trim($this->color)))
            ]);
        }

        if ($this->has('license_plate')) {
            $this->merge([
                'license_plate' => strtoupper(trim($this->license_plate))
            ]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => trim($this->description)
            ]);
        }

        // Convertir les booléens
        $this->merge([
            'air_conditioning' => $this->boolean('air_conditioning', false),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validation de la plaque d'immatriculation camerounaise
            if ($this->license_plate) {
                $pattern = '/^[A-Z]{2}-\d{3,4}-[A-Z]{2}$/';
                if (!preg_match($pattern, $this->license_plate)) {
                    $validator->errors()->add('license_plate', 'Format de plaque invalide. Utilisez le format : LT-123-CM');
                }
            }

            // Vérifier que l'année n'est pas trop ancienne pour certains types de carburant
            if ($this->fuel_type === 'hybrid' && $this->year < 2000) {
                $validator->errors()->add('year', 'Les véhicules hybrides n\'existaient pas avant 2000');
            }

            if ($this->fuel_type === 'electric' && $this->year < 2010) {
                $validator->errors()->add('year', 'Les véhicules électriques grand public n\'existaient pas avant 2010');
            }

            // Validation logique des marques et modèles populaires au Cameroun
            $popularBrands = [
                'Toyota', 'Honda', 'Hyundai', 'Kia', 'Nissan', 'Mazda', 
                'Ford', 'Volkswagen', 'Peugeot', 'Renault', 'Mercedes', 'BMW'
            ];
            
            if ($this->brand && !in_array($this->brand, $popularBrands)) {
                // Avertissement seulement, pas d'erreur
                // $validator->errors()->add('brand', 'Marque non reconnue');
            }
        });
    }
}