/**
 * Test de charge K6 — Bichette Thomas
 *
 * Objectif : valider la tenue à 200 utilisateurs simultanés avant publication.
 *
 * Utilisation :
 *   k6 run infra/scripts/k6-load-test.js
 *   k6 run --env BASE_URL=https://bichettethomas.site infra/scripts/k6-load-test.js
 *
 * Résultats attendus sur CX23 optimisé :
 *   - catalogue p95 < 500 ms
 *   - réservation p95 < 2 000 ms
 *   - taux d'erreur < 1 %
 */

import http from 'k6/http'
import { check, sleep } from 'k6'
import { Counter, Rate, Trend } from 'k6/metrics'

// ── Configuration ────────────────────────────────────────────────────────────

const BASE_URL = __ENV.BASE_URL || 'http://localhost'

// ── Métriques personnalisées ─────────────────────────────────────────────────

const erreurs           = new Rate('erreurs_custom')
const catalogueDuration = new Trend('catalogue_duration_ms', true)
const authDuration      = new Trend('auth_duration_ms', true)
const reservDuration    = new Trend('reservation_duration_ms', true)
const reservCrees       = new Counter('reservations_creees')

// ── Scénarios ────────────────────────────────────────────────────────────────
//
// Répartition réaliste :
//   80 % navigation catalogue  → 160 VUs
//   10 % tentatives connexion  →  20 VUs
//   10 % création réservation  →  20 VUs (espèces, sans redirect NabooPay)
//
// Montée progressive en 2 min pour simuler une vraie vague de trafic
// (publicité lancée → afflux progressif, pas instantané).

export const options = {
  scenarios: {
    navigation: {
      executor:   'ramping-vus',
      startVUs:   0,
      stages: [
        { duration: '1m',  target: 80  }, // montée douce
        { duration: '1m',  target: 160 }, // charge nominale
        { duration: '4m',  target: 160 }, // maintien (pic publicitaire)
        { duration: '1m',  target: 0   }, // descente
      ],
      exec: 'navigation',
    },

    connexion: {
      executor:   'ramping-vus',
      startVUs:   0,
      startTime:  '30s',               // démarre après que le catalogue soit chaud
      stages: [
        { duration: '1m',  target: 10 },
        { duration: '4m',  target: 20 },
        { duration: '1m',  target: 0  },
      ],
      exec: 'connexion',
    },

    reservation: {
      executor:   'ramping-vus',
      startVUs:   0,
      startTime:  '30s',
      stages: [
        { duration: '1m',  target: 10 },
        { duration: '4m',  target: 20 },
        { duration: '1m',  target: 0  },
      ],
      exec: 'reservation',
    },
  },

  // Seuils à respecter pour valider le test
  thresholds: {
    http_req_failed:       ['rate<0.01'],    // < 1 % d'erreurs HTTP globales
    http_req_duration:     ['p(95)<1500'],   // 95 % des requêtes < 1,5 s
    catalogue_duration_ms: ['p(95)<500'],    // catalogue < 500 ms
    auth_duration_ms:      ['p(95)<700'],    // auth < 700 ms
    reservation_duration_ms: ['p(95)<2000'],// réservation < 2 s
    erreurs_custom:        ['rate<0.01'],
  },
}

// ── Setup : récupère les IDs réels du catalogue ───────────────────────────────
//
// Exécuté une seule fois avant le début du test.
// Les données sont partagées en lecture seule entre tous les VUs.

export function setup() {
  const res = http.get(`${BASE_URL}/api/client/catalogue`, {
    headers: { Accept: 'application/json' },
    timeout: '10s',
  })

  if (res.status !== 200) {
    console.error(`[setup] Catalogue indisponible (HTTP ${res.status}) — vérifiez que le serveur est démarré.`)
    return {}
  }

  let body
  try {
    body = res.json()
  } catch (_) {
    console.error('[setup] Réponse catalogue non JSON')
    return {}
  }

  const coiffures = body?.data?.coiffures ?? []

  // Recherche d'une coiffure active avec au moins une variante
  const coiffure = coiffures.find(
    (c) => c.actif && Array.isArray(c.variantes) && c.variantes.length > 0
  )

  if (!coiffure) {
    console.warn('[setup] Aucune coiffure active avec variante trouvée — scénario réservation désactivé.')
    return {}
  }

  console.log(`[setup] Coiffure de test : "${coiffure.nom}" (id=${coiffure.id}, variante_id=${coiffure.variantes[0].id})`)

  return {
    coiffure_id:  coiffure.id,
    variante_id:  coiffure.variantes[0].id,
  }
}

// ── Scénario 1 : Navigation catalogue ────────────────────────────────────────
//
// Simule un visiteur qui charge la page d'accueil, lit le catalogue,
// puis revient 2 à 6 secondes plus tard (comportement réel de lecture).

