<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de reservation - Bichette Thomas</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #fff6fb; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body, td, th { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1A1A1A; }
        .uppercase { text-transform: uppercase; letter-spacing: 2px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .border-top { border-top: 1px solid #f2d7e5; }
        .border-bottom { border-bottom: 1px solid #f2d7e5; }
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; padding: 20px !important; }
            .columns { display: block !important; width: 100% !important; }
            .mobile-center { text-align: center !important; }
            .mobile-padding { padding-top: 20px !important; }
        }
    </style>
</head>
<body style="background-color: #fff6fb; margin: 0; padding: 40px 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="600" class="wrapper" style="background-color: #ffffff; max-width: 600px; width: 100%;">
                    <tr>
                        <td align="center" style="padding: 40px 0 20px 0; border-bottom: 4px solid #e91e63;">
                            <h1 style="margin: 0; font-size: 26px; letter-spacing: 5px; text-transform: uppercase; color: #e91e63;">BICHETTE THOMAS</h1>
                            <p style="margin: 10px 0 0 0; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #9a4a6b;">Elegance & Soin</p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 40px 40px 20px 40px;">
                            <p style="font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #a46a80; margin-bottom: 10px;">Merci {{ $receipt['client_nom'] ?? 'Cliente' }}</p>
                            <h2 style="font-size: 22px; font-weight: 300; margin: 0 0 20px 0; text-transform: uppercase; letter-spacing: 1px;">Reservation confirmee</h2>
                            <p style="font-size: 14px; line-height: 24px; color: #4b3440; max-width: 420px; margin: 0 auto;">
                                Votre reservation est enregistree. Nous avons bien recu votre acompte et preparons votre accueil avec soin.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 0 40px 30px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fff0f6; padding: 15px; border: 1px solid #f5d3e2;">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #a0526d;">
                                            Reservation #{{ $receipt['reservation_id'] ?? '-' }} <span style="color: #e2b5c6;">|</span>
                                            {{ $receipt['date_reservation'] ?? 'Date a confirmer' }}
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
                                        <th align="left" style="padding-bottom: 15px; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid #f2d7e5;">Prestation</th>
                                        <th align="right" style="padding-bottom: 15px; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; border-bottom: 1px solid #f2d7e5;">Heure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($receipt['services'] ?? []) as $service)
                                    <tr>
                                        <td align="left" style="padding: 15px 0; border-bottom: 1px solid #f7e3ec;">
                                            <p style="margin: 0; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">{{ $service }}</p>
                                        </td>
                                        <td align="right" style="padding: 15px 0; border-bottom: 1px solid #f7e3ec; font-size: 14px;">
                                            {{ $receipt['heure_debut'] ?? '--:--' }}
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
                                    <td align="right" style="padding-top: 15px; font-size: 12px; color: #a0526d; text-transform: uppercase; letter-spacing: 1px;">Acompte paye</td>
                                    <td align="right" style="padding-top: 15px; font-size: 12px; width: 140px;">{{ number_format((float) ($receipt['montant_paye'] ?? 0), 0, ',', ' ') }} {{ $receipt['devise'] ?? 'FCFA' }}</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 10px; font-size: 12px; color: #a0526d; text-transform: uppercase; letter-spacing: 1px;">Total</td>
                                    <td align="right" style="padding-top: 10px; font-size: 12px;">{{ number_format((float) ($receipt['montant_total'] ?? 0), 0, ',', ' ') }} {{ $receipt['devise'] ?? 'FCFA' }}</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 10px; font-size: 12px; color: #e91e63; text-transform: uppercase; letter-spacing: 1px;">Reste a payer</td>
                                    <td align="right" style="padding-top: 10px; font-size: 12px; color: #e91e63;">{{ number_format((float) ($receipt['reste_a_payer'] ?? 0), 0, ',', ' ') }} {{ $receipt['devise'] ?? 'FCFA' }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top: 15px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top: 1px dashed #e91e63;">
                                            <tr>
                                                <td align="right" style="padding-top: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Paiement</td>
                                                <td align="right" style="padding-top: 15px; font-size: 12px; font-weight: bold; width: 140px;">{{ $receipt['mode_paiement'] ?? '---' }} · {{ $receipt['numero_recu'] ?? '---' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 30px 40px; background-color: #fff6fb;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td valign="top" class="columns" width="50%" style="padding-top: 25px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a0526d;">Contact</h3>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #4b3440;">
                                            {{ $receipt['salon_whatsapp'] ?? 'WhatsApp disponible' }}
                                        </p>
                                    </td>
                                    <td valign="top" class="columns" width="50%" style="padding-top: 25px;">
                                        <h3 style="margin: 0 0 10px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a0526d;">Rendez-vous</h3>
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #4b3440;">
                                            {{ $receipt['date_reservation'] ?? 'Date a confirmer' }}<br>
                                            {{ $receipt['heure_debut'] ?? '--:--' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 30px 40px 40px 40px; border-top: 1px solid #f2d7e5;">
                            <p style="margin: 0 0 15px 0; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #a0526d;">BICHETTE THOMAS</p>
                            <p style="margin: 0; font-size: 10px; color: #c19aaa; text-transform: uppercase; letter-spacing: 1px;">
                                © {{ date('Y') }} Bichette Thomas. Dakar, Senegal.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
