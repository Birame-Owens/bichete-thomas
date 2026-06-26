<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

   public function rules(): array
{
    $promotionId = $this->route('promotion') ? $this->route('promotion')->id : null;

    return [
        'nom' => [
            'required',
            'string',
            'max:100',
            'min:3'
        ],
        'code' => [
            'nullable',
            'string',
            'max:20',
            'alpha_num',
            Rule::unique('promotions', 'code')->ignore($promotionId)
        ],
        'description' => [
            'required',
            'string',
            'max:1000'
        ],
        'image' => [
            'nullable',
            'image',
            'mimes:jpeg,jpg,png,gif,webp',
            'max:2048' // 2MB
        ],
        'type_promotion' => [
            'required',
            'string',
            'in:pourcentage,montant_fixe,livraison_gratuite'
        ],
        'valeur' => [
            'required',
            'numeric',
            'min:0'
        ],
        'montant_minimum' => [
            'nullable',
            'numeric',
            'min:0',
            'max:10000000'
        ],
        'reduction_maximum' => [
            'nullable',
            'numeric',
            'min:0',
            'max:10000000'
        ],
        'date_debut' => array_filter([
            'required',
            'date',
            $this->isMethod('POST') && !$this->route('promotion') ? 'after_or_equal:today' : null,
        ]),
        'date_fin' => [
            'required',
            'date',
            'after:date_debut'
        ],
        'est_active' => [
            'boolean'
        ],
        'utilisation_maximum' => [
            'nullable',
            'integer',
            'min:1',
            'max:100000'
        ],
        'utilisation_par_client' => [
            'required',
            'integer',
            'min:1',
            'max:100'
        ],
        'cible_client' => [
            'required',
            'string',
            'in:tous,nouveaux,vip,reguliers' // ← Corrigé pour correspondre aux options
        ],
        'categories_eligibles' => [
            'nullable',
            'array'
        ],
        'categories_eligibles.*' => [
            'integer',
            'exists:categories,id' // ← Correct: table 'categories'
        ],
        'produits_eligibles' => [
            'nullable',
            'array'
        ],
        'produits_eligibles.*' => [
            'integer',
            'exists:produits,id' // ← Correct: table 'produits'
        ],
        'cumul_avec_autres' => [
            'boolean'
        ],
        'premiere_commande_seulement' => [
            'boolean'
        ],
        'jours_semaine_valides' => [
            'nullable',
            'array'
        ],
        'jours_semaine_valides.*' => [
            'integer',
            'min:0',
            'max:6'
        ],
        'afficher_site' => [
            'boolean'
        ],
        'envoyer_whatsapp' => [
            'boolean'
        ],
        'envoyer_email' => [
            'boolean'
        ],
        'couleur_affichage' => [
            'nullable',
            'string',
            'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
        ]
    ];
}

