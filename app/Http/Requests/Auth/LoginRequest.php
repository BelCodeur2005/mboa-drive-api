<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'login' => 'required|string', // Peut être email ou téléphone
            'password' => 'required|string',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'login.required' => 'L\'email ou le numéro de téléphone est obligatoire',
            'login.string' => 'L\'email ou le numéro de téléphone doit être une chaîne de caractères',
            'password.required' => 'Le mot de passe est obligatoire',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'login' => 'identifiant',
            'password' => 'mot de passe',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer l'identifiant de connexion
        if ($this->has('login')) {
            $login = trim($this->login);

            // Si c'est un email, le mettre en minuscules
            if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
                $login = strtolower($login);
            } else {
                // Si c'est un téléphone, supprimer les espaces
                $login = preg_replace('/\s+/', '', $login);
            }

            $this->merge([
                'login' => $login
            ]);
        }
    }
}
