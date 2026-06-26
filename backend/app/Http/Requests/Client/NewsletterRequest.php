<?php
namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class NewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'nom' => 'nullable|string|max:100',
            'prenom' => 'nullable|string|max:100',
            'accepte_conditions' => 'accepted'
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 100 caractères.',
            'accepte_conditions.accepted' => 'Vous devez accepter les conditions d\'utilisation.'
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }

        if ($this->has('nom')) {
            $this->merge(['nom' => ucfirst(strtolower(trim($this->nom)))]);
        }

        if ($this->has('prenom')) {
            $this->merge(['prenom' => ucfirst(strtolower(trim($this->prenom)))]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422)
        );
    }
 }