export function navigation() {
  const debut = Date.now()
  const res = http.get(`${BASE_URL}/api/client/catalogue`, {
    headers: { Accept: 'application/json' },
    tags:    { scenario: 'navigation' },
  })
  catalogueDuration.add(Date.now() - debut)

  const ok = check(res, {
    'catalogue: HTTP 200':       (r) => r.status === 200,
    'catalogue: contient data':  (r) => {
      try { return typeof r.json('data') === 'object' } catch (_) { return false }
    },
  })
  erreurs.add(!ok)

  // Lecture simulée entre 2 et 6 secondes
  sleep(2 + Math.random() * 4)
}

// ── Scénario 2 : Tentative de connexion ──────────────────────────────────────
//
// Simule un client qui saisit son numéro pour recevoir un magic link.
// On utilise des numéros inventés → réponse 404 attendue (numéro inconnu).
// L'important est de mesurer le temps de réponse de l'endpoint sous charge.

const NUMEROS_TEST = [
  '+221771397393',
  '+221777231600',
  '+221765923402',
  '+221774444444',
  '+221785555555',
]

export function connexion() {
  const telephone = NUMEROS_TEST[Math.floor(Math.random() * NUMEROS_TEST.length)]

  const debut = Date.now()
  const res = http.post(
    `${BASE_URL}/api/client/auth/login`,
    JSON.stringify({ telephone }),
    {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      tags:    { scenario: 'connexion' },
      // 404 (numéro inconnu), 422 (validation), 429 (rate limit) sont des
      // réponses attendues avec de faux numéros — ne pas les compter comme
      // http_req_failed pour ne pas fausser le taux d'erreur global.
      responseCallback: http.expectedStatuses({ min: 200, max: 299 }, 404, 422, 429),
    }
  )
  authDuration.add(Date.now() - debut)

  // 200 (magic link envoyé) ou 404 (numéro inconnu) ou 429 (rate limit) :
  // tous sont des comportements corrects de l'API.
  const ok = check(res, {
    'auth: réponse valide': (r) => [200, 404, 422, 429].includes(r.status),
  })
  erreurs.add(!ok)

  // Pause réaliste entre deux tentatives
  sleep(3 + Math.random() * 5)
}

// ── Scénario 3 : Réservation complète ────────────────────────────────────────
//
// Simule le parcours le plus lourd : soumission du formulaire de réservation.
// On choisit le mode "especes" pour éviter le redirect NabooPay en test.
//
// Le numéro de téléphone est aléatoire → crée un nouveau client à chaque fois.
// Risque : saturation de la table clients. Prévoir un nettoyage post-test :
//   DELETE FROM clients WHERE source = 'en_ligne' AND created_at > NOW() - INTERVAL '2h';

export function reservation(data) {
  // Désactiver le scénario si setup n'a pas trouvé de coiffure valide
  if (!data?.coiffure_id) {
    sleep(5)
    return
  }

  // Génère un numéro sénégalais aléatoire pour ne pas déclencher le rate limit
  // sur un numéro fixe (throttle: 10/min par IP dans l'API).
  const suffixe = String(Math.floor(10000000 + Math.random() * 89999999))
  const telephone = `+2217${suffixe}`

  // Date J+7 (évite les conflits avec les créneaux réels du salon)
  const date = new Date()
  date.setDate(date.getDate() + 7)
  const dateReservation = date.toISOString().split('T')[0]

  const payload = {
    client: {
      nom:       `TestNom${suffixe.slice(-4)}`,
      prenom:    `TestPrenom${suffixe.slice(-4)}`,
      telephone,
      email:     null,
    },
    coiffure_id:          data.coiffure_id,
    variante_coiffure_id: data.variante_id,
    option_ids:           [],
    date_reservation:     dateReservation,
    heure_debut:          '10:00',
    mode_paiement:        'especes',   // pas de redirect NabooPay
    code_promo:           null,
    notes:                'Test de charge K6 — à supprimer.',
    success_url:          `${BASE_URL}/client?paiement=naboopay_success`,
    cancel_url:           `${BASE_URL}/client?paiement=naboopay_cancel`,
    // Clé d'idempotence unique par requête pour ne pas créer de doublons
    idempotency_key:      `k6-${Date.now()}-${Math.random().toString(36).slice(2)}`,
    reference_paiement:   null,
  }

  const debut = Date.now()
  const res = http.post(
    `${BASE_URL}/api/client/reservations`,
    JSON.stringify(payload),
    {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      tags:    { scenario: 'reservation' },
      timeout: '15s',
    }
  )
  reservDuration.add(Date.now() - debut)

  const ok = check(res, {
    // 201 = succès, 422 = créneau indisponible, 429 = rate limit : tous valides
    'reservation: réponse attendue': (r) => [201, 422, 429].includes(r.status),
  })

  if (res.status === 201) {
    reservCrees.add(1)
  }

  erreurs.add(!ok)

  // Pause longue : un client ne réserve pas deux fois en 10 secondes
  sleep(5 + Math.random() * 8)
}
