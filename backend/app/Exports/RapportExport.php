<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RapportExport implements FromArray, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected array $data;
    protected string $type;

    public function __construct(array $data, string $type)
    {
        $this->data = $data;
        $this->type = $type;
    }

    public function array(): array
    {
        return $this->formatDataForExport();
    }

    public function headings(): array
    {
        return match($this->type) {
            'ventes' => [
                'Période',
                'Nombre de commandes', 
                'Chiffre d\'affaires',
                'Panier moyen',
                'Clients uniques'
            ],
            'produits' => [
                'Produit',
                'Catégorie',
                'Quantité vendue',
                'Chiffre d\'affaires',
                'Prix moyen'
            ],
            'clients' => [
                'Nom',
                'Prénom',
                'Téléphone',
                'Ville',
                'Nombre de commandes',
                'Total dépensé',
                'Panier moyen'
            ],
            'financier' => [
                'Date',
                'Méthode de paiement',
                'Nombre de paiements',
                'Montant total'
            ],
            'commandes' => [
                'Date',
                'Statut',
                'Nombre de commandes',
                'Montant total',
                'Panier moyen'
            ],
            'performance-produits' => [
                'Produit',
                'Catégorie',
                'Vues',
                'Quantité vendue',
                'Chiffre d\'affaires',
                'Taux de conversion (%)'
            ],
            'analytics' => [
                'Produit',
                'Vues totales',
                'Vues uniques',
                'Panier',
                'Commandes'
            ],
            default => ['Données']
        };
    }

    public function map($row): array
    {
        return match($this->type) {
            'ventes' => [
                $this->getValue($row, 'periode'),
                $this->getValue($row, 'nombre_commandes', 0),
                number_format($this->getValue($row, 'chiffre_affaires', 0), 0, ',', ' ') . ' FCFA',
                number_format($this->getValue($row, 'panier_moyen', 0), 0, ',', ' ') . ' FCFA',
                $this->getValue($row, 'clients_uniques', 0)
            ],
            'produits' => [
                $this->getValue($row, 'nom'),
                $this->getValue($row, 'categorie'),
                $this->getValue($row, 'total_vendu', 0),
                number_format($this->getValue($row, 'chiffre_affaires', 0), 0, ',', ' ') . ' FCFA',
                number_format($this->getValue($row, 'prix_moyen', 0), 0, ',', ' ') . ' FCFA'
            ],
            'clients' => [
                $this->getValue($row, 'nom'),
                $this->getValue($row, 'prenom'),
                $this->getValue($row, 'telephone'),
                $this->getValue($row, 'ville'),
                $this->getValue($row, 'nombre_commandes', 0),
                number_format($this->getValue($row, 'total_depense', 0), 0, ',', ' ') . ' FCFA',
                number_format($this->getValue($row, 'panier_moyen', 0), 0, ',', ' ') . ' FCFA'
            ],
            'financier' => [
                $this->getValue($row, 'date'),
                $this->getValue($row, 'methode_paiement'),
                $this->getValue($row, 'nombre_paiements', 0),
                number_format($this->getValue($row, 'chiffre_affaires', 0), 0, ',', ' ') . ' FCFA'
            ],
            'commandes' => [
                $this->getValue($row, 'date'),
                $this->getValue($row, 'statut'),
                $this->getValue($row, 'nombre_commandes', 0),
                number_format($this->getValue($row, 'montant_total', 0), 0, ',', ' ') . ' FCFA',
                number_format($this->getValue($row, 'panier_moyen', 0), 0, ',', ' ') . ' FCFA'
            ],
            'performance-produits' => [
                $this->getValue($row, 'nom'),
                $this->getValue($row, 'categorie'),
                $this->getValue($row, 'nombre_vues', 0),
                $this->getValue($row, 'total_vendu', 0),
                number_format($this->getValue($row, 'chiffre_affaires', 0), 0, ',', ' ') . ' FCFA',
                number_format($this->getValue($row, 'taux_conversion', 0), 2, ',', ' ') . ' %'
            ],
            'analytics' => [
                $this->getValue($row, 'nom'),
                $this->getValue($row, 'nombre_vues', 0),
                $this->getValue($row, 'vues_uniques', 0),
                $this->getValue($row, 'nombre_paniers', 0),
                $this->getValue($row, 'nombre_commandes', 0)
            ],
            default => [is_array($row) || is_object($row) ? json_encode($row) : (string)$row]
        };
    }

    public function title(): string
    {
        return match($this->type) {
            'ventes' => 'Rapport des Ventes',
            'produits' => 'Rapport des Produits',
            'clients' => 'Rapport des Clients',
            'financier' => 'Rapport Financier',
            'commandes'            => 'Rapport des Commandes',
            'performance-produits' => 'Performance Produits',
            'analytics'            => 'Analytics Web',
            default => 'Rapport'
        };
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => '366092'],
                ],
            ],
        ];
    }

    private function formatDataForExport(): array
    {
        $data = match($this->type) {
            'ventes' => $this->data['ventes'] ?? [],
            'produits' => $this->data['produits'] ?? [],
            'clients' => $this->data['top_clients'] ?? [],
            'financier' => $this->data['evolution_quotidienne'] ?? [],
            'commandes'            => $this->data['evolution_quotidienne'] ?? [],
            'performance-produits' => $this->data['produits'] ?? [],
            'analytics'            => $this->data['produits'] ?? [],
            default => []
        };

        if (is_array($data)) {
            return array_map(function($item) {
                return is_object($item) ? (array) $item : $item;
            }, $data);
        }

        return [];
    }

    private function getValue($data, string $key, $default = '')
    {
        if (is_object($data)) {
            return $data->$key ?? $default;
        }
        
        if (is_array($data)) {
            return $data[$key] ?? $default;
        }
        
        return $default;
    }
}