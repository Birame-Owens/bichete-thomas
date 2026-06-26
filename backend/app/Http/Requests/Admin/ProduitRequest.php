<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule; // AJOUTEZ CETTE LIGNE
class ProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $produitId = $this->route('produit') ? $this->route('produit')->id : null;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'nom' => [
                'required',
                'string',
                'min:3',
                'max:200',
                Rule::unique('produits', 'nom')->ignore($produitId)->whereNull('deleted_at')
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:5000'
            ],
            'description_courte' => [
                'nullable',
                'string',
                'max:300'
            ],
            'prix' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999.99'
            ],
            'prix_promo' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99',
                'lt:prix' // Prix promo doit être inférieur au prix normal
            ],
            'debut_promo' => [
                'nullable',
                'date',
            ],
            'fin_promo' => [
                'nullable',
                'date',
                'after:debut_promo'
            ],
            'categorie_id' => [
                'required',
                'integer',
                'exists:categories,id'
            ],
            'type_variante' => [
                'nullable',
                'string',
                'in:vetement,chaussure,parfum,aucun',
            ],
            'stock_disponible' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999'
            ],
            'seuil_alerte' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
            ],
            'gestion_stock' => [
                'boolean'
            ],
            'fait_sur_mesure' => [
                'boolean'
            ],
            'delai_production_jours' => [
                'nullable',
                'integer',
                'min:1',
                'max:365'
            ],
            'cout_production' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99'
            ],
            'tailles_disponibles' => [
                'nullable',
                'array'
            ],
            'tailles_disponibles.*' => [
                'string',
                'max:50'
            ],
            'couleurs_disponibles' => [
                'nullable',
                'array'
            ],
            'couleurs_disponibles.*' => [
                'string',
                'max:50'
            ],
            'materiaux_necessaires' => [
                'nullable',
                'array'
            ],
            'materiaux_necessaires.*' => [
                'string',
                'max:100'
            ],
            'est_visible' => [
                'boolean'
            ],
            'est_populaire' => [
                'boolean'
            ],
            'est_nouveaute' => [
                'boolean'
            ],
            'ordre_affichage' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999'
            ],
            'meta_titre' => [
                'nullable',
                'string',
                'max:70'
            ],
            'meta_description' => [
                'nullable',
                'string',
                'max:160'
            ],
            'tags' => [
                'nullable',
                'string',
                'max:500'
            ],
            
            // Images - obligatoire à la création, optionnelle en mise à jour
            'image_principale' => [
                $isUpdate ? 'nullable' : 'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:3072' // 3MB max
            ],
            'images' => [
                'nullable',
                'array',
                'max:10' // Maximum 10 images
            ],
            'images.*' => [
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:3072' // 3MB max par image
            ],
            'image_couleurs' => [
                'nullable',
                'array',
            ],
            'image_couleurs.*' => [
                'nullable',
                'string',
                'max:50',
            ],
            'couleur_tailles' => [
                'nullable',
                'array',
            ],
            'couleur_tailles.*' => [
                'array',
            ],
            'couleur_tailles.*.*' => [
                'string',
                'max:50',
            ],
            'couleur_tailles_stock' => [
                'nullable',
                'array',
            ],
            'couleur_tailles_stock.*' => [
                'array',
            ],
            'couleur_tailles_stock.*.*' => [
                'integer',
                'min:0',
                'max:999999',
            ],
            'couleur_tailles_seuil' => [
                'nullable',
                'array',
            ],
            'couleur_tailles_seuil.*' => [
                'array',
            ],
            'couleur_tailles_seuil.*.*' => [
                'integer',
                'min:0',
                'max:999999',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            // Nom
            'nom.required' => 'Le nom du produit est obligatoire.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.min' => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 200 caractères.',
            'nom.unique' => 'Ce nom de produit existe déjà.',
            
            // Description
            'description.required' => 'La description est obligatoire.',
            'description.string' => 'La description doit être une chaîne de caractères.',
            'description.min' => 'La description doit contenir au moins 10 caractères.',
            'description.max' => 'La description ne peut pas dépasser 5000 caractères.',
            
            'description_courte.string' => 'La description courte doit être une chaîne de caractères.',
            'description_courte.max' => 'La description courte ne peut pas dépasser 300 caractères.',
            
            // Prix
            'prix.required' => 'Le prix est obligatoire.',
            'prix.numeric' => 'Le prix doit être un nombre.',
            'prix.min' => 'Le prix ne peut pas être négatif.',
            'prix.max' => 'Le prix ne peut pas dépasser 9 999 999,99.',
            
            'prix_promo.numeric' => 'Le prix promotionnel doit être un nombre.',
            'prix_promo.min' => 'Le prix promotionnel ne peut pas être négatif.',
            'prix_promo.max' => 'Le prix promotionnel ne peut pas dépasser 9 999 999,99.',
            'prix_promo.lt' => 'Le prix promotionnel doit être inférieur au prix normal.',
            
            // Dates promo
            'debut_promo.date' => 'La date de début de promotion doit être une date valide.',
            'debut_promo.after_or_equal' => 'La date de début de promotion ne peut pas être dans le passé.',
            
            'fin_promo.date' => 'La date de fin de promotion doit être une date valide.',
            'fin_promo.after' => 'La date de fin de promotion doit être après la date de début.',
            
            // Catégorie
            'categorie_id.required' => 'La catégorie est obligatoire.',
            'categorie_id.integer' => 'L\'ID de la catégorie doit être un nombre entier.',
            'categorie_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            
            // Stock
            'stock_disponible.required_if' => 'Le stock disponible est obligatoire si la gestion de stock est activée.',
            'stock_disponible.integer' => 'Le stock disponible doit être un nombre entier.',
            'stock_disponible.min' => 'Le stock disponible ne peut pas être négatif.',
            'stock_disponible.max' => 'Le stock disponible ne peut pas dépasser 999 999.',
            
            'seuil_alerte.integer' => 'Le seuil d\'alerte doit être un nombre entier.',
            'seuil_alerte.min' => 'Le seuil d\'alerte ne peut pas être négatif.',
            'seuil_alerte.max' => 'Le seuil d\'alerte ne peut pas dépasser 999 999.',
            'seuil_alerte.lte' => 'Le seuil d\'alerte ne peut pas être supérieur au stock disponible.',
            
            // Production
            'delai_production_jours.integer' => 'Le délai de production doit être un nombre entier.',
            'delai_production_jours.min' => 'Le délai de production doit être d\'au moins 1 jour.',
            'delai_production_jours.max' => 'Le délai de production ne peut pas dépasser 365 jours.',
            
            'cout_production.numeric' => 'Le coût de production doit être un nombre.',
            'cout_production.min' => 'Le coût de production ne peut pas être négatif.',
            'cout_production.max' => 'Le coût de production ne peut pas dépasser 9 999 999,99.',
            
            // Arrays
            'tailles_disponibles.array' => 'Les tailles disponibles doivent être un tableau.',
            'tailles_disponibles.*.string' => 'Chaque taille doit être une chaîne de caractères.',
            'tailles_disponibles.*.max' => 'Chaque taille ne peut pas dépasser 50 caractères.',
            
            'couleurs_disponibles.array' => 'Les couleurs disponibles doivent être un tableau.',
            'couleurs_disponibles.*.string' => 'Chaque couleur doit être une chaîne de caractères.',
            'couleurs_disponibles.*.max' => 'Chaque couleur ne peut pas dépasser 50 caractères.',
            
            'materiaux_necessaires.array' => 'Les matériaux nécessaires doivent être un tableau.',
            'materiaux_necessaires.*.string' => 'Chaque matériau doit être une chaîne de caractères.',
            'materiaux_necessaires.*.max' => 'Chaque matériau ne peut pas dépasser 100 caractères.',
            
            // Booleans
            'gestion_stock.boolean' => 'La gestion de stock doit être vraie ou fausse.',
            'fait_sur_mesure.boolean' => 'Le fait sur mesure doit être vrai ou faux.',
            'est_visible.boolean' => 'La visibilité doit être vraie ou fausse.',
            'est_populaire.boolean' => 'Le statut populaire doit être vrai ou faux.',
            'est_nouveaute.boolean' => 'Le statut nouveauté doit être vrai ou faux.',
            
            // Autres
            'ordre_affichage.integer' => 'L\'ordre d\'affichage doit être un nombre entier.',
            'ordre_affichage.min' => 'L\'ordre d\'affichage ne peut pas être négatif.',
            'ordre_affichage.max' => 'L\'ordre d\'affichage ne peut pas dépasser 999 999.',
            
            'meta_titre.string' => 'Le méta titre doit être une chaîne de caractères.',
            'meta_titre.max' => 'Le méta titre ne peut pas dépasser 70 caractères.',
            
            'meta_description.string' => 'La méta description doit être une chaîne de caractères.',
            'meta_description.max' => 'La méta description ne peut pas dépasser 160 caractères.',
            
            'tags.string' => 'Les tags doivent être une chaîne de caractères.',
            'tags.max' => 'Les tags ne peuvent pas dépasser 500 caractères.',
            
            // Images
            'image_principale.required' => 'L\'image principale est obligatoire.',
            'image_principale.image' => 'Le fichier sélectionné doit être une image valide (vérifiez que le fichier n\'est pas corrompu).',
            'image_principale.mimes' => 'L\'image principale doit être au format JPEG, PNG, JPG ou WebP.',
            'image_principale.max' => 'L\'image principale ne peut pas dépasser 3 MB.',
            
            'images.array' => 'Les images doivent être un tableau.',
            'images.max' => 'Vous ne pouvez pas télécharger plus de 10 images.',
            
            'images.*.image' => 'Chaque fichier doit être une image.',
            'images.*.mimes' => 'Chaque image doit être au format JPEG, PNG, JPG ou WebP.',
            'images.*.max' => 'Chaque image ne peut pas dépasser 3 MB.',
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'gestion_stock' => $this->boolean('gestion_stock', true),
            'fait_sur_mesure' => $this->boolean('fait_sur_mesure', false),
            'est_visible' => $this->boolean('est_visible', true),
            'est_populaire' => $this->boolean('est_populaire', false),
            'est_nouveaute' => $this->boolean('est_nouveaute', false),
            'ordre_affichage' => $this->integer('ordre_affichage', 0),
        ]);

        // Nettoyer les prix
        if ($this->has('prix')) {
            $this->merge(['prix' => (float) $this->input('prix')]);
        }
        
        if ($this->has('prix_promo') && $this->input('prix_promo') !== null) {
            $this->merge(['prix_promo' => (float) $this->input('prix_promo')]);
        }

        // Si pas de prix promo, mettre null ET effacer les dates de promo
        if (!$this->has('prix_promo') || $this->input('prix_promo') === '' || $this->input('prix_promo') === '0') {
            $this->merge([
                'prix_promo' => null,
                'debut_promo' => null,
                'fin_promo' => null,
            ]);
        }

        // Nettoyer les arrays vides
        if ($this->has('tailles_disponibles') && empty($this->input('tailles_disponibles'))) {
            $this->merge(['tailles_disponibles' => []]);
        }
        
        if ($this->has('couleurs_disponibles') && empty($this->input('couleurs_disponibles'))) {
            $this->merge(['couleurs_disponibles' => []]);
        }
        
        if ($this->has('materiaux_necessaires') && empty($this->input('materiaux_necessaires'))) {
            $this->merge(['materiaux_necessaires' => []]);
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
            // Si prix promo défini, vérifier les dates
            if ($this->prix_promo) {
                if (!$this->debut_promo || !$this->fin_promo) {
                    $validator->errors()->add('debut_promo', 'Les dates de promotion sont obligatoires si un prix promotionnel est défini.');
                    $validator->errors()->add('fin_promo', 'Les dates de promotion sont obligatoires si un prix promotionnel est défini.');
                }
            }

            // Si fait sur mesure, délai de production recommandé
            if ($this->fait_sur_mesure && !$this->delai_production_jours) {
                $validator->errors()->add('delai_production_jours', 'Le délai de production est recommandé pour les produits sur mesure.');
            }

            // Le stock est géré par variante (couleur_tailles_stock), pas de validation globale nécessaire

            // Validation des tailles
            if ($this->tailles_disponibles && is_array($this->tailles_disponibles)) {
                $tailles = array_filter($this->tailles_disponibles); // Supprimer les valeurs vides
                if (count($tailles) !== count(array_unique($tailles))) {
                    $validator->errors()->add('tailles_disponibles', 'Les tailles ne peuvent pas être dupliquées.');
                }
            }

            // Validation des couleurs
            if ($this->couleurs_disponibles && is_array($this->couleurs_disponibles)) {
                $couleurs = array_filter($this->couleurs_disponibles); // Supprimer les valeurs vides
                if (count($couleurs) !== count(array_unique($couleurs))) {
                    $validator->errors()->add('couleurs_disponibles', 'Les couleurs ne peuvent pas être dupliquées.');
                }
            }
        });
    }
}