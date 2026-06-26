<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe - NDEYA</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #FDFBF7; }
        table { border-collapse: collapse !important; }
        body, td, th { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1A1A1A; }
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; padding: 20px !important; }
        }
    </style>
</head>
<body style="background-color: #FDFBF7; margin: 0; padding: 40px 0;">

    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="600" class="wrapper" style="background-color: #FFFFFF; max-width: 600px; width: 100%;">

                    {{-- Header --}}
                    <tr>
                        <td align="center" style="padding: 40px 0 20px 0; border-bottom: 4px solid #B76E4D;">
                            <h1 style="margin: 0; font-size: 28px; letter-spacing: 6px; text-transform: uppercase; color: #B76E4D;">NDEYA</h1>
                            <p style="margin: 10px 0 0 0; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #999999;">L'Élégance Intemporelle</p>
                        </td>
                    </tr>

                    {{-- Title --}}
                    <tr>
                        <td align="center" style="padding: 40px 40px 20px 40px;">
                            <p style="font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #666666; margin-bottom: 10px;">Bonjour {{ $userName }}</p>
                            <h2 style="font-size: 24px; font-weight: 300; margin: 0 0 20px 0; text-transform: uppercase; letter-spacing: 1px;">Réinitialisation du Mot de Passe</h2>
                            <p style="font-size: 14px; line-height: 24px; color: #444444; max-width: 400px; margin: 0 auto;">
                                Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe.
                            </p>
                        </td>
                    </tr>

                    {{-- CTA Button --}}
                    <tr>
                        <td align="center" style="padding: 10px 40px 40px 40px;">
                            <a href="{{ $resetUrl }}" style="background-color: #B76E4D; color: #FFFFFF; display: inline-block; padding: 16px 40px; text-decoration: none; text-transform: uppercase; font-size: 12px; letter-spacing: 2px; font-weight: bold; border-radius: 0;">
                                Réinitialiser mon mot de passe
                            </a>
                            <p style="margin: 15px 0 0 0; font-size: 10px; color: #999999; text-transform: uppercase; letter-spacing: 1px;">
                                Ce lien est valide pendant 60 minutes
                            </p>
                        </td>
                    </tr>

                    {{-- Info box --}}
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F9F9F9; border-left: 4px solid #B76E4D; padding: 20px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; color: #B76E4D;">Vous n'avez pas demandé cette réinitialisation ?</p>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #666666;">
                                            Ignorez simplement cet email. Votre mot de passe actuel reste inchangé et sécurisé.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Security tips --}}
                    <tr>
                        <td style="padding: 0 40px 40px 40px;">
                            <p style="margin: 0 0 15px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #888888;">Conseils de sécurité</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 13px; color: #666666; border-bottom: 1px solid #F5F5F5;">Choisissez un mot de passe d'au moins 8 caractères</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 13px; color: #666666; border-bottom: 1px solid #F5F5F5;">Mélangez majuscules, minuscules, chiffres et symboles</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 13px; color: #666666;">Ne partagez jamais votre mot de passe</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Support --}}
                    <tr>
                        <td style="padding: 0 40px 40px 40px; background-color: #FDFBF7;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding-top: 30px;">
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

                    {{-- Footer --}}
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
