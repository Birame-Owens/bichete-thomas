<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Signalement — Bichette Thomas</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 24px; }
  .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
  .header { background: #e91e63; padding: 28px 32px; }
  .header h1 { color: #fff; margin: 0; font-size: 20px; font-weight: 800; }
  .header p { color: rgba(255,255,255,.75); margin: 4px 0 0; font-size: 13px; }
  .body { padding: 28px 32px; }
  .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 18px; }
  .badge-urgente { background: #fee2e2; color: #b91c1c; }
  .badge-normale  { background: #fef9c3; color: #854d0e; }
  .row { margin-bottom: 14px; }
  .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #9ca3af; margin-bottom: 3px; }
  .value { font-size: 14px; color: #111; font-weight: 600; }
  .description { background: #f9fafb; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #374151; line-height: 1.6; }
  .footer { padding: 16px 32px; border-top: 1px solid #f3f4f6; font-size: 12px; color: #9ca3af; text-align: center; }
  .btn { display: inline-block; margin-top: 20px; background: #e91e63; color: #fff; text-decoration: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h1>Nouveau signalement</h1>
    <p>Bichette Thomas — Salon de Coiffure</p>
  </div>
  <div class="body">
    <span class="badge badge-{{ $signalement->urgence }}">
      {{ $signalement->urgence === 'urgente' ? '🔴 Urgent' : '🟡 Normal' }}
    </span>

    <div class="row">
      <div class="label">Categorie</div>
      <div class="value">
        @if($signalement->type === 'produit') Produit / Fourniture
        @elseif($signalement->type === 'materiel') Equipement / Materiel
        @else Autre
        @endif
      </div>
    </div>

    <div class="row">
      <div class="label">Objet</div>
      <div class="value">{{ $signalement->titre }}</div>
    </div>

    @if($signalement->description)
    <div class="row">
      <div class="label">Details</div>
      <div class="description">{{ $signalement->description }}</div>
    </div>
    @endif

    <div class="row" style="margin-top:18px">
      <div class="label">Envoye par</div>
      <div class="value">{{ $signalement->gerante?->name ?? 'La gerante' }}</div>
    </div>

    <div class="row">
      <div class="label">Date</div>
      <div class="value">{{ $signalement->created_at->format('d/m/Y à H:i') }}</div>
    </div>
  </div>
  <div class="footer">
    Ce signalement est visible dans votre espace d'administration.
  </div>
</div>
</body>
</html>
