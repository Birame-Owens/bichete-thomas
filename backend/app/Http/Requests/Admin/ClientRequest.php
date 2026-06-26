<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client')?->id;

        return [
            'nom' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\']+$/'
            ],
            'prenom' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\']+$/'
            ],
            'telephone' => [
                'required',
                'string',
                'max:20',
                'unique:clients,telephone,' . $clientId,
                'regex:/^[0-9\+\-\s\(\)]+$/'
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:clients,email,' . $clientId
            ],
            'genre' => [
                'nullable',
                'string',
                'in:masculin,feminin,autre'
            ],
            'date_naissance' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01'
            ],
            'adresse_principale' => [
                'nullable',
                'string',
                'max:500'
            ],
            'quartier' => [
                'nullable',
                'string',
                'max:100'
            ],
            'ville' => [
                'required',
                'string',
                'max:100'
            ],
            'indications_livraison' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'taille_habituelle' => [
                'nullable',
                'string',
                'max:10'
            ],
            'couleurs_preferees' => [
                'nullable',
                'string',
                'max:200'
            ],
            'styles_preferes' => [
                'nullable',
                'string',
                'max:200'
            ],
            'budget_moyen' => [
                'nullable',
                'numeric',
                'min:0',
                'max:10000000'
            ],
            'accepte_whatsapp' => [
                'boolean'
            ],
            'accepte_email' => [
                'boolean'
            ],
            'accepte_sms' => [
                'boolean'
            ],
            'accepte_promotions' => [
                'boolean'
            ],
            'canaux_preferes' => [
                'nullable',
                'string',
                'max:100'
            ],
            'notes_privees' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'priorite' => [
                'required',
                'string',
                'in:normale,haute,vip'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            // Nom et prénom
            'nom.required' => 'Le nom est obligatoire.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
            
            'prenom.required' => 'Le prénom est obligatoire.',
            'prenom.string' => 'Le prénom doit être une chaîne de caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 100 caractères.',
            'prenom.regex' => 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
            
            // Téléphone
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.string' => 'Le téléphone doit être une chaîne de caractères.',
            'telephone.max' => 'Le téléphone ne peut pas dépasser 20 caractères.',
            'telephone.unique' => 'Ce numéro de téléphone est déjà utilisé par un autre client.',
            'telephone.regex' => 'Le format du numéro de téléphone n\'est pas valide.',
            
            // Email
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.max' => 'L\'email ne peut pas dépasser 255 caractères.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre client.',
            
            // Genre
            'genre.in' => 'Le genre doit être : masculin, féminin ou autre.',
            
            // Date de naissance
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'date_naissance.after' => 'La date de naissance doit être postérieure à 1900.',
            
            // Adresses
            'adresse_principale.string' => 'L\'adresse principale doit être une chaîne de caractères.',
            'adresse_principale.max' => 'L\'adresse principale ne peut pas dépasser 500 caractères.',
            
            'quartier.string' => 'Le quartier doit être une chaîne de caractères.',
            'quartier.max' => 'Le quartier ne peut pas dépasser 100 caractères.',
            
            'ville.required' => 'La ville est obligatoire.',
            'ville.string' => 'La ville doit être une chaîne de caractères.',
            'ville.max' => 'La ville ne peut pas dépasser 100 caractères.',
            
            'indications_livraison.string' => 'Les indications de livraison doivent être une chaîne de caractères.',
            'indications_livraison.max' => 'Les indications de livraison ne peuvent pas dépasser 1000 caractères.',
            
            // Préférences
            'taille_habituelle.string' => 'La taille habituelle doit être une chaîne de caractères.',
            'taille_habituelle.max' => 'La taille habituelle ne peut pas dépasser 10 caractères.',
            
            'couleurs_preferees.string' => 'Les couleurs préférées doivent être une chaîne de caractères.',
            'couleurs_preferees.max' => 'Les couleurs préférées ne peuvent pas dépasser 200 caractères.',
            
            'styles_preferes.string' => 'Les styles préférés doivent être une chaîne de caractères.',
            'styles_preferes.max' => 'Les styles préférés ne peuvent pas dépasser 200 caractères.',
            
            'budget_moyen.numeric' => 'Le budget moyen doit être un nombre.',
            'budget_moyen.min' => 'Le budget moyen ne peut pas être négatif.',
            'budget_moyen.max' => 'Le budget moyen ne peut pas dépasser 10 000 000.',
            
            // Communications
            'accepte_whatsapp.boolean' => 'L\'acceptation WhatsApp doit être vrai ou faux.',
            'accepte_email.boolean' => 'L\'acceptation email doit être vrai ou faux.',
            'accepte_sms.boolean' => 'L\'acceptation SMS doit être vrai ou faux.',
            'accepte_promotions.boolean' => 'L\'acceptation promotions doit être vrai ou faux.',
            
            'canaux_preferes.string' => 'Les canaux préférés doivent être une chaîne de caractères.',
            'canaux_preferes.max' => 'Les canaux préférés ne peuvent pas dépasser 100 caractères.',
            
            // Notes
            'notes_privees.string' => 'Les notes privées doivent être une chaîne de caractères.',
            'notes_privees.max' => 'Les notes privées ne peuvent pas dépasser 2000 caractères.',
            
            // Priorité
            'priorite.required' => 'La priorité est obligatoire.',
            'priorite.in' => 'La priorité doit être : normale, haute ou vip.',
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'accepte_whatsapp' => $this->boolean('accepte_whatsapp', false),
            'accepte_email' => $this->boolean('accepte_email', false),
            'accepte_sms' => $this->boolean('accepte_sms', false),
            'accepte_promotions' => $this->boolean('accepte_promotions', false),
            'budget_moyen' => $this->input('budget_moyen') ? (float) $this->input('budget_moyen') : null,
        ]);

        // Nettoyer le téléphone
        if ($this->has('telephone')) {
            $telephone = preg_replace('/[^0-9\+]/', '', $this->input('telephone'));
            $this->merge(['telephone' => $telephone]);
        }

        // Nettoyer l'email
        if ($this->has('email')) {
            $email = strtolower(trim($this->input('email')));
            $this->merge(['email' => $email ?: null]);
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
            // Vérifier que si email accepté, email fourni
            if ($this->boolean('accepte_email') && !$this->input('email')) {
                $validator->errors()->add('email', 'L\'email est requis si le client accepte les communications par email.');
            }

            // Vérifier que si WhatsApp accepté, téléphone fourni
            if ($this->boolean('accepte_whatsapp') && !$this->input('telephone')) {
                $validator->errors()->add('telephone', 'Le téléphone est requis si le client accepte WhatsApp.');
            }

            // Vérifier l'âge minimum (13 ans)
            if ($this->input('date_naissance')) {
                $dateNaissance = \Carbon\Carbon::parse($this->input('date_naissance'));
                if ($dateNaissance->diffInYears(now()) < 13) {
                    $validator->errors()->add('date_naissance', 'Le client doit avoir au moins 13 ans.');
                }
            }

            // Valider le format du téléphone sénégalais
            if ($this->input('telephone')) {
                $telephone = $this->input('telephone');
                if (!$this->isValidSenegalPhone($telephone)) {
                    $validator->errors()->add('telephone', 'Le numéro de téléphone n\'est pas un numéro sénégalais valide.');
                }
            }
        });
    }

    /**
     * Vérifier si le numéro est un téléphone sénégalais valide
     */
    private function isValidSenegalPhone(string $phone): bool
    {
        // Nettoyer le numéro
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Formats acceptés:
        // - 9 chiffres commençant par 7 (format local)
        // - 10 chiffres commençant par 07
        // - 12 chiffres commençant par 221 (avec indicatif)
        
        if (strlen($cleaned) === 9 && substr($cleaned, 0, 1) === '7') {
            return true;
        }
        
        if (strlen($cleaned) === 10 && substr($cleaned, 0, 2) === '07') {
            return true;
        }
        
        if (strlen($cleaned) === 12 && substr($cleaned, 0, 3) === '221') {
            return true;
        }
        
        return false;
    }
}