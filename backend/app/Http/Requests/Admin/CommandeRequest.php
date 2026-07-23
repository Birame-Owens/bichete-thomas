<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Informations client
            'client_id' => [
                'nullable',
                'integer',
                'exists:clients,id'
            ],
            'new_client' => [
                'nullable',
                'array'
            ],
            'new_client.nom_complet' => [
                'nullable',
                'string',
                'max:150'
            ],
            'new_client.nom' => [
                'nullable',
                'string',
                'max:100'
            ],
            'new_client.prenom' => [
                'nullable',
                'string',
                'max:100'
            ],
            'new_client.telephone' => [
                'nullable',
                'string',
                'max:20'
            ],
            'new_client.email' => [
                'nullable',
                'email',
                'max:150'
            ],
            'new_client.adresse' => [
                'nullable',
                'string',
                'max:500'
            ],
            'new_client.ville' => [
                'nullable',
                'string',
                'max:100'
            ],
            
            // Informations destinataire
            'nom_destinataire' => [
                'required',
                'string',
                'max:100'
            ],
            'telephone_livraison' => [
                'required',
                'string',
                'max:20'
            ],
            'adresse_livraison' => [
                'required',
                'string',
                'max:500'
            ],
            'instructions_livraison' => [
                'nullable',
                'string',
                'max:1000'
            ],
            
            // Livraison
            'mode_livraison' => [
                'required',
                'string',
                'in:domicile,boutique,magasin,point_relais'
            ],
            'date_livraison_prevue' => [
                'nullable',
                'date',
                'after:now'
            ],
            
            // Notes
            'notes_client' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'notes_admin' => [
                'nullable',
                'string',
                'max:1000'
            ],
            
            // Paramètres commande
            'priorite' => [
                'required',
                'string',
                'in:normale,urgente,tres_urgente'
            ],
            'est_cadeau' => [
                'boolean'
            ],
            'message_cadeau' => [
                'nullable',
                'string',
                'max:500'
            ],
            'code_promo' => [
                'nullable',
                'string',
                'max:20'
            ],
            
            // Montants
            'frais_livraison' => [
                'required',
                'numeric',
                'min:0',
                'max:50000'
            ],
            'remise' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1000000'
            ],
            
            // Articles de la commande
            'articles' => [
                'required',
                'array',
                'min:1',
                'max:20' // Limite à 20 articles par commande
            ],
            'articles.*.produit_id' => [
                'required',
                'integer',
                'exists:produits,id'
            ],
            'articles.*.quantite' => [
                'required',
                'integer',
                'min:1',
                'max:50'
            ],
            'articles.*.prix_unitaire' => [
                'required',
                'numeric',
                'min:0'
            ],
            'articles.*.taille' => [
                'nullable',
                'string',
                'max:50'
            ],
            'articles.*.couleur' => [
                'nullable',
                'string',
                'max:50'
            ],
            'articles.*.instructions' => [
                'nullable',
                'string',
                'max:500'
            ],
            
            // Mesures pour les articles
            'articles.*.utilise_mesures_client' => [
                'nullable',
                'boolean'
            ],
            'articles.*.mesures' => [
                'nullable',
                'array'
            ],
            
            // Validation des mesures individuelles
            'articles.*.mesures.epaule' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.poitrine' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.taille' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.longueur_robe' => 'nullable|numeric|min:0|max:300',
            'articles.*.mesures.tour_bras' => 'nullable|numeric|min:0|max:100',
            'articles.*.mesures.tour_cuisses' => 'nullable|numeric|min:0|max:150',
            'articles.*.mesures.longueur_jupe' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.ceinture' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.tour_fesses' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.buste' => 'nullable|numeric|min:0|max:200',
            'articles.*.mesures.longueur_manches_longues' => 'nullable|numeric|min:0|max:150',
            'articles.*.mesures.longueur_manches_courtes' => 'nullable|numeric|min:0|max:100',
            'articles.*.mesures.longueur_short' => 'nullable|numeric|min:0|max:100',
            'articles.*.mesures.cou' => 'nullable|numeric|min:0|max:100',
            'articles.*.mesures.longueur_taille_basse' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            // Client
            'client_id.exists' => 'Le client sélectionné n\'existe pas.',
            
            // Destinataire
            'nom_destinataire.required' => 'Le nom du destinataire est obligatoire.',
            'nom_destinataire.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            
            'telephone_livraison.required' => 'Le téléphone de livraison est obligatoire.',
            'telephone_livraison.max' => 'Le téléphone ne peut pas dépasser 20 caractères.',
            
            'adresse_livraison.required' => 'L\'adresse de livraison est obligatoire.',
            'adresse_livraison.max' => 'L\'adresse ne peut pas dépasser 500 caractères.',
            
            // Mode et date de livraison
            'mode_livraison.required' => 'Le mode de livraison est obligatoire.',
            'mode_livraison.in' => 'Le mode de livraison doit être : domicile, magasin ou point_relais.',
            
            'date_livraison_prevue.date' => 'La date de livraison doit être une date valide.',
            'date_livraison_prevue.after' => 'La date de livraison doit être dans le futur.',
            
            // Priorité
            'priorite.required' => 'La priorité est obligatoire.',
            'priorite.in' => 'La priorité doit être : normale, urgente ou tres_urgente.',
            
            // Montants
            'frais_livraison.required' => 'Les frais de livraison sont obligatoires.',
            'frais_livraison.numeric' => 'Les frais de livraison doivent être un nombre.',
            'frais_livraison.min' => 'Les frais de livraison ne peuvent pas être négatifs.',
            
            'remise.numeric' => 'La remise doit être un nombre.',
            'remise.min' => 'La remise ne peut pas être négative.',
            
            // Articles
            'articles.required' => 'Au moins un article est requis.',
            'articles.min' => 'Au moins un article est requis.',
            'articles.max' => 'Maximum 20 articles par commande.',
            
            'articles.*.produit_id.required' => 'Le produit est obligatoire pour chaque article.',
            'articles.*.produit_id.exists' => 'Le produit sélectionné n\'existe pas.',
            
            'articles.*.quantite.required' => 'La quantité est obligatoire.',
            'articles.*.quantite.min' => 'La quantité doit être d\'au moins 1.',
            'articles.*.quantite.max' => 'La quantité ne peut pas dépasser 50.',
            
            'articles.*.prix_unitaire.required' => 'Le prix unitaire est obligatoire.',
            'articles.*.prix_unitaire.numeric' => 'Le prix unitaire doit être un nombre.',
            'articles.*.prix_unitaire.min' => 'Le prix ne peut pas être négatif.',
            
            'articles.*.taille.max' => 'La taille/pointure ne peut pas dépasser 50 caractères.',
            'articles.*.couleur.max' => 'La couleur ne peut pas dépasser 50 caractères.',
            
            // Mesures
            'articles.*.mesures.*.numeric' => 'Les mesures doivent être des nombres.',
            'articles.*.mesures.*.min' => 'Les mesures ne peuvent pas être négatives.',
            'articles.*.mesures.*.max' => 'Mesure invalide (trop grande).',
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => $this->filled('client_id') ? (int) $this->input('client_id') : null,
            'mode_livraison' => $this->input('mode_livraison') === 'magasin' ? 'boutique' : $this->input('mode_livraison'),
            'est_cadeau' => $this->boolean('est_cadeau', false),
            'frais_livraison' => (float) ($this->input('frais_livraison') ?? 0),
            'remise' => (float) ($this->input('remise') ?? 0),
        ]);

        if (!$this->filled('client_id')) {
            $this->merge([
                'new_client' => array_filter([
                    'nom_complet' => $this->input('nom_destinataire'),
                    'telephone' => $this->input('telephone_livraison'),
                    'adresse' => $this->input('adresse_livraison'),
                    'ville' => $this->input('new_client.ville'),
                    'email' => $this->input('new_client.email'),
                ], fn ($value) => $value !== null && $value !== ''),
            ]);
        }

        // Nettoyer les articles
        if ($this->has('articles') && is_array($this->input('articles'))) {
            $articles = [];
            foreach ($this->input('articles') as $article) {
                if (isset($article['produit_id']) && isset($article['quantite']) && isset($article['prix_unitaire'])) {
                    $cleanedArticle = [
                        'produit_id' => (int) $article['produit_id'],
                        'quantite' => (int) $article['quantite'],
                        'prix_unitaire' => (float) $article['prix_unitaire'],
                        'taille' => $article['taille'] ?? null,
                        'couleur' => $article['couleur'] ?? null,
                        'instructions' => $article['instructions'] ?? null,
                        'utilise_mesures_client' => (bool) ($article['utilise_mesures_client'] ?? false)
                    ];
                    
                    // Nettoyer les mesures si présentes
                    if (isset($article['mesures']) && is_array($article['mesures'])) {
                        $mesures = [];
                        foreach ($article['mesures'] as $key => $value) {
                            if (is_numeric($value) && $value > 0) {
                                $mesures[$key] = (float) $value;
                            }
                        }
                        $cleanedArticle['mesures'] = $mesures;
                    }
                    
                    $articles[] = $cleanedArticle;
                }
            }
            $this->merge(['articles' => $articles]);
        }
    }

    /**
     * Validation personnalisée
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('articles')) {
                $sousTotal = 0;
                
                foreach ($this->input('articles') as $index => $article) {
                    // Calculer le sous-total
                    if (isset($article['quantite']) && isset($article['prix_unitaire'])) {
                        $sousTotal += $article['quantite'] * $article['prix_unitaire'];
                    }
                    
                    // Vérifier le stock disponible
                    if (isset($article['produit_id']) && isset($article['quantite'])) {
                        $produit = \App\Models\Produit::find($article['produit_id']);
                        if ($produit && $produit->gestion_stock) {
                            $stockDisponible = $produit->stock_disponible;
                            $couleur = $article['couleur'] ?? null;
                            $taille = $article['taille'] ?? null;
                            $stockMap = $produit->couleur_tailles_stock
                                ? json_decode($produit->couleur_tailles_stock, true)
                                : null;
                            if (is_array($stockMap)) {
                                if ($couleur && $taille) {
                                    $stockDisponible = $stockMap[$couleur][$taille] ?? 0;
                                } elseif ($couleur) {
                                    $stockDisponible = array_sum($stockMap[$couleur] ?? []);
                                } elseif ($taille) {
                                    $sum = 0;
                                    foreach ($stockMap as $sizes) {
                                        if (is_array($sizes)) {
                                            $sum += $sizes[$taille] ?? 0;
                                        }
                                    }
                                    $stockDisponible = $sum;
                                }
                            }
                            
                            // Si on modifie une commande existante, ajouter le stock de l'ancien article
                            if ($this->route('commande')) {
                                $commandeExistante = $this->route('commande');
                                $ancienArticle = $commandeExistante->articles_commandes()
                                    ->where('produit_id', $article['produit_id'])
                                    ->first();
                                if ($ancienArticle) {
                                    if (!empty($couleur) && !empty($taille)
                                        && $ancienArticle->couleur_choisie === $couleur
                                        && $ancienArticle->taille_choisie === $taille) {
                                        $stockDisponible += $ancienArticle->quantite;
                                    } elseif (empty($couleur) || empty($taille)) {
                                        $stockDisponible += $ancienArticle->quantite;
                                    }
                                }
                            }
                            
                            if ($stockDisponible < $article['quantite']) {
                                $validator->errors()->add(
                                    "articles.{$index}.quantite",
                                    "Stock insuffisant pour {$produit->nom}. Stock disponible: {$stockDisponible}"
                                );
                            }
                        }
                    }
                    
                    // Validation des mesures : ne peut pas avoir à la fois mesures client et mesures personnalisées
                    if (!empty($article['utilise_mesures_client']) && !empty($article['mesures'])) {
                        $validator->errors()->add(
                            "articles.{$index}",
                            "Vous ne pouvez pas utiliser à la fois les mesures du client et des mesures personnalisées."
                        );
                    }
                    
                    // Si taille standard sélectionnée, ne doit pas avoir de mesures
                    if (!empty($article['taille']) && (!empty($article['mesures']) || !empty($article['utilise_mesures_client']))) {
                        $validator->errors()->add(
                            "articles.{$index}",
                            "Si vous sélectionnez une taille standard, vous ne pouvez pas ajouter de mesures."
                        );
                    }
                }

                // Vérifier le total de la commande
                $fraisLivraison = (float) $this->input('frais_livraison', 0);
                $remise = (float) $this->input('remise', 0);
                $total = $sousTotal + $fraisLivraison - $remise;

                if ($total < 0) {
                    $validator->errors()->add('remise', 'La remise ne peut pas être supérieure au montant de la commande.');
                }

                if ($total < 100) {
                    $validator->errors()->add('articles', 'Le montant minimum d\'une commande est de 100 FCFA.');
                }
            }

            // Vérifier la cohérence cadeau/message
            if ($this->boolean('est_cadeau') && !$this->input('message_cadeau')) {
                $validator->errors()->add('message_cadeau', 'Un message cadeau est requis si la commande est un cadeau.');
            }

            // Vérifier que le client existe et est actif quand il est choisi.
            if ($this->input('client_id')) {
                $client = \App\Models\Client::find($this->input('client_id'));
                if (!$client) {
                    $validator->errors()->add('client_id', 'Le client sélectionné n\'existe pas.');
                }
            } elseif (!$this->input('telephone_livraison')) {
                $validator->errors()->add('telephone_livraison', 'Le téléphone est obligatoire pour une commande sans client existant.');
            }
        });
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
}
