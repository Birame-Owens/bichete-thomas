<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Commande - NDEYA</title>
    <style>
        /* Reset CSS */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #FDFBF7; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        
        /* Typography */
        body, td, th {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1A1A1A;
        }
        
        /* Utilities */
        .uppercase { text-transform: uppercase; letter-spacing: 2px; }
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .border-top { border-top: 1px solid #E5E5E5; }
        .border-bottom { border-bottom: 1px solid #E5E5E5; }
        
        /* Mobile */
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; padding: 20px !important; }
            .columns { display: block !important; width: 100% !important; }
            .mobile-center { text-align: center !important; }
            .mobile-padding { padding-top: 20px !important; }
        }
    </style>
</head>
<body style="background-color: #FDFBF7; margin: 0; padding: 40px 0;">

    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="600" class="wrapper" style="background-color: #FFFFFF; max-width: 600px; width: 100%;">
                    
                    <tr>
                        <td align="center" style="padding: 40px 0 20px 0; border-bottom: 4px solid #B76E4D;">
                            <h1 style="margin: 0; font-size: 28px; letter-spacing: 6px; text-transform: uppercase; color: #B76E4D;">NDEYA</h1>
                            <p style="margin: 10px 0 0 0; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #999999;">L'Élégance Intemporelle</p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 40px 40px 20px 40px;">
                            <p style="font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #666666; margin-bottom: 10px;">Merci {{ $client->prenom }}</p>
                            <h2 style="font-size: 24px; font-weight: 300; margin: 0 0 20px 0; text-transform: uppercase; letter-spacing: 1px;">Commande Confirmée</h2>
                            <p style="font-size: 14px; line-height: 24px; color: #444444; max-width: 400px; margin: 0 auto;">
                                Nous préparons votre commande avec le plus grand soin. Vous recevrez un email dès son expédition.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 0 40px 40px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F9F9F9; padding: 15px;">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                                            N° {{ $commande->numero_commande }} <span style="color: #CCCCCC;">|</span> {{ $commande->created_at->format('d/m/Y') }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding-bottom: 15px; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid #E5E5E5;">Produit</th>
                                        <th align="right" style="padding-bottom: 15px; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid #E5E5E5;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($commande->articles_commandes as $article)
                                    <tr>
                                        <td align="left" style="padding: 15px 0; border-bottom: 1px solid #F5F5F5;">
                                            <p style="margin: 0; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">{{ $article->nom_produit }}</p>
                                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #888888;">
                                                Qté: {{ $article->quantite }}
                                                @if($article->taille_choisie) | Taille: {{ $article->taille_choisie }} @endif
                                                @if($article->couleur_choisie) | Couleur: {{ $article->couleur_choisie }} @endif
                                            </p>
                                        </td>
                                        <td align="right" style="padding: 15px 0; border-bottom: 1px solid #F5F5F5; font-size: 14px;">
                                            {{ number_format($article->prix_unitaire * $article->quantite, 0, ',', ' ') }} FCFA
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="right" style="padding-top: 15px; font-size: 12px; color: #666666; text-transform: uppercase; letter-spacing: 1px;">Sous-total</td>
                                    <td align="right" style="padding-top: 15px; font-size: 12px; width: 120px;">{{ number_format($commande->montant_total - $commande->frais_livraison, 0, ',', ' ') }} FCFA</td>
                                </tr>
                                
                                @if($commande->remise > 0)
                                <tr>
                                    <td align="right" style="padding-top: 10px; font-size: 12px; color: #10b981; text-transform: uppercase; letter-spacing: 1px;">Remise</td>
                                    <td align="right" style="padding-top: 10px; font-size: 12px; color: #10b981;">- {{ number_format($commande->remise, 0, ',', ' ') }} FCFA</td>
                                </tr>
                                @endif

                                <tr>
                                    <td align="right" style="padding-top: 10px; font-size: 12px; color: #666666; text-transform: uppercase; letter-spacing: 1px;">Livraison</td>
                                    <td align="right" style="padding-top: 10px; font-size: 12px;">{{ $commande->frais_livraison > 0 ? number_format($commande->frais_livraison, 0, ',', ' ') . ' FCFA' : 'OFFERTE' }}</td>
                                </tr>

                                <tr>
                                    <td colspan="2" style="padding-top: 15px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top: 1px dashed #B76E4D;">
                                            <tr>
                                                <td align="right" style="padding-top: 15px; font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px;">Total</td>
                                                <td align="right" style="padding-top: 15px; font-size: 16px; font-weight: bold; width: 120px;">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($isNewAccount && $temporaryPassword)
                    {{-- Section Compte Créé --}}
                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #FFF9E6; padding: 30px; border-left: 4px solid #F59E0B;">
                                <tr>
                                    <td align="center">
                                        <h3 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: #B45309;">🎉 Votre Compte Est Créé !</h3>
                                        <p style="margin: 0 0 20px 0; font-size: 13px; line-height: 20px; color: #4A5568;">
                                            Nous avons automatiquement créé un compte pour vous afin de faciliter vos prochains achats et le suivi de vos commandes.
                                        </p>
                                        
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #FFFFFF; padding: 20px; margin-bottom: 20px;">
                                            <tr>
                                                <td>
                                                    <p style="margin: 0 0 10px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888888;">Identifiant</p>
                                                    <p style="margin: 0 0 15px 0; font-size: 14px; font-weight: bold; color: #1A1A1A;">{{ $client->email }}</p>
                                                    
                                                    <p style="margin: 0 0 10px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888888;">Mot de Passe Temporaire</p>
                                                    <p style="margin: 0; font-size: 18px; font-weight: bold; font-family: 'Courier New', monospace; letter-spacing: 2px; color: #10b981; background-color: #F0FDF4; padding: 10px; text-align: center; border: 1px dashed #10b981;">{{ $temporaryPassword }}</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="margin: 0 0 15px 0; font-size: 12px; color: #DC2626; font-weight: bold;">
                                            ⚠️ Changez ce mot de passe dès votre première connexion
                                        </p>

                                        <a href="{{ config('services.frontend_url') }}/login" style="background-color: #10b981; color: #FFFFFF; display: inline-block; padding: 12px 30px; text-decoration: none; text-transform: uppercase; font-size: 11px; letter-spacing: 2px; font-weight: bold;">
                                            Se Connecter Maintenant
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td style="padding: 0 40px 40px 40px; background-color: #FDFBF7;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td valign="top" class="columns" width="50%" style="padding-top: 30px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Livraison</h3>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #1A1A1A;">
                                            {{ $commande->adresse_livraison }}<br>
                                            {{ $commande->ville_livraison }}<br>
                                            {{ $commande->telephone_livraison }}
                                        </p>
                                    </td>
                                    <td valign="top" class="columns" width="50%" style="padding-top: 30px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Support</h3>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #1A1A1A;">
                                            <a href="mailto:contact@nd-world.site" style="color: #1A1A1A; text-decoration: none; border-bottom: 1px solid #CCCCCC;">contact@nd-world.site</a><br>
                                            +221 76 592 34 02
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 40px;">
                            <a href="{{ config('services.frontend_url') }}/account/orders/{{ $commande->numero_commande }}" style="background-color: #B76E4D; color: #FFFFFF; display: inline-block; padding: 16px 40px; text-decoration: none; text-transform: uppercase; font-size: 12px; letter-spacing: 2px; font-weight: bold; border-radius: 0;">
                                Suivre ma commande
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 20px 40px 40px 40px; border-top: 1px solid #F0F0F0;">
                            <p style="margin: 0 0 15px 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px;">NDEYA SHOP</p>
                            
                            <p style="margin: 0 0 15px 0;">
                                <a href="{{ config('app.instagram_url') }}" style="color: #999999; text-decoration: none; font-size: 11px; margin: 0 10px; text-transform: uppercase; letter-spacing: 1px;">Instagram</a>
                                <a href="{{ config('app.tiktok_url') }}" style="color: #999999; text-decoration: none; font-size: 11px; margin: 0 10px; text-transform: uppercase; letter-spacing: 1px;">TikTok</a>
                                <a href="{{ config('app.facebook_url') }}" style="color: #999999; text-decoration: none; font-size: 11px; margin: 0 10px; text-transform: uppercase; letter-spacing: 1px;">Facebook</a>
                            </p>

                            <p style="margin: 0; font-size: 10px; color: #CCCCCC; text-transform: uppercase; letter-spacing: 1px;">
                                © {{ date('Y') }} NDEYA SHOP. Dakar, Sénégal.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>