public function messages(): array
{
    return [
        // Nom
        'nom.required' => 'Le nom de la promotion est obligatoire.',
        'nom.string' => 'Le nom doit être une chaîne de caractères.',
        'nom.max' => 'Le nom ne peut pas dépasser 100 caractères.',
        'nom.min' => 'Le nom doit contenir au moins 3 caractères.',

        // Code
        'code.string' => 'Le code doit être une chaîne de caractères.',
        'code.max' => 'Le code ne peut pas dépasser 20 caractères.',
        'code.alpha_num' => 'Le code ne peut contenir que des lettres et des chiffres.',
        'code.unique' => 'Ce code promo existe déjà.',

        // Description
        'description.required' => 'La description est obligatoire.',
        'description.string' => 'La description doit être une chaîne de caractères.',
        'description.max' => 'La description ne peut pas dépasser 1000 caractères.',

        // Image
        'image.image' => 'Le fichier doit être une image.',
        'image.mimes' => 'L\'image doit être au format : jpeg, jpg, png, gif ou webp.',
        'image.max' => 'L\'image ne peut pas dépasser 2 Mo.',

        // Type de promotion
        'type_promotion.required' => 'Le type de promotion est obligatoire.',
        'type_promotion.in' => 'Le type doit être : pourcentage, montant fixe ou livraison gratuite.',

        // Valeur
        'valeur.required' => 'La valeur de la promotion est obligatoire.',
        'valeur.numeric' => 'La valeur doit être un nombre.',
        'valeur.min' => 'La valeur ne peut pas être négative.',

        // Montant minimum
        'montant_minimum.numeric' => 'Le montant minimum doit être un nombre.',
        'montant_minimum.min' => 'Le montant minimum ne peut pas être négatif.',
        'montant_minimum.max' => 'Le montant minimum ne peut pas dépasser 10 000 000 FCFA.',

        // Réduction maximum
        'reduction_maximum.numeric' => 'La réduction maximum doit être un nombre.',
        'reduction_maximum.min' => 'La réduction maximum ne peut pas être négative.',
        'reduction_maximum.max' => 'La réduction maximum ne peut pas dépasser 10 000 000 FCFA.',

        // Dates
        'date_debut.required' => 'La date de début est obligatoire.',
        'date_debut.date' => 'La date de début doit être une date valide.',
        'date_debut.after_or_equal' => 'La date de début ne peut pas être dans le passé.',

        'date_fin.required' => 'La date de fin est obligatoire.',
        'date_fin.date' => 'La date de fin doit être une date valide.',
        'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',

        // Statut
        'est_active.boolean' => 'Le statut actif doit être vrai ou faux.',

        // Utilisations
        'utilisation_maximum.integer' => 'Le nombre d\'utilisations maximum doit être un nombre entier.',
        'utilisation_maximum.min' => 'Le nombre d\'utilisations maximum doit être au moins 1.',
        'utilisation_maximum.max' => 'Le nombre d\'utilisations maximum ne peut pas dépasser 100 000.',

        'utilisation_par_client.required' => 'Le nombre d\'utilisations par client est obligatoire.',
        'utilisation_par_client.integer' => 'Le nombre d\'utilisations par client doit être un nombre entier.',
        'utilisation_par_client.min' => 'Le nombre d\'utilisations par client doit être au moins 1.',
        'utilisation_par_client.max' => 'Le nombre d\'utilisations par client ne peut pas dépasser 100.',

        // Cible client - Message corrigé
        'cible_client.required' => 'La cible client est obligatoire.',
        'cible_client.in' => 'La cible doit être : tous, nouveaux, VIP ou réguliers.',

        // Catégories éligibles
        'categories_eligibles.array' => 'Les catégories éligibles doivent être un tableau.',
        'categories_eligibles.*.integer' => 'Chaque catégorie doit être un nombre entier.',
        'categories_eligibles.*.exists' => 'Une ou plusieurs catégories sélectionnées n\'existent pas.',

        // Produits éligibles
        'produits_eligibles.array' => 'Les produits éligibles doivent être un tableau.',
        'produits_eligibles.*.integer' => 'Chaque produit doit être un nombre entier.',
        'produits_eligibles.*.exists' => 'Un ou plusieurs produits sélectionnés n\'existent pas.',

        // Options booléennes
        'cumul_avec_autres.boolean' => 'L\'option cumul doit être vrai ou faux.',
        'premiere_commande_seulement.boolean' => 'L\'option première commande doit être vrai ou faux.',
        'afficher_site.boolean' => 'L\'option affichage site doit être vrai ou faux.',
        'envoyer_whatsapp.boolean' => 'L\'option envoi WhatsApp doit être vrai ou faux.',
        'envoyer_email.boolean' => 'L\'option envoi email doit être vrai ou faux.',

        // Jours de la semaine
        'jours_semaine_valides.array' => 'Les jours valides doivent être un tableau.',
        'jours_semaine_valides.*.integer' => 'Chaque jour doit être un nombre entier.',
        'jours_semaine_valides.*.min' => 'Le jour doit être entre 0 et 6.',
        'jours_semaine_valides.*.max' => 'Le jour doit être entre 0 et 6.',

        // Couleur
        'couleur_affichage.regex' => 'La couleur doit être au format hexadécimal (#FFFFFF).',
    ];
}

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'est_active' => $this->boolean('est_active', false),
            'cumul_avec_autres' => $this->boolean('cumul_avec_autres', false),
            'premiere_commande_seulement' => $this->boolean('premiere_commande_seulement', false),
            'afficher_site' => $this->boolean('afficher_site', true),
            'envoyer_whatsapp' => $this->boolean('envoyer_whatsapp', false),
            'envoyer_email' => $this->boolean('envoyer_email', false),
        ]);

        // Convertir les valeurs numériques
        if ($this->has('valeur')) {
            $this->merge(['valeur' => (float) $this->input('valeur')]);
        }

        if ($this->has('montant_minimum') && $this->input('montant_minimum')) {
            $this->merge(['montant_minimum' => (float) $this->input('montant_minimum')]);
        }

        if ($this->has('reduction_maximum') && $this->input('reduction_maximum')) {
            $this->merge(['reduction_maximum' => (float) $this->input('reduction_maximum')]);
        }

        if ($this->has('utilisation_maximum') && $this->input('utilisation_maximum')) {
            $this->merge(['utilisation_maximum' => (int) $this->input('utilisation_maximum')]);
        }

        if ($this->has('utilisation_par_client')) {
            $this->merge(['utilisation_par_client' => (int) $this->input('utilisation_par_client', 1)]);
        }

        // Toujours normaliser les tableaux d'IDs en entiers (même vides)
        // Cela garantit que des strings "3" deviennent des entiers 3,
        // et que la suppression de toutes les valeurs envoie un tableau vide.
        $this->merge([
            'jours_semaine_valides' => array_map('intval', (array) $this->input('jours_semaine_valides', [])),
            'categories_eligibles'  => array_map('intval', (array) $this->input('categories_eligibles', [])),
            'produits_eligibles'    => array_map('intval', (array) $this->input('produits_eligibles', [])),
        ]);
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
            // Validation spécifique selon le type de promotion
            $typePromotion = $this->input('type_promotion');
            $valeur = $this->input('valeur');

            if ($typePromotion === 'pourcentage' && $valeur > 100) {
                $validator->errors()->add('valeur', 'Le pourcentage ne peut pas dépasser 100%.');
            }

            if ($typePromotion === 'montant_fixe' && $valeur > 1000000) {
                $validator->errors()->add('valeur', 'Le montant fixe ne peut pas dépasser 1 000 000 FCFA.');
            }

            if ($typePromotion === 'livraison_gratuite' && $valeur != 0) {
                $validator->errors()->add('valeur', 'La valeur doit être 0 pour la livraison gratuite.');
            }

            // Validation de cohérence des montants
            $montantMin = $this->input('montant_minimum');
            $reductionMax = $this->input('reduction_maximum');

            if ($montantMin && $reductionMax && $typePromotion === 'montant_fixe') {
                if ($reductionMax > $montantMin) {
                    $validator->errors()->add('reduction_maximum', 
                        'La réduction maximum ne peut pas être supérieure au montant minimum.');
                }
            }

            // Validation de la durée de la promotion
          // Validation de la durée de la promotion
