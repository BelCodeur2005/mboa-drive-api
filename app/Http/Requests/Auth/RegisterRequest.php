<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'user_type' => 'nullable|in:passenger,driver,both',
            'city' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'name.string' => 'Le nom doit être une chaîne de caractères',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',

            'email.required' => 'L\'adresse email est obligatoire',
            'email.email' => 'L\'adresse email doit être valide',
            'email.unique' => 'Cette adresse email est déjà utilisée',

            'phone.required' => 'Le numéro de téléphone est obligatoire',
            'phone.regex' => 'Le format du numéro de téléphone est invalide',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',

            'password.required' => 'Le mot de passe est obligatoire',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas',

            'user_type.in' => 'Le type d\'utilisateur doit être passenger, driver ou both',
            'date_of_birth.date' => 'La date de naissance doit être une date valide',
            'date_of_birth.before' => 'La date de naissance doit être antérieure à aujourd\'hui',
            'gender.in' => 'Le genre doit être male, female ou other',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'email',
            'phone' => 'téléphone',
            'password' => 'mot de passe',
            'user_type' => 'type d\'utilisateur',
            'city' => 'ville',
            'date_of_birth' => 'date de naissance',
            'gender' => 'genre',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer le numéro de téléphone
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\s+/', '', $this->phone)
            ]);
        }

        // Nettoyer l'email
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }

        // Nettoyer le nom
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name)
            ]);
        }
    }
}
