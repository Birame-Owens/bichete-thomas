<?php

namespace App\Services\Admin;

use App\Models\Promotion;
use App\Models\Commande;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PromotionService
{
    /**
     * Créer une nouvelle promotion
     */
    public function createPromotion(array $data): Promotion
    {
        DB::beginTransaction();

        try {
            // Générer un code unique si non fourni
            if (empty($data['code'])) {
                $data['code'] = $this->generateUniqueCode($data['nom']);
            }

            // Gérer l'upload d'image
            if (isset($data['image']) && $data['image']) {
                $data['image'] = $this->storeImage($data['image']);
            }

            // Initialiser les statistiques
            $data['nombre_utilisations'] = 0;
            $data['chiffre_affaires_genere'] = 0;
            $data['nombre_commandes'] = 0;

            // Valider les dates
            $this->validateDates($data);

            // Encoder les tableaux JSON (toujours présents grâce à prepareForValidation)
            // Vide = null (la promo s'applique à tout), non-vide = JSON d'entiers
            $data['categories_eligibles'] = !empty($data['categories_eligibles'])
                ? json_encode(array_values(array_map('intval', $data['categories_eligibles'])))
                : null;

            $data['produits_eligibles'] = !empty($data['produits_eligibles'])
                ? json_encode(array_values(array_map('intval', $data['produits_eligibles'])))
                : null;

            $data['jours_semaine_valides'] = !empty($data['jours_semaine_valides'])
                ? json_encode(array_values(array_map('intval', $data['jours_semaine_valides'])))
                : null;

            $promotion = Promotion::create($data);

            DB::commit();

            Log::info('Nouvelle promotion créée', [
                'promotion_id' => $promotion->id,
                'nom' => $promotion->nom,
                'code' => $promotion->code,
                'type' => $promotion->type_promotion,
                'admin_id' => auth()->id()
            ]);

            return $promotion;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création promotion', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Mettre à jour une promotion
     */
    public function updatePromotion(Promotion $promotion, array $data): Promotion
    {
        DB::beginTransaction();

        try {
            // Gérer l'upload d'image
            if (isset($data['image']) && $data['image']) {
                // Supprimer l'ancienne image
                if ($promotion->image) {
                    Storage::disk('public')->delete($promotion->image);
                }
                $data['image'] = $this->storeImage($data['image']);
            }

            // Valider les dates
            $this->validateDates($data);

            // Toujours mettre à jour ces champs (même si vide = suppression des restrictions)
            $data['categories_eligibles'] = !empty($data['categories_eligibles'])
                ? json_encode(array_values(array_map('intval', $data['categories_eligibles'])))
                : null;

            $data['produits_eligibles'] = !empty($data['produits_eligibles'])
                ? json_encode(array_values(array_map('intval', $data['produits_eligibles'])))
                : null;

            $data['jours_semaine_valides'] = !empty($data['jours_semaine_valides'])
                ? json_encode(array_values(array_map('intval', $data['jours_semaine_valides'])))
                : null;

            $promotion->update($data);

            DB::commit();

            Log::info('Promotion mise à jour', [
                'promotion_id' => $promotion->id,
                'nom' => $promotion->nom,
                'admin_id' => auth()->id()
            ]);

            return $promotion;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour promotion', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Supprimer une promotion
     */
    public function deletePromotion(Promotion $promotion): bool
    {
        try {
            // Vérifier si la promotion est utilisée
            if ($promotion->nombre_utilisations > 0) {
                throw new \Exception('Impossible de supprimer une promotion déjà utilisée');
            }

            // Supprimer l'image si elle existe
            if ($promotion->image) {
                Storage::disk('public')->delete($promotion->image);
            }

            $promotion->delete();

            Log::info('Promotion supprimée', [
                'promotion_id' => $promotion->id,
                'nom' => $promotion->nom,
                'admin_id' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Erreur suppression promotion', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Activer/Désactiver une promotion
     */
    public function toggleStatus(Promotion $promotion): bool
    {
        try {
            $newStatus = !$promotion->est_active;
            
            $promotion->update(['est_active' => $newStatus]);

            Log::info('Statut promotion modifié', [
                'promotion_id' => $promotion->id,
                'nouveau_statut' => $newStatus ? 'active' : 'inactive',
                'admin_id' => auth()->id()
            ]);

            return $newStatus;

        } catch (\Exception $e) {
            Log::error('Erreur changement statut promotion', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Appliquer une promotion à une commande
     */
    public function appliquerPromotion(string $code, Commande $commande, Client $client): array
    {
        DB::beginTransaction();

        try {
            $promotion = $this->validatePromotionCode($code, $commande, $client);
            
            $reduction = $this->calculerReduction($promotion, $commande);
            
            // Mettre à jour les statistiques
            $this->updatePromotionStats($promotion, $commande, $reduction);

            DB::commit();

            Log::info('Promotion appliquée', [
                'promotion_id' => $promotion->id,
                'code' => $code,
                'commande_id' => $commande->id,
                'client_id' => $client->id,
                'reduction' => $reduction
            ]);

            return [
                'success' => true,
                'promotion' => $promotion,
                'reduction' => $reduction,
                'message' => "Code promo '{$code}' appliqué avec succès"
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::warning('Échec application promotion', [
                'code' => $code,
                'commande_id' => $commande->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtenir les statistiques des promotions
     */
    public function getStatistics(): array
    {
        return [
            'total_promotions' => Promotion::count(),
            'promotions_actives' => Promotion::where('est_active', true)->count(),
            'promotions_expirees' => Promotion::where('date_fin', '<', now())->count(),
            'promotions_futures' => Promotion::where('date_debut', '>', now())->count(),
            'ca_genere_total' => Promotion::sum('chiffre_affaires_genere'),
            'utilisations_totales' => Promotion::sum('nombre_utilisations'),
            'promotion_plus_utilisee' => Promotion::orderBy('nombre_utilisations', 'desc')->first(),
            'promotion_plus_rentable' => Promotion::orderBy('chiffre_affaires_genere', 'desc')->first(),
            'promotions_par_type' => Promotion::select('type_promotion', DB::raw('count(*) as total'))
                ->groupBy('type_promotion')
                ->get()
                ->keyBy('type_promotion'),
            'utilisation_moyenne' => Promotion::avg('nombre_utilisations')
        ];
    }

    /**
     * Obtenir les promotions actives pour le site
     */
    public function getPromotionsActives(): array
    {
        return Promotion::where('est_active', true)
            ->where('afficher_site', true)
            ->where('date_debut', '<=', now())
            ->where('date_fin', '>=', now())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($promotion) {
                return $this->formatPromotionForSite($promotion);
            })
            ->toArray();
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Générer un code unique
     */
    private function generateUniqueCode(string $nom): string
    {
        $base = strtoupper(Str::slug(substr($nom, 0, 5)));
        $code = $base . rand(10, 99);
        
        // Vérifier l'unicité
        while (Promotion::where('code', $code)->exists()) {
            $code = $base . rand(10, 99);
        }
        
        return $code;
    }

    /**
     * Stocker l'image
     */
    private function storeImage($image): string
    {
        if (is_string($image)) {
            return $image; // Déjà un chemin
        }
        
        $filename = 'promotion_' . time() . '_' . Str::random(8) . '.' . $image->getClientOriginalExtension();
        return $image->storeAs('promotions', $filename, 'public');
    }

    /**
     * Valider les dates
     */
    private function validateDates(array $data): void
    {
        if (isset($data['date_debut']) && isset($data['date_fin'])) {
            $dateDebut = Carbon::parse($data['date_debut']);
            $dateFin = Carbon::parse($data['date_fin']);
            
            if ($dateFin->lte($dateDebut)) {
                throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
            }
        }
    }

    /**
     * Valider un code promo
     */
    private function validatePromotionCode(string $code, Commande $commande, Client $client): Promotion
    {
        $promotion = Promotion::where('code', $code)
            ->where('est_active', true)
            ->first();

        if (!$promotion) {
            throw new \Exception('Code promo invalide ou expiré');
        }

        // Vérifier les dates
        $now = now();
        if ($promotion->date_debut > $now || $promotion->date_fin < $now) {
            throw new \Exception('Code promo expiré');
        }

        // Vérifier les utilisations maximales
        if ($promotion->utilisation_maximum && 
            $promotion->nombre_utilisations >= $promotion->utilisation_maximum) {
            throw new \Exception('Code promo épuisé');
        }

        // Vérifier le montant minimum
        if ($promotion->montant_minimum && $commande->montant_total < $promotion->montant_minimum) {
            throw new \Exception("Montant minimum requis : {$promotion->montant_minimum} FCFA");
        }

        // Vérifier les jours de la semaine
        if ($promotion->jours_semaine_valides) {
            $joursValides = json_decode($promotion->jours_semaine_valides, true);
            if (!in_array($now->dayOfWeek, $joursValides)) {
                throw new \Exception('Code promo non valide aujourd\'hui');
            }
        }

        // Vérifier la cible client
        $this->validateClientEligibility($promotion, $client);

        return $promotion;
    }

    /**
     * Valider l'éligibilité du client
     */
    private function validateClientEligibility(Promotion $promotion, Client $client): void
    {
        switch ($promotion->cible_client) {
            case 'nouveaux':
                if ($client->commandes()->count() > 0) {
                    throw new \Exception('Code promo réservé aux nouveaux clients');
                }
                break;
            
            case 'vip':
                // Définir la logique VIP selon vos critères
                $totalAchats = $client->commandes()->sum('montant_total');
                if ($totalAchats < 100000) { // 100k FCFA par exemple
                    throw new \Exception('Code promo réservé aux clients VIP');
                }
                break;
            
            case 'inactifs':
                $derniereCommande = $client->commandes()->latest()->first();
                if (!$derniereCommande || $derniereCommande->created_at->diffInMonths(now()) < 3) {
                    throw new \Exception('Code promo réservé aux clients inactifs');
                }
                break;
        }

        // Vérifier première commande seulement
        if ($promotion->premiere_commande_seulement && $client->commandes()->count() > 0) {
            throw new \Exception('Code promo valide uniquement pour la première commande');
        }
    }

    /**
     * Calculer la réduction
     */
    private function calculerReduction(Promotion $promotion, Commande $commande): float
    {
        $montantBase = $commande->montant_total;
        $reduction = 0;

        switch ($promotion->type_promotion) {
            case 'pourcentage':
                $reduction = ($montantBase * $promotion->valeur) / 100;
                break;
            
            case 'montant_fixe':
                $reduction = min($promotion->valeur, $montantBase);
                break;
            
            case 'livraison_gratuite':
                $reduction = $commande->frais_livraison ?? 0;
                break;
        }

        // Appliquer la réduction maximum si définie
        if ($promotion->reduction_maximum) {
            $reduction = min($reduction, $promotion->reduction_maximum);
        }

        return $reduction;
    }

    /**
     * Mettre à jour les statistiques de la promotion
     */
    private function updatePromotionStats(Promotion $promotion, Commande $commande, float $reduction): void
    {
        $promotion->increment('nombre_utilisations');
        $promotion->increment('nombre_commandes');
        $promotion->increment('chiffre_affaires_genere', $commande->montant_total);
    }

    /**
     * Formater une promotion pour l'affichage sur le site
     */
    private function formatPromotionForSite(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'nom' => $promotion->nom,
            'description' => $promotion->description,
            'code' => $promotion->code,
            'type_promotion' => $promotion->type_promotion,
            'valeur' => $promotion->valeur,
            'image' => $promotion->image ? Storage::url($promotion->image) : null,
            'date_fin' => $promotion->date_fin->format('d/m/Y'),
            'couleur_affichage' => $promotion->couleur_affichage,
            'montant_minimum' => $promotion->montant_minimum,
            'jours_restants' => max(0, $promotion->date_fin->diffInDays(now()))
        ];
    }
}