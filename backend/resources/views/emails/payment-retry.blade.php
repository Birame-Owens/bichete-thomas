<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relance de Paiement - NDEYA</title>
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
                    
                    <!-- En-tête -->
                    <tr>
                        <td align="center" style="padding: 40px 0 20px 0; border-bottom: 4px solid #B76E4D;">
                            <h1 style="margin: 0; font-size: 28px; letter-spacing: 6px; text-transform: uppercase; color: #B76E4D;">NDEYA</h1>
                            <p style="margin: 10px 0 0 0; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #999999;">L'Élégance Intemporelle</p>
                        </td>
                    </tr>

                    <!-- Alerte paiement -->
                    <tr>
                        <td style="padding: 40px 40px 0 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #FFF3CD; border-left: 4px solid #FFC107; padding: 20px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-size: 14px; color: #856404;">
                                            <strong style="display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; font-size: 12px;">⚠️ Paiement non finalisé</strong>
                                            Le paiement de votre commande <strong>{{ $commande->numero_commande }}</strong> n'a pas pu être complété.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Message principal -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 20px 40px;">
                            <p style="font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #666666; margin-bottom: 10px;">Bonjour {{ $client->prenom }}</p>
                            <h2 style="font-size: 24px; font-weight: 300; margin: 0 0 20px 0; text-transform: uppercase; letter-spacing: 1px;">Finalisez Votre Paiement</h2>
                            <p style="font-size: 14px; line-height: 24px; color: #444444; max-width: 400px; margin: 0 auto;">
                                Votre commande est en attente. Cliquez sur le bouton ci-dessous pour finaliser votre paiement en toute sécurité.
                            </p>
                        </td>
                    </tr>

                    <!-- Bouton CTA -->
                    <tr>
                        <td align="center" style="padding: 0 40px 40px 40px;">
                            <a href="{{ $paymentUrl }}" style="background-color: #B76E4D; color: #FFFFFF; display: inline-block; padding: 16px 40px; text-decoration: none; text-transform: uppercase; font-size: 12px; letter-spacing: 2px; font-weight: bold; border-radius: 0;">
                                Finaliser mon paiement
                            </a>
                            <p style="margin: 15px 0 0 0; font-size: 10px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                Ce lien reste actif pendant 7 jours
                            </p>
                        </td>
                    </tr>

                    <!-- Détails commande -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F9F9F9; padding: 20px; border-top: 1px solid #E5E5E5; border-bottom: 1px solid #E5E5E5;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 5px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Commande</p>
                                        <p style="margin: 0 0 15px 0; font-size: 14px; font-weight: bold; letter-spacing: 1px;">{{ $commande->numero_commande }}</p>
                                        
                                        <p style="margin: 0 0 5px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Montant</p>
                                        <p style="margin: 0 0 15px 0; font-size: 18px; font-weight: bold;">{{ $montant }}</p>
                                        
                                        <p style="margin: 0 0 5px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Date</p>
                                        <p style="margin: 0; font-size: 13px; color: #444444;">{{ $commande->created_at->format('d/m/Y à H:i') }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Pourquoi ce message -->
                    <tr>
                        <td style="padding: 40px 40px 20px 40px;">
                            <h3 style="margin: 0 0 15px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Pourquoi ce message ?</h3>
                            <p style="margin: 0 0 10px 0; font-size: 13px; line-height: 22px; color: #666666;">
                                Le paiement peut avoir échoué pour plusieurs raisons :
                            </p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding: 8px 0; font-size: 13px; color: #666666;">• Connexion internet interrompue</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 13px; color: #666666;">• Fonds insuffisants sur le compte</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 13px; color: #666666;">• Session de paiement expirée</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; font-size: 13px; color: #666666;">• Carte bancaire refusée</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Support & Livraison -->
                    <tr>
                        <td style="padding: 0 40px 40px 40px; background-color: #FDFBF7;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td valign="top" class="columns" width="50%" style="padding-top: 30px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Livraison</h3>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #1A1A1A;">
                                            {{ $commande->adresse_livraison }}<br>
                                            {{ $commande->client->ville ?? 'Dakar' }}
                                        </p>
                                    </td>
                                    <td valign="top" class="columns" width="50%" style="padding-top: 30px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Support</h3>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #1A1A1A;">
                                            <a href="mailto:{{ config('mail.from.address') }}" style="color: #1A1A1A; text-decoration: none; border-bottom: 1px solid #CCCCCC;">{{ config('mail.from.address') }}</a><br>
                                            +221 76 592 34 02
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
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
