<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h1 { font-size: 18px; color: #5b21b6; margin-bottom: 4px; }
    h2 { font-size: 13px; color: #6d28d9; margin: 16px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
    .meta { font-size: 10px; color: #9ca3af; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    th { background: #5b21b6; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
    td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #f9fafb; }
    .kpi-grid { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
    .kpi { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 10px 14px; min-width: 140px; }
    .kpi-label { font-size: 9px; text-transform: uppercase; color: #7c3aed; }
    .kpi-value { font-size: 16px; font-weight: bold; color: #4c1d95; margin-top: 2px; }
</style>
</head>
<body>
<h1>
@switch($type)
    @case('ventes') Rapport des Ventes @break
    @case('produits') Rapport des Produits @break
    @case('clients') Rapport des Clients @break
    @case('financier') Rapport Financier @break
    @case('commandes') Rapport des Commandes @break
    @case('performance-produits') Performance Produits @break
    @case('analytics') Analytics Web @break
    @default Rapport
@endswitch
</h1>
<p class="meta">Généré le {{ now()->format('d/m/Y à H:i') }} — NDEYA SHOP</p>

@if($type === 'ventes')
    @if(!empty($data['totaux']))
    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">CA Total</div><div class="kpi-value">{{ number_format($data['totaux']['total_ca'] ?? 0, 0, ',', ' ') }} FCFA</div></div>
        <div class="kpi"><div class="kpi-label">Commandes</div><div class="kpi-value">{{ $data['totaux']['nombre_commandes'] ?? 0 }}</div></div>
        <div class="kpi"><div class="kpi-label">Panier moyen</div><div class="kpi-value">{{ number_format($data['totaux']['panier_moyen'] ?? 0, 0, ',', ' ') }} FCFA</div></div>
    </div>
    @endif
    <h2>Détail des ventes</h2>
    <table>
        <thead><tr><th>Période</th><th>Commandes</th><th>CA</th><th>Panier moyen</th></tr></thead>
        <tbody>
        @foreach($data['ventes'] ?? [] as $v)
            <tr><td>{{ $v->periode ?? '' }}</td><td>{{ $v->nombre_commandes ?? 0 }}</td><td>{{ number_format($v->chiffre_affaires ?? 0, 0, ',', ' ') }} FCFA</td><td>{{ number_format($v->panier_moyen ?? 0, 0, ',', ' ') }} FCFA</td></tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'produits')
    <h2>Top Produits vendus</h2>
    <table>
        <thead><tr><th>Produit</th><th>Catégorie</th><th>Qté vendue</th><th>CA</th></tr></thead>
        <tbody>
        @foreach($data['produits'] ?? [] as $p)
            <tr><td>{{ $p->nom ?? '' }}</td><td>{{ $p->categorie ?? '' }}</td><td>{{ $p->total_vendu ?? 0 }}</td><td>{{ number_format($p->chiffre_affaires ?? 0, 0, ',', ' ') }} FCFA</td></tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'clients')
    <h2>Top Clients</h2>
    <table>
        <thead><tr><th>Nom</th><th>Téléphone</th><th>Ville</th><th>Commandes</th><th>Total dépensé</th></tr></thead>
        <tbody>
        @foreach($data['top_clients'] ?? [] as $c)
            <tr><td>{{ ($c->nom ?? '') . ' ' . ($c->prenom ?? '') }}</td><td>{{ $c->telephone ?? '' }}</td><td>{{ $c->ville ?? '' }}</td><td>{{ $c->nombre_commandes ?? 0 }}</td><td>{{ number_format($c->total_depense ?? 0, 0, ',', ' ') }} FCFA</td></tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'financier')
    @if(!empty($data['resume']))
    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">CA Total</div><div class="kpi-value">{{ number_format($data['resume']['total_ca'] ?? 0, 0, ',', ' ') }} FCFA</div></div>
        <div class="kpi"><div class="kpi-label">Paiements validés</div><div class="kpi-value">{{ $data['resume']['nombre_paiements'] ?? 0 }}</div></div>
    </div>
    @endif
    <h2>Évolution quotidienne</h2>
    <table>
        <thead><tr><th>Date</th><th>Méthode</th><th>Paiements</th><th>Montant</th></tr></thead>
        <tbody>
        @foreach($data['evolution_quotidienne'] ?? [] as $e)
            <tr><td>{{ $e->date ?? '' }}</td><td>{{ $e->methode_paiement ?? '' }}</td><td>{{ $e->nombre_paiements ?? 0 }}</td><td>{{ number_format($e->chiffre_affaires ?? 0, 0, ',', ' ') }} FCFA</td></tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'commandes')
    <h2>Évolution des commandes</h2>
    <table>
        <thead><tr><th>Date</th><th>Statut</th><th>Commandes</th><th>Montant total</th></tr></thead>
        <tbody>
        @foreach($data['evolution_quotidienne'] ?? [] as $e)
            <tr><td>{{ $e->date ?? '' }}</td><td>{{ $e->statut ?? '' }}</td><td>{{ $e->nombre_commandes ?? 0 }}</td><td>{{ number_format($e->montant_total ?? 0, 0, ',', ' ') }} FCFA</td></tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'performance-produits')
    @if(!empty($data['resume']))
    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">Total vues</div><div class="kpi-value">{{ $data['resume']['total_vues'] ?? 0 }}</div></div>
        <div class="kpi"><div class="kpi-label">Total vendus</div><div class="kpi-value">{{ $data['resume']['total_vendus'] ?? 0 }}</div></div>
        <div class="kpi"><div class="kpi-label">Taux conversion</div><div class="kpi-value">{{ number_format($data['resume']['taux_conversion_global'] ?? 0, 1, ',', ' ') }} %</div></div>
    </div>
    @endif
    <h2>Performance par produit</h2>
    <table>
        <thead><tr><th>Produit</th><th>Catégorie</th><th>Vues</th><th>Vendus</th><th>CA</th><th>Conversion</th></tr></thead>
        <tbody>
        @foreach($data['produits'] ?? [] as $p)
            <tr>
                <td>{{ $p->nom ?? '' }}</td>
                <td>{{ $p->categorie ?? '' }}</td>
                <td>{{ $p->nombre_vues ?? 0 }}</td>
                <td>{{ $p->total_vendu ?? 0 }}</td>
                <td>{{ number_format($p->chiffre_affaires ?? 0, 0, ',', ' ') }} FCFA</td>
                <td>{{ number_format($p->taux_conversion ?? 0, 1, ',', ' ') }} %</td>
            </tr>
        @endforeach
        </tbody>
    </table>

@elseif($type === 'analytics')
    <h2>Données analytics</h2>
    <table>
        <thead><tr><th>Produit</th><th>Vues</th><th>Ajouts panier</th><th>Commandes</th></tr></thead>
        <tbody>
        @foreach($data['produits'] ?? [] as $p)
            <tr>
                <td>{{ $p->nom ?? '' }}</td>
                <td>{{ $p->nombre_vues ?? 0 }}</td>
                <td>{{ $p->nombre_paniers ?? 0 }}</td>
                <td>{{ $p->nombre_commandes ?? 0 }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
