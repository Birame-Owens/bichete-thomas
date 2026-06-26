<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AvisClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => [
                'required',
                'string',
                'in:en_attente,approuve,rejete,masque'
            ],
            'raison_rejet' => [
                'nullable',
                'string',
                'max:500',
                'required_if:statut,rejete'
            ],
            'reponse_boutique' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'est_visible' => [
                'nullable',
                'boolean'
            ],
            'est_mis_en_avant' => [
                'nullable',
                'boolean'
            ],
            'ordre_affichage' => [
                'nullable',
                'integer',
                'min:0',
                'max:999'
            ],
            'avis_verifie' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'statut.required' => 'Le statut est obligatoire.',
            'statut.in' => 'Le statut doit être : en attente, approuvé, rejeté ou masqué.',
            
            'raison_rejet.required_if' => 'La raison du rejet est obligatoire quand le statut est "rejeté".',
            'raison_rejet.max' => 'La raison du rejet ne peut pas dépasser 500 caractères.',
            
            'reponse_boutique.max' => 'La réponse ne peut pas dépasser 1000 caractères.',
            
            'est_visible.boolean' => 'La visibilité doit être vraie ou fausse.',
            'est_mis_en_avant.boolean' => 'La mise en avant doit être vraie ou fausse.',
            'avis_verifie.boolean' => 'La vérification doit être vraie ou fausse.',
            
            'ordre_affichage.integer' => 'L\'ordre d\'affichage doit être un nombre entier.',
            'ordre_affichage.min' => 'L\'ordre d\'affichage ne peut pas être négatif.',
            'ordre_affichage.max' => 'L\'ordre d\'affichage ne peut pas dépasser 999.'
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'est_visible' => $this->boolean('est_visible', false),
            'est_mis_en_avant' => $this->boolean('est_mis_en_avant', false),
            'avis_verifie' => $this->boolean('avis_verifie', false),
        ]);

        // Si le statut est approuvé, rendre visible par défaut
        if ($this->input('statut') === 'approuve') {
            $this->merge(['est_visible' => true]);
        }

        // Nettoyer la raison de rejet si le statut n'est pas rejeté
        if ($this->input('statut') !== 'rejete') {
            $this->merge(['raison_rejet' => null]);
        }
    }

    /**
     * Personnaliser la réponse en cas d'échec de validation
     */
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $validator->errors()
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Validation personnalisée
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Validation pour la réponse boutique
            if ($this->input('reponse_boutique') && strlen(trim($this->input('reponse_boutique'))) < 10) {
                $validator->errors()->add('reponse_boutique', 
                    'La réponse doit contenir au moins 10 caractères.');
            }

            // Validation pour l'ordre d'affichage
            if ($this->input('est_mis_en_avant') && !$this->input('ordre_affichage')) {
                $this->merge(['ordre_affichage' => 1]);
            }
        });
    }
}