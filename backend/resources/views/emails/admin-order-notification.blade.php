<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle commande confirmee</title>
    <style>
        body { margin: 0; padding: 24px 0; background: #f4f2ee; color: #1a1a1a; font-family: Arial, sans-serif; }
        .wrapper { width: 100%; }
        .card { max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #e6e2db; }
        .header { padding: 28px 32px; border-bottom: 3px solid #111111; text-align: left; }
        .brand { font-size: 22px; letter-spacing: 4px; text-transform: uppercase; margin: 0; }
        .subtitle { margin: 6px 0 0; font-size: 11px; color: #777777; letter-spacing: 2px; text-transform: uppercase; }
        .content { padding: 28px 32px; }
        h1 { font-size: 18px; margin: 0 0 6px; text-transform: uppercase; letter-spacing: 1px; }
        h2 { font-size: 13px; margin: 22px 0 8px; text-transform: uppercase; letter-spacing: 1px; color: #444444; }
        .muted { color: #666666; font-size: 12px; }
        .pill { display: inline-block; padding: 6px 10px; background: #f1ede7; border: 1px solid #e1dbd3; font-size: 12px; letter-spacing: 1px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666666; border-bottom: 1px solid #eee8e0; padding: 10px 0; }
        .grid td { font-size: 13px; border-bottom: 1px solid #f2eee8; padding: 12px 0; }
        .summary td { padding: 8px 0; font-size: 13px; }
        .summary .total { font-weight: bold; border-top: 1px dashed #111111; padding-top: 12px; }
        .footer { padding: 18px 32px 26px; border-top: 1px solid #f1ece5; font-size: 11px; color: #888888; }
        @media only screen and (max-width: 600px) {
            .content, .header, .footer { padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <p class="brand">NDEYA</p>
                <p class="subtitle">Nouvelle commande confirmee</p>
            </div>
            <div class="content">
                <h1>Commande confirmee</h1>
                <p class="muted">Commande No {{ $commande->numero_commande }} | {{ $commande->created_at->format('d/m/Y H:i') }}</p>
                <p class="pill">Statut: {{ $commande->statut }}</p>

                <h2>Client</h2>
                <p>
                    {{ $client->prenom }} {{ $client->nom }}<br>
                    {{ $client->email }}<br>
                    {{ $client->telephone }}
                </p>

                <h2>Livraison</h2>
                <p>
                    {{ $commande->adresse_livraison }}<br>
                    {{ $commande->telephone_livraison }}
                </p>

                <h2>Articles</h2>
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Qte</th>
                            <th>Prix</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($commande->articles_commandes as $article)
                            <tr>
                                <td>{{ $article->nom_produit ?? ($article->produit->nom ?? 'Produit') }}</td>
                                <td>{{ $article->quantite }}</td>
                                <td>{{ number_format($article->prix_unitaire, 0, ',', ' ') }} FCFA</td>
                                <td>{{ number_format($article->prix_total_article, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h2>Resume</h2>
                <table class="summary">
                    <tbody>
                        <tr>
                            <td>Sous-total</td>
                            <td>{{ number_format($commande->montant_total - $commande->frais_livraison, 0, ',', ' ') }} FCFA</td>
                        </tr>
                        <tr>
                            <td>Livraison</td>
                            <td>{{ number_format($commande->frais_livraison, 0, ',', ' ') }} FCFA</td>
                        </tr>
                        <tr>
                            <td class="total">Total</td>
                            <td class="total">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="footer">
                Message automatique NDEYA SHOP - Admin notification
            </div>
        </div>
    </div>
</body>
</html>
