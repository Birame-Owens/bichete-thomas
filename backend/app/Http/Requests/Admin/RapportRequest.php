<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RapportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Période
            'periode' => [
                'nullable',
                'string',
                'in:7_jours,30_jours,mois_actuel,mois_precedent,trimestre_actuel,annee_actuelle,personnalise'
            ],
            
            // Dates personnalisées
            'date_debut' => [
                'nullable',
                'date',
                'before_or_equal:date_fin',
                'before_or_equal:today'
            ],
            'date_fin' => [
                'nullable',
                'date',
                'after_or_equal:date_debut',
                'before_or_equal:today'
            ],
            
            // Groupement pour les ventes
            'group_by' => [
                'nullable',
                'string',
                'in:day,week,month,year'
            ],
            
            // Limite pour les top produits/clients
            'limit' => [
                'nullable',
                'integer',
                'min:5',
                'max:100'
            ],
            
            // Filtres optionnels
            'categorie_id' => [
                'nullable',
                'integer',
                'exists:categories,id'
            ],
            'client_id' => [
                'nullable',
                'integer',
                'exists:clients,id'
            ],
            'statut_commande' => [
                'nullable',
                'string',
                'in:en_attente,confirmee,en_preparation,prete,livree,annulee'
            ],
            'methode_paiement' => [
                'nullable',
                'string',
                'in:especes,wave,orange_money,free_money,virement,cheque,carte'
            ],
            
            // Export
            'format_export' => [
                'nullable',
                'string',
                'in:excel,csv,pdf'
            ],
            'inclure_graphiques' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            // Période
            'periode.in' => 'La période sélectionnée n\'est pas valide.',
            
            // Dates
            'date_debut.date' => 'La date de début doit être une date valide.',
            'date_debut.before_or_equal' => 'La date de début ne peut pas être après la date de fin ou dans le futur.',
            'date_fin.date' => 'La date de fin doit être une date valide.',
            'date_fin.after_or_equal' => 'La date de fin ne peut pas être avant la date de début.',
            'date_fin.before_or_equal' => 'La date de fin ne peut pas être dans le futur.',
            
            // Groupement
            'group_by.in' => 'Le groupement doit être : day, week, month ou year.',
            
            // Limite
            'limit.integer' => 'La limite doit être un nombre entier.',
            'limit.min' => 'La limite minimum est de 5 éléments.',
            'limit.max' => 'La limite maximum est de 100 éléments.',
            
            // Filtres
            'categorie_id.exists' => 'La catégorie sélectionnée n\'existe pas.',
            'client_id.exists' => 'Le client sélectionné n\'existe pas.',
            'statut_commande.in' => 'Le statut de commande n\'est pas valide.',
            'methode_paiement.in' => 'La méthode de paiement n\'est pas valide.',
            
            // Export
            'format_export.in' => 'Le format d\'export doit être : excel, csv ou pdf.',
            'inclure_graphiques.boolean' => 'Le paramètre graphiques doit être true ou false.'
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        // Valeurs par défaut
        $this->merge([
            'periode' => $this->input('periode', '30_jours'),
            'group_by' => $this->input('group_by', 'day'),
            'limit' => (int) ($this->input('limit') ?? 20),
            'inclure_graphiques' => $this->boolean('inclure_graphiques', true)
        ]);

        // Si période personnalisée mais pas de dates, utiliser les 30 derniers jours
        if ($this->input('periode') === 'personnalise') {
            if (!$this->input('date_debut') || !$this->input('date_fin')) {
                $this->merge([
                    'date_debut' => now()->subDays(30)->format('Y-m-d'),
                    'date_fin' => now()->format('Y-m-d')
                ]);
            }
        }
    }

    /**
     * Validation personnalisée
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Vérifier que les dates sont dans une plage raisonnable
            if ($this->input('date_debut') && $this->input('date_fin')) {
                $debut = \Carbon\Carbon::parse($this->input('date_debut'));
                $fin = \Carbon\Carbon::parse($this->input('date_fin'));
                
                // Maximum 2 ans d'écart
                if ($debut->diffInDays($fin) > 730) {
                    $validator->errors()->add('date_fin', 'La période ne peut pas dépasser 2 ans.');
                }
                
                // Pas plus de 5 ans dans le passé
                if ($debut->isBefore(now()->subYears(5))) {
                    $validator->errors()->add('date_debut', 'La date ne peut pas remonter à plus de 5 ans.');
                }
            }

            // Vérifier la cohérence du groupement avec la période
            if ($this->input('group_by') === 'year') {
                $debut = $this->input('date_debut') ? \Carbon\Carbon::parse($this->input('date_debut')) : now()->subDays(30);
                $fin = $this->input('date_fin') ? \Carbon\Carbon::parse($this->input('date_fin')) : now();
                
                if ($debut->diffInMonths($fin) < 12) {
                    $validator->errors()->add('group_by', 'Le groupement par année nécessite une période d\'au moins 12 mois.');
                }
            }

            // Validation spécifique selon le type de rapport
            if ($this->input('type') === 'produits' && $this->input('limit') > 50) {
                $validator->errors()->add('limit', 'Pour le rapport produits, la limite maximum est de 50.');
            }

            if ($this->input('type') === 'clients' && $this->input('limit') > 30) {
                $validator->errors()->add('limit', 'Pour le rapport clients, la limite maximum est de 30.');
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
                    'message' => 'Erreurs de validation dans les paramètres du rapport',
                    'errors' => $validator->errors(),
                    'suggestions' => $this->getSuggestions($validator->errors())
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Suggestions pour corriger les erreurs
     */
    private function getSuggestions($errors): array
    {
        $suggestions = [];

        if (isset($errors['periode'])) {
            $suggestions[] = 'Utilisez une période prédéfinie comme "30_jours" ou "mois_actuel".';
        }

        if (isset($errors['date_debut']) || isset($errors['date_fin'])) {
            $suggestions[] = 'Vérifiez que les dates sont au format YYYY-MM-DD et que la date de début est antérieure à la date de fin.';
        }

        if (isset($errors['limit'])) {
            $suggestions[] = 'La limite doit être entre 5 et 100 éléments selon le type de rapport.';
        }

        if (isset($errors['group_by'])) {
            $suggestions[] = 'Pour un groupement par année, sélectionnez une période d\'au moins 12 mois.';
        }

        return $suggestions;
    }

    /**
     * Obtenir les dates formatées pour la requête
     */
    public function getFormattedDates(): array
    {
        $periode = $this->input('periode', '30_jours');
        
        if ($periode === 'personnalise') {
            return [
                'debut' => $this->input('date_debut'),
                'fin' => $this->input('date_fin')
            ];
        }

        return $this->getPredefinedPeriod($periode);
    }

    /**
     * Obtenir les dates d'une période prédéfinie
     */
    private function getPredefinedPeriod(string $periode): array
    {
        return match($periode) {
            '7_jours' => [
                'debut' => now()->subDays(7)->format('Y-m-d'),
                'fin' => now()->format('Y-m-d')
            ],
            '30_jours' => [
                'debut' => now()->subDays(30)->format('Y-m-d'),
                'fin' => now()->format('Y-m-d')
            ],
            'mois_actuel' => [
                'debut' => now()->startOfMonth()->format('Y-m-d'),
                'fin' => now()->endOfMonth()->format('Y-m-d')
            ],
            'mois_precedent' => [
                'debut' => now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'fin' => now()->subMonth()->endOfMonth()->format('Y-m-d')
            ],
            'trimestre_actuel' => [
                'debut' => now()->startOfQuarter()->format('Y-m-d'),
                'fin' => now()->endOfQuarter()->format('Y-m-d')
            ],
            'annee_actuelle' => [
                'debut' => now()->startOfYear()->format('Y-m-d'),
                'fin' => now()->endOfYear()->format('Y-m-d')
            ],
            default => [
                'debut' => now()->subDays(30)->format('Y-m-d'),
                'fin' => now()->format('Y-m-d')
            ]
        };
    }
}