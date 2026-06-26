<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue chez NDEYA</title>
    <style>
        /* Reset */
        body { margin: 0; padding: 0; width: 100% !important; background-color: #FDFBF7; }
        table { border-collapse: collapse !important; }
        
        /* Fonts */
        body, td, th, p, a {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1A1A1A;
        }
        
        /* Helpers */
        .uppercase { text-transform: uppercase; letter-spacing: 2px; }
        .button:hover { background-color: #9C5839 !important; }
    </style>
</head>
<body style="background-color: #FDFBF7; margin: 0; padding: 40px 0;">

    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #FFFFFF; max-width: 600px; width: 100%;">
                    
                    <tr>
                        <td align="center" style="padding: 40px 0 30px 0; border-bottom: 4px solid #B76E4D;">
                            <h1 style="margin: 0; font-size: 32px; letter-spacing: 8px; text-transform: uppercase; color: #B76E4D;">NDEYA</h1>
                            <p style="margin: 10px 0 0 0; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #999999;">L'expérience Client</p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 40px 40px 20px 40px;">
                            <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #999999; margin-bottom: 15px;">Bonjour {{ $client->prenom }}</p>
                            <h2 style="font-size: 24px; font-weight: 300; margin: 0 0 20px 0; text-transform: uppercase; letter-spacing: 1px; line-height: 1.4;">Merci pour votre commande</h2>
                            <p style="font-size: 14px; line-height: 24px; color: #444444; margin: 0 auto; max-width: 480px;">
                                Votre commande <strong>#{{ $commande->numero_commande }}</strong> a bien été enregistrée. Pour suivre son avancement et profiter pleinement de l'expérience NDEYA, nous vous invitons à finaliser votre compte.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 20px 40px 40px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #E5E5E5; background-color: #FAFAFA;">
                                <tr>
                                    <td align="center" style="padding: 40px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; font-weight: bold;">Membre Privilégié</h3>
                                        <p style="margin: 0 0 30px 0; font-size: 13px; color: #666666;">Activez votre compte pour accéder à vos avantages.</p>
                                        
                                        <a href="{{ $accountCreationUrl }}" style="background-color: #B76E4D; color: #FFFFFF; display: inline-block; padding: 18px 30px; text-decoration: none; text-transform: uppercase; font-size: 11px; letter-spacing: 2px; font-weight: bold; border-radius: 0;">
                                            Créer mon mot de passe
                                        </a>
                                        
                                        <p style="margin: 20px 0 0 0; font-size: 10px; color: #999999; font-style: italic;">
                                            Lien valide pendant 7 jours
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 30px;">
                                        <p style="font-size: 10px; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid #E5E5E5; display: inline-block; padding-bottom: 5px;">Vos privilèges</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td width="50%" valign="top" style="padding-bottom: 20px; padding-right: 10px;">
                                                    <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                                        <span style="color: #B76E4D;">01.</span> Suivi Réel
                                                    </p>
                                                    <p style="margin: 0; font-size: 12px; color: #666666; line-height: 18px;">
                                                        Localisez vos commandes en temps réel depuis votre espace.
                                                    </p>
                                                </td>
                                                <td width="50%" valign="top" style="padding-bottom: 20px; padding-left: 10px;">
                                                    <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                                        <span style="color: #B76E4D;">02.</span> Facturation
                                                    </p>
                                                    <p style="margin: 0; font-size: 12px; color: #666666; line-height: 18px;">
                                                        Accédez à votre historique et téléchargez vos factures PDF.
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="50%" valign="top" style="padding-bottom: 20px; padding-right: 10px;">
                                                    <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                                        <span style="color: #B76E4D;">03.</span> Express
                                                    </p>
                                                    <p style="margin: 0; font-size: 12px; color: #666666; line-height: 18px;">
                                                        Commandez plus rapidement sans ressaisir vos coordonnées.
                                                    </p>
                                                </td>
                                                <td width="50%" valign="top" style="padding-bottom: 20px; padding-left: 10px;">
                                                    <p style="margin: 0 0 5px 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                                        <span style="color: #B76E4D;">04.</span> Wishlist
                                                    </p>
                                                    <p style="margin: 0; font-size: 12px; color: #666666; line-height: 18px;">
                                                        Sauvegardez vos coups de cœur pour vos prochains achats.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #FDFBF7; border: 1px dashed #CCCCCC;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666666;">Rappel Commande</td>
                                                <td align="right" style="font-size: 11px; font-weight: bold; color: #B76E4D;">#{{ $commande->numero_commande }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top: 5px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666666;">Montant</td>
                                                <td align="right" style="padding-top: 5px; font-size: 11px; font-weight: bold; color: #B76E4D;">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 0 40px 40px 40px;">
                            <p style="font-size: 12px; color: #1A1A1A; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">Service Conciergerie</p>
                            <p style="font-size: 12px; color: #666666; margin: 0;">
                                <a href="mailto:contact@nd-world.site" style="color: #666666; text-decoration: none; border-bottom: 1px solid #CCCCCC;">contact@nd-world.site</a>
                                &nbsp;|&nbsp; 
                                <a href="https://wa.me/221765923402" style="color: #666666; text-decoration: none;">+221 76 592 34 02</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 30px 40px; background-color: #F9F9F9; border-top: 1px solid #EEEEEE;">
                            <p style="margin: 0 0 10px 0; font-size: 10px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                Suivez-nous
                            </p>
                            <p style="margin: 0 0 20px 0;">
                                <a href="{{ config('app.instagram_url') }}" style="color: #B76E4D; text-decoration: none; font-size: 11px; margin: 0 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">Instagram</a>
                                <a href="{{ config('app.tiktok_url') }}" style="color: #B76E4D; text-decoration: none; font-size: 11px; margin: 0 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">TikTok</a>
                            </p>
                            <p style="font-size: 10px; color: #CCCCCC; margin: 0;">
                                © {{ date('Y') }} NDEYA SHOP. Tous droits réservés.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>