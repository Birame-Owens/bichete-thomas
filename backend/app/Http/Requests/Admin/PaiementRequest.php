<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commande_id' => [
                'required',
                'integer',
                'exists:commandes,id'
            ],
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id'
            ],
            'montant' => [
                'required',
                'numeric',
                'min:100',
                'max:10000000'
            ],
            'methode_paiement' => [
                'required',
                'string',
                'in:carte_bancaire,wave,orange_money,virement,especes,cheque'
            ],
            'numero_telephone' => [
                'required_if:methode_paiement,wave,orange_money',
                'nullable',
                'string',
                'regex:/^[0-9\+\-\s\(\)]+$/'
            ],
            'est_acompte' => [
                'boolean'
            ],
            'montant_restant' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'date_echeance' => [
                'nullable',
                'date',
                'after:today'
            ],
            'commentaire_client' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'notes_admin' => [
                'nullable',
                'string',
                'max:2000'
            ],
            
            // Données spécifiques aux cartes
            'card_number' => [
                'required_if:methode_paiement,carte_bancaire',
                'nullable',
                'string',
                'regex:/^[0-9\s\-]+$/',
                'min:13',
                'max:19'
            ],
            'card_expiry' => [
                'required_if:methode_paiement,carte_bancaire',
                'nullable',
                'string',
                'regex:/^(0[1-9]|1[0-2])\/[0-9]{2}$/'
            ],
            'card_cvv' => [
                'required_if:methode_paiement,carte_bancaire',
                'nullable',
                'string',
                'regex:/^[0-9]{3,4}$/'
            ],
            'card_holder_name' => [
                'required_if:methode_paiement,carte_bancaire',
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\'\.]+$/'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            // Commande et client
            'commande_id.required' => 'La commande est obligatoire.',
            'commande_id.integer' => 'L\'ID de la commande doit être un nombre entier.',
            'commande_id.exists' => 'La commande sélectionnée n\'existe pas.',
            
            'client_id.required' => 'Le client est obligatoire.',
            'client_id.integer' => 'L\'ID du client doit être un nombre entier.',
            'client_id.exists' => 'Le client sélectionné n\'existe pas.',
            
            // Montant
            'montant.required' => 'Le montant est obligatoire.',
            'montant.numeric' => 'Le montant doit être un nombre.',
            'montant.min' => 'Le montant minimum est de 100 FCFA.',
            'montant.max' => 'Le montant maximum est de 10 000 000 FCFA.',
            
            // Méthode de paiement
            'methode_paiement.required' => 'La méthode de paiement est obligatoire.',
            'methode_paiement.in' => 'La méthode de paiement doit être : carte bancaire, Wave, Orange Money, virement, espèces ou chèque.',
            
            // Téléphone
            'numero_telephone.required_if' => 'Le numéro de téléphone est requis pour Wave et Orange Money.',
            'numero_telephone.regex' => 'Le format du numéro de téléphone n\'est pas valide.',
            
            // Acompte
            'est_acompte.boolean' => 'Le statut acompte doit être vrai ou faux.',
            'montant_restant.numeric' => 'Le montant restant doit être un nombre.',
            'montant_restant.min' => 'Le montant restant ne peut pas être négatif.',
            
            // Date
            'date_echeance.date' => 'La date d\'échéance doit être une date valide.',
            'date_echeance.after' => 'La date d\'échéance doit être dans le futur.',
            
            // Commentaires
            'commentaire_client.string' => 'Le commentaire client doit être une chaîne de caractères.',
            'commentaire_client.max' => 'Le commentaire client ne peut pas dépasser 1000 caractères.',
            
            'notes_admin.string' => 'Les notes administratives doivent être une chaîne de caractères.',
            'notes_admin.max' => 'Les notes administratives ne peuvent pas dépasser 2000 caractères.',
            
            // Carte bancaire
            'card_number.required_if' => 'Le numéro de carte est requis pour les paiements par carte.',
            'card_number.regex' => 'Le format du numéro de carte n\'est pas valide.',
            'card_number.min' => 'Le numéro de carte doit contenir au moins 13 chiffres.',
            'card_number.max' => 'Le numéro de carte ne peut pas dépasser 19 chiffres.',
            
            'card_expiry.required_if' => 'La date d\'expiration est requise pour les paiements par carte.',
            'card_expiry.regex' => 'La date d\'expiration doit être au format MM/AA.',
            
            'card_cvv.required_if' => 'Le code CVV est requis pour les paiements par carte.',
            'card_cvv.regex' => 'Le code CVV doit contenir 3 ou 4 chiffres.',
            
            'card_holder_name.required_if' => 'Le nom du porteur est requis pour les paiements par carte.',
            'card_holder_name.max' => 'Le nom du porteur ne peut pas dépasser 100 caractères.',
            'card_holder_name.regex' => 'Le nom du porteur ne peut contenir que des lettres, espaces et tirets.',
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'est_acompte' => $this->boolean('est_acompte', false),
            'montant' => $this->input('montant') ? (float) $this->input('montant') : null,
            'montant_restant' => $this->input('montant_restant') ? (float) $this->input('montant_restant') : null,
        ]);

        // Nettoyer le numéro de téléphone
        if ($this->has('numero_telephone')) {
            $telephone = preg_replace('/[^0-9\+]/', '', $this->input('numero_telephone'));
            $this->merge(['numero_telephone' => $telephone ?: null]);
        }

        // Nettoyer le numéro de carte
        if ($this->has('card_number')) {
            $cardNumber = preg_replace('/[^0-9]/', '', $this->input('card_number'));
            $this->merge(['card_number' => $cardNumber ?: null]);
        }

        // Nettoyer le CVV
        if ($this->has('card_cvv')) {
            $cvv = preg_replace('/[^0-9]/', '', $this->input('card_cvv'));
            $this->merge(['card_cvv' => $cvv ?: null]);
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
            // Vérifier que le montant ne dépasse pas le montant de la commande
            if ($this->input('commande_id') && $this->input('montant')) {
                $commande = \App\Models\Commande::find($this->input('commande_id'));
                if ($commande) {
                    $totalPaye = $commande->paiements()->where('statut', 'valide')->sum('montant');
                    $montantRestant = $commande->montant_total - $totalPaye;
                    
                    if ($this->input('montant') > $montantRestant) {
                        $validator->errors()->add('montant', "Le montant ne peut pas dépasser {$montantRestant} FCFA (montant restant dû).");
                    }
                }
            }

            // Vérifier le client correspond à la commande
            if ($this->input('commande_id') && $this->input('client_id')) {
                $commande = \App\Models\Commande::find($this->input('commande_id'));
                if ($commande && $commande->client_id !== $this->input('client_id')) {
                    $validator->errors()->add('client_id', 'Le client sélectionné ne correspond pas à la commande.');
                }
            }

            // Validation spécifique pour les téléphones sénégalais
            if ($this->input('numero_telephone')) {
                $telephone = $this->input('numero_telephone');
                if (!$this->isValidSenegalPhone($telephone)) {
                    $validator->errors()->add('numero_telephone', 'Le numéro de téléphone n\'est pas un numéro sénégalais valide.');
                }
            }

            // Validation de la carte bancaire
            if ($this->input('methode_paiement') === 'carte_bancaire') {
                // Vérifier la date d'expiration
                if ($this->input('card_expiry')) {
                    $expiry = $this->input('card_expiry');
                    [$month, $year] = explode('/', $expiry);
                    $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', '20' . $year . '-' . $month . '-01')->endOfMonth();
                    
                    if ($expiryDate->isPast()) {
                        $validator->errors()->add('card_expiry', 'La carte a expiré.');
                    }
                }

                // Validation du numéro de carte (algorithme de Luhn)
                if ($this->input('card_number')) {
                    if (!$this->isValidCardNumber($this->input('card_number'))) {
                        $validator->errors()->add('card_number', 'Le numéro de carte n\'est pas valide.');
                    }
                }
            }

            // Validation des acomptes
            if ($this->boolean('est_acompte')) {
                if (!$this->input('montant_restant')) {
                    $validator->errors()->add('montant_restant', 'Le montant restant est obligatoire pour un acompte.');
                }
                
                if ($this->input('montant') && $this->input('montant_restant')) {
                    $total = $this->input('montant') + $this->input('montant_restant');
                    $commande = \App\Models\Commande::find($this->input('commande_id'));
                    
                    if ($commande && abs($total - $commande->montant_total) > 1) {
                        $validator->errors()->add('montant_restant', 'La somme de l\'acompte et du montant restant doit égaler le montant total de la commande.');
                    }
                }
            }
        });
    }

    /**
     * Vérifier si le numéro est un téléphone sénégalais valide
     */
    private function isValidSenegalPhone(string $phone): bool
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Formats acceptés pour Wave et Orange Money
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

    /**
     * Valider le numéro de carte avec l'algorithme de Luhn
     */
    private function isValidCardNumber(string $cardNumber): bool
    {
        $cardNumber = preg_replace('/[^0-9]/', '', $cardNumber);
        $length = strlen($cardNumber);
        
        if ($length < 13 || $length > 19) {
            return false;
        }
        
        $sum = 0;
        $alternate = false;
        
        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = intval($cardNumber[$i]);
            
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        return $sum % 10 === 0;
    }
}