if ($this->input('date_debut') && $this->input('date_fin')) {
    $dateDebut = Carbon::parse($this->input('date_debut'));
    $dateFin = Carbon::parse($this->input('date_fin'));
    
    // Vérifier que la date de fin est postérieure à la date de début
    if ($dateFin->lte($dateDebut)) {
        $validator->errors()->add('date_fin', 
            'La date de fin doit être postérieure à la date de début.');
    }
    
    // Vérifier la durée maximale seulement (la durée minimale est déjà gérée par 'after:date_debut')
    $dureeJours = $dateFin->diffInDays($dateDebut, false);
    if ($dureeJours > 365) {
        $validator->errors()->add('date_fin', 
            'Une promotion ne peut pas durer plus d\'un an.');
    }
}

            // Validation des utilisations
            $utilisationMax = $this->input('utilisation_maximum');
            $utilisationParClient = $this->input('utilisation_par_client');

            if ($utilisationMax && $utilisationParClient && $utilisationParClient > $utilisationMax) {
                $validator->errors()->add('utilisation_par_client', 
                    'Le nombre d\'utilisations par client ne peut pas dépasser le maximum global.');
            }

            // Validation de la cible première commande
            if ($this->boolean('premiere_commande_seulement') && 
                $this->input('cible_client') !== 'nouveaux' && 
                $this->input('cible_client') !== 'tous') {
                $validator->errors()->add('premiere_commande_seulement', 
                    'L\'option "première commande seulement" n\'est compatible qu\'avec les cibles "tous" ou "nouveaux clients".');
            }

            // Validation des jours de la semaine
            $joursValides = $this->input('jours_semaine_valides');
            if ($joursValides && count($joursValides) === 7) {
                $validator->errors()->add('jours_semaine_valides', 
                    'Si tous les jours sont sélectionnés, laissez ce champ vide.');
            }

            // Validation de l'éligibilité produits/catégories
            $categories = $this->input('categories_eligibles');
            $produits = $this->input('produits_eligibles');

            if ($categories && $produits) {
                $validator->errors()->add('categories_eligibles', 
                    'Vous ne pouvez pas sélectionner à la fois des catégories et des produits spécifiques.');
            }
        });
    }
}