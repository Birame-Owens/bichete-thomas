<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $facture->numero_facture }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e74c3c;
        }
        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        .slogan {
            font-size: 10px;
            color: #666;
            font-style: italic;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        .invoice-number {
            font-size: 14px;
            color: #666;
        }
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .info-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .info-box + .info-box {
            margin-left: 20px;
        }
        .info-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            color: #e74c3c;
            text-transform: uppercase;
        }
        .info-line {
            margin-bottom: 5px;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead {
            background-color: #e74c3c;
            color: white;
        }
        thead th {
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        tbody td {
            padding: 10px 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 11px;
        }
        tbody tr:last-child td {
            border-bottom: 2px solid #e74c3c;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .total-line.final {
            background-color: #e74c3c;
            color: white;
            font-weight: bold;
            font-size: 14px;
            padding: 12px 10px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e74c3c;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .payment-info {
            background: #fff9e6;
            padding: 15px;
            border-radius: 5px;
            margin-top: 30px;
            border-left: 4px solid #ffc107;
        }
        .payment-info strong {
            color: #e74c3c;
            display: block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo">{{ $facture->boutique_nom }}</div>
                <div class="slogan">{{ $facture->boutique_slogan }}</div>
                <div class="info-line" style="margin-top: 10px;">
                    <strong>Adresse:</strong> {{ $facture->boutique_adresse }}
                </div>
                <div class="info-line">
                    <strong>Téléphone:</strong> {{ $facture->boutique_telephone }}
                </div>
                <div class="info-line">
                    <strong>Email:</strong> {{ $facture->boutique_email }}
                </div>
                <div class="info-line">
                    <strong>Site web:</strong> {{ $facture->boutique_site_web }}
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">FACTURE</div>
                <div class="invoice-number">N° {{ $facture->numero_facture }}</div>
                <div style="margin-top: 15px; font-size: 11px;">
                    <div><strong>Date:</strong> {{ $facture->date_emission->format('d/m/Y') }}</div>
                    <div><strong>Commande:</strong> {{ $facture->numero_commande_ref }}</div>
                    <div><strong>Type:</strong> {{ strtoupper($facture->type_facture) }}</div>
                </div>
            </div>
        </div>

        <!-- Client Info -->
        <div class="info-section">
            <div class="info-box">
                <div class="info-title">Facturé à</div>
                <div class="info-line"><strong>{{ $facture->client_prenom }} {{ $facture->client_nom }}</strong></div>
                @if($facture->client_adresse_complete)
                    <div class="info-line">{{ $facture->client_adresse_complete }}</div>
                @endif
                <div class="info-line">{{ $facture->client_ville }}</div>
                @if($facture->client_telephone)
                    <div class="info-line">Tél: {{ $facture->client_telephone }}</div>
                @endif
                @if($facture->client_email)
                    <div class="info-line">Email: {{ $facture->client_email }}</div>
                @endif
            </div>
            <div class="info-box" style="margin-left: 20px;">
                <div class="info-title">Statut de paiement</div>
                <div class="info-line">
                    <strong>Statut:</strong> 
                    <span style="color: #28a745; font-weight: bold;">
                        {{ str_replace('_', ' ', strtoupper($facture->statut)) }}
                    </span>
                </div>
                @if($facture->date_paiement_complet)
                    <div class="info-line"><strong>Payé le:</strong> {{ $facture->date_paiement_complet->format('d/m/Y') }}</div>
                @endif
                <div class="info-line"><strong>Montant payé:</strong> {{ number_format($facture->montant_paye, 0, ',', ' ') }} FCFA</div>
                @if($facture->montant_restant_du > 0)
                    <div class="info-line" style="color: #e74c3c;"><strong>Reste dû:</strong> {{ number_format($facture->montant_restant_du, 0, ',', ' ') }} FCFA</div>
                @endif
            </div>
        </div>

        <!-- Articles Table -->
        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Article</th>
                    <th class="text-center" style="width: 15%;">Quantité</th>
                    <th class="text-right" style="width: 17.5%;">Prix Unitaire</th>
                    <th class="text-right" style="width: 17.5%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $articles = json_decode($facture->articles_facture, true) ?? [];
                @endphp
                @foreach($articles as $article)
                <tr>
                    <td>{{ $article['nom'] ?? 'Article' }}</td>
                    <td class="text-center">{{ $article['quantite'] ?? 0 }}</td>
                    <td class="text-right">{{ number_format($article['prix_unitaire'] ?? 0, 0, ',', ' ') }} FCFA</td>
                    <td class="text-right">{{ number_format($article['total'] ?? ($article['prix_unitaire'] * $article['quantite']), 0, ',', ' ') }} FCFA</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-line">
                <span>Sous-total HT:</span>
                <span>{{ number_format($facture->sous_total_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($facture->frais_livraison > 0)
            <div class="total-line">
                <span>Frais de livraison:</span>
                <span>{{ number_format($facture->frais_livraison, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            @if($facture->montant_remise > 0)
            <div class="total-line" style="color: #28a745;">
                <span>Remise ({{ $facture->pourcentage_remise }}%):</span>
                <span>- {{ number_format($facture->montant_remise, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="total-line">
                <span>Total HT:</span>
                <span>{{ number_format($facture->montant_total_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="total-line">
                <span>TVA ({{ $facture->taux_tva * 100 }}%):</span>
                <span>{{ number_format($facture->montant_tva, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="total-line final">
                <span>TOTAL TTC:</span>
                <span>{{ number_format($facture->montant_total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ $facture->boutique_nom }}</strong></p>
            <p>{{ $facture->boutique_adresse }} | Tél: {{ $facture->boutique_telephone }} | Email: {{ $facture->boutique_email }}</p>
            <p style="margin-top: 10px; font-size: 9px;">
                Document généré le {{ now()->format('d/m/Y à H:i') }} - Facture {{ $facture->numero_facture }}
            </p>
            @if($facture->mentions_legales)
                <p style="margin-top: 10px; font-size: 9px;">{{ $facture->mentions_legales }}</p>
            @endif
        </div>
    </div>
</body>
</html>
