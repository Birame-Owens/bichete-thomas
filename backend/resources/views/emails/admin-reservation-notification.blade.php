<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle reservation - Bichette Thomas</title>
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; background-color: #fff6fb; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1A1A1A; }
        table { border-collapse: collapse !important; }
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; padding: 20px !important; }
        }
    </style>
</head>
<body style="background-color: #fff6fb; margin: 0; padding: 40px 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="600" class="wrapper" style="background-color: #ffffff; max-width: 600px; width: 100%;">

                    {{-- En-tête --}}
                    <tr>
                        <td align="center" style="padding: 30px 0 20px 0; border-bottom: 4px solid #e91e63;">
                            <h1 style="margin: 0; font-size: 22px; letter-spacing: 5px; text-transform: uppercase; color: #e91e63;">BICHETTE THOMAS</h1>
                            <p style="margin: 6px 0 0 0; font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #9a4a6b;">Notification interne</p>
                        </td>
                    </tr>

                    {{-- Titre --}}
                    <tr>
                        <td align="center" style="padding: 30px 40px 20px 40px;">
                            <p style="font-size: 12px; text-transform: uppercase; letter-spacing: 2px; color: #a46a80; margin: 0 0 8px 0;">Nouvelle reservation confirmee</p>
                            <h2 style="font-size: 20px; font-weight: 700; margin: 0; color: #1A1A1A;">
                                Reservation #{{ $receipt['reservation_id'] ?? '-' }}
                            </h2>
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #7a5060;">
                                {{ $receipt['date_reservation'] ?? 'Date a confirmer' }}
                                @if($receipt['heure_debut'] ?? null)
                                    &nbsp;à&nbsp;{{ $receipt['heure_debut'] }}
                                @endif
                            </p>
                        </td>
                    </tr>

                    {{-- Infos cliente --}}
                    <tr>
                        <td style="padding: 0 40px 20px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fff0f6; border: 1px solid #f5d3e2; padding: 16px;">
                                <tr>
                                    <td style="padding: 0 16px;">
                                        <p style="margin: 0 0 6px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a0526d;">Cliente</p>
                                        <p style="margin: 0; font-size: 16px; font-weight: bold; color: #1A1A1A;">{{ $receipt['client_nom'] ?? 'Cliente' }}</p>
                                        @if($receipt['telephone'] ?? null)
                                            <p style="margin: 4px 0 0 0; font-size: 14px; color: #4b3440;">{{ $receipt['telephone'] }}</p>
                                        @endif
                                        @if($receipt['email'] ?? null)
                                            <p style="margin: 2px 0 0 0; font-size: 12px; color: #7a5060;">{{ $receipt['email'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Prestations --}}
                    <tr>
                        <td style="padding: 0 40px 10px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding-bottom: 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a0526d; border-bottom: 1px solid #f2d7e5;">Prestation</th>
                                        <th align="right" style="padding-bottom: 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #a0526d; border-bottom: 1px solid #f2d7e5;">Heure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($receipt['services'] ?? []) as $service)
                                    <tr>
                                        <td align="left" style="padding: 12px 0; border-bottom: 1px solid #f7e3ec; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                            {{ $service }}
                                        </td>
                                        <td align="right" style="padding: 12px 0; border-bottom: 1px solid #f7e3ec; font-size: 14px;">
                                            {{ $receipt['heure_debut'] ?? '--:--' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    {{-- Montants --}}
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="right" style="padding-top: 12px; font-size: 12px; color: #a0526d; text-transform: uppercase; letter-spacing: 1px;">Acompte recu</td>
                                    <td align="right" style="padding-top: 12px; font-size: 12px; width: 140px; font-weight: bold;">
                                        {{ number_format((float) ($receipt['montant_paye'] ?? 0), 0, ',', ' ') }} {{ $receipt['devise'] ?? 'FCFA' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 8px; font-size: 12px; color: #a0526d; text-transform: uppercase; letter-spacing: 1px;">Total prestation</td>
                                    <td align="right" style="padding-top: 8px; font-size: 12px;">
                                        {{ number_format((float) ($receipt['montant_total'] ?? 0), 0, ',', ' ') }} {{ $receipt['devise'] ?? 'FCFA' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 8px; font-size: 13px; color: #e91e63; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">Reste a encaisser</td>
                                    <td align="right" style="padding-top: 8px; font-size: 13px; color: #e91e63; font-weight: bold;">
                                        {{ number_format((float) ($receipt['reste_a_payer'] ?? 0), 0, ',', ' ') }} {{ $receipt['devise'] ?? 'FCFA' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top: 12px; border-top: 1px dashed #e91e63;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td align="right" style="padding-top: 10px; font-size: 11px; color: #7a5060; text-transform: uppercase; letter-spacing: 1px;">Mode paiement</td>
                                                <td align="right" style="padding-top: 10px; font-size: 11px; width: 140px;">
                                                    {{ $receipt['mode_paiement'] ?? '---' }} · {{ $receipt['numero_recu'] ?? '---' }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Pied de page --}}
                    <tr>
                        <td align="center" style="padding: 20px 40px 30px 40px; border-top: 1px solid #f2d7e5;">
                            <p style="margin: 0; font-size: 10px; color: #c19aaa; text-transform: uppercase; letter-spacing: 1px;">
                                Notification interne — Bichette Thomas · Dakar, Senegal
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
