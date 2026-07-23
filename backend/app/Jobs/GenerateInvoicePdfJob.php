<?php

namespace App\Jobs;

use App\Models\Commande;
use App\Models\Facture;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 60;

    protected $commande;

    /**
     * Create a new job instance.
     */
    public function __construct(Commande $commande)
    {
        $this->commande = $commande;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $commande = $this->commande->load(['client', 'articles.produit', 'paiements']);

            // Vérifier si facture existe déjà
            $facture = Facture::where('commande_id', $commande->id)->first();

            if (!$facture) {
                // Calculer les montants
                $tauxTva = 0.18; // 18%
                $montantHT = $commande->montant_total / (1 + $tauxTva);
                $montantTVA = $commande->montant_total - $montantHT;
                $fraisLivraison = $commande->frais_livraison ?? 0;
                $montantRemise = $commande->montant_remise ?? 0;
                $sousTotal = $montantHT - $fraisLivraison;

                // Créer l'enregistrement facture avec TOUS les champs requis
                $facture = Facture::create([
                    // IDs et références
                    'commande_id' => $commande->id,
                    'client_id' => $commande->client_id,
                    'numero_facture' => $this->generateInvoiceNumber(),
                    'numero_commande_ref' => $commande->numero_commande,
                    'type_facture' => 'facture',
                    
                    // Informations client
                    'client_nom' => $commande->client->nom ?? 'Client',
                    'client_prenom' => $commande->client->prenom ?? '',
                    'client_telephone' => $commande->client->telephone ?? $commande->telephone,
                    'client_email' => $commande->client->email,
                    'client_adresse_complete' => $commande->adresse_livraison,
                    'client_ville' => $commande->ville ?? 'Dakar',
                    
                    // Informations boutique
                    'boutique_nom' => config('app.name', 'NDEYA SHOP'),
                    'boutique_slogan' => 'Votre boutique en ligne de confiance',
                    'boutique_adresse' => 'Dakar, Sénégal',
                    'boutique_telephone' => '+221 XX XXX XX XX',
                    'boutique_email' => config('mail.from.address', 'diopbirame8@gmail.com'),
                    'boutique_site_web' => config('app.url', 'https://ndeya-shop.com'),
                    
                    // Montants financiers
                    'sous_total_ht' => round($sousTotal, 2),
                    'montant_remise' => round($montantRemise, 2),
                    'pourcentage_remise' => 0,
                    'frais_livraison' => round($fraisLivraison, 2),
                    'montant_tva' => round($montantTVA, 2),
                    'taux_tva' => $tauxTva,
                    'autres_frais' => 0,
                    'montant_total_ht' => round($montantHT, 2),
                    'montant_total_ttc' => round($commande->montant_total, 2),
                    
                    // Articles (JSON des articles de la commande)
                    'articles_facture' => json_encode($commande->articles->map(function($article) {
                        return [
                            'nom' => $article->produit->nom ?? 'Produit',
                            'quantite' => $article->quantite,
                            'prix_unitaire' => $article->prix_unitaire,
                            'total' => $article->prix_total,
                        ];
                    })),
                    
                    // Dates
                    'date_emission' => now(),
                    'date_paiement_complet' => now(),
                    
                    // Statuts et paiement
                    'statut' => 'payee_totalement',
                    'montant_paye' => round($commande->montant_total, 2),
                    'montant_restant_du' => 0,
                    
                    // Flags
                    'pdf_genere' => false,
                    'template_utilise' => 'default',
                    'langue_facture' => 'fr',
                ]);
            }

            // Générer le PDF
            $pdf = Pdf::loadView('pdfs.invoice', [
                'facture' => $facture,
                'commande' => $commande,
                'client' => $commande->client,
            ]);

            // Enregistrer le fichier
            $filename = "factures/facture-{$facture->numero_facture}.pdf";
            Storage::disk('public')->put($filename, $pdf->output());

            // Mettre à jour le chemin du fichier et marquer comme généré
            $facture->update([
                'chemin_pdf' => $filename,
                'nom_fichier_pdf' => basename($filename),
                'pdf_genere' => true,
                'taille_fichier_octets' => strlen($pdf->output()),
            ]);

            Log::info('Facture PDF générée', [
                'facture_id' => $facture->id,
                'numero' => $facture->numero_facture,
                'path' => $filename,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur génération facture PDF', [
                'commande_id' => $this->commande->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release(60);
            }
        }
    }

    /**
     * Générer numéro de facture unique
     */
    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        // Compter les factures du mois
        $count = Facture::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('FAC-%s%s-%04d', $year, $month, $count);
    }
}
