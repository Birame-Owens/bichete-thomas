/**
 * NDEYA SHOP — Script k6 v2
 * Test 200 utilisateurs simultanés (réaliste)
 *
 * Profils disponibles via PROFILE= :
 *   smoke      — 5 VUs, vérification rapide
 *   prod_safe  — test leger compatible prod, garde le site utilisable
 *   load       — charge raisonnable pour serveur 2 vCPU / 4 GB  ← défaut
 *   realtime   — rampe jusqu'à 200 VUs, plateau 10 min
 *   stress     — pousse jusqu'à 300 VUs pour trouver le point de rupture
 *   spike      — pic brutal à 200 VUs en 30 secondes
 *   soak       — 100 VUs pendant 30 min (endurance)
 *
 * Usage :
 *   k6 run -e BASE_URL=https://ndeya-shop.site \
 *          -e PERF_TOKEN=<PERFORMANCE_TEST_BYPASS_TOKEN_du_serveur> \
 *          -e PRODUCT_SLUG=mon-produit \
 *          -e CATEGORY_SLUG=robes \
 *          -e PROFILE=load \
 *          -e MAX_RPS=20 \
 *          tests/performance/ndeya-load-test.js
 *
 * PERF_TOKEN est obligatoire — sans lui le rate limiting (180 req/min/IP)
 * bloque tous les VUs en 429 des qu'ils depassent ~3 VUs actifs.
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Counter, Rate, Trend, Gauge } from 'k6/metrics';

// ─── Configuration ─────────────────────────────────────────────────────────
const BASE_URL    = (__ENV.BASE_URL    || 'https://ndeya-shop.site').replace(/\/$/, '');
const PROFILE     = __ENV.PROFILE     || 'load';
const PERF_TOKEN  = __ENV.PERF_TOKEN  || '';
const PRODUCT_SLUG   = __ENV.PRODUCT_SLUG   || 'boubou-homme-brodé';
const CATEGORY_SLUG  = __ENV.CATEGORY_SLUG  || 'robes';
const SEARCH_QUERY   = __ENV.SEARCH_QUERY   || 'robe';
const MAX_RPS        = Number(__ENV.MAX_RPS || 0);
const ALLOW_PROD_STRESS = __ENV.ALLOW_PROD_STRESS === '1';
const PROD_LIKE = /^https:\/\/(www\.)?ndeya-shop\.site/i.test(BASE_URL);
const DANGEROUS_PROFILES = ['realtime', 'stress', 'spike', 'soak'];

if (PROD_LIKE && DANGEROUS_PROFILES.includes(PROFILE) && !ALLOW_PROD_STRESS) {
  throw new Error(
    `[NDEYA k6] Profil ${PROFILE} bloque sur la prod.\n` +
    'Utilise PROFILE=prod_safe ou PROFILE=load pour garder le site utilisable.\n' +
    'Pour forcer quand meme un test destructif: -e ALLOW_PROD_STRESS=1'
  );
}

const EFFECTIVE_MAX_RPS = MAX_RPS > 0 ? MAX_RPS : (PROD_LIKE ? 20 : 0);

// PERF_TOKEN est OBLIGATOIRE pour bypasser le rate limit (180 req/min/IP).
// Sans lui, 200 VUs depuis une seule machine = 429 en masse des la 2eme minute.
// 1. Definir PERFORMANCE_TEST_BYPASS_TOKEN=<secret> dans le .env du serveur
// 2. Passer le meme secret ici : k6 run -e PERF_TOKEN=<secret> ...
if (!PERF_TOKEN) {
  console.warn('\n[NDEYA k6] ATTENTION: PERF_TOKEN non defini !' +
    '\n  Tous les VUs partagent la meme IP -> rate limit 429 en masse.' +
    '\n  Definis PERFORMANCE_TEST_BYPASS_TOKEN dans le .env du serveur' +
    '\n  et relance avec: k6 run -e PERF_TOKEN=<ton_token> ...\n');
}

// ─── Métriques personnalisées ──────────────────────────────────────────────
const errors         = new Counter('ndeya_errors');
const okRate         = new Rate('ndeya_ok_rate');
const cacheHitRate   = new Rate('ndeya_cache_hit');   // X-Cache: HIT détecté
const homeLatency    = new Trend('ndeya_home_ms',    true);
const productLatency = new Trend('ndeya_product_ms', true);
const searchLatency  = new Trend('ndeya_search_ms',  true);
const cartLatency    = new Trend('ndeya_cart_ms',    true);
const activeVUs      = new Gauge('ndeya_active_vus');

// ─── Profils de charge ─────────────────────────────────────────────────────
const profiles = {

  smoke: {
    executor: 'ramping-vus',
    stages: [
      { duration: '30s', target: 5  },
      { duration: '1m',  target: 5  },
      { duration: '30s', target: 0  },
    ],
  },

  // Test leger, a utiliser sur la prod pendant les heures calmes.
  // Objectif : verifier la stabilite sans monopoliser PHP-FPM/Postgres.
  prod_safe: {
    executor: 'ramping-vus',
    stages: [
      { duration: '30s', target: 5  },
      { duration: '1m',  target: 10 },
      { duration: '2m',  target: 15 },
      { duration: '30s', target: 0  },
    ],
  },

  // Load test raisonnable pour un serveur 2 vCPU / 4 GB.
  // Ancien bug: PROFILE=load n'existait pas et retombait sur realtime (200 VUs).
  load: {
    executor: 'ramping-vus',
    stages: [
      { duration: '1m', target: 10 },
      { duration: '2m', target: 25 },
      { duration: '4m', target: 40 },
      { duration: '2m', target: 0  },
    ],
  },

  // 200 VUs — monte progressivement, plateau 10 min, redescend
  realtime: {
    executor: 'ramping-vus',
    stages: [
      { duration: '1m',  target: 50  },   // chauffe douce
      { duration: '2m',  target: 100 },   // montée intermédiaire
      { duration: '2m',  target: 200 },   // rampe finale vers 200
      { duration: '10m', target: 200 },   // plateau — test principal
      { duration: '2m',  target: 100 },   // descente
      { duration: '1m',  target: 0   },   // arrêt
    ],
  },

  // Trouve le point de rupture : dépasse 200, monte à 300
  stress: {
    executor: 'ramping-vus',
    stages: [
      { duration: '2m',  target: 50  },
      { duration: '2m',  target: 100 },
      { duration: '2m',  target: 150 },
      { duration: '2m',  target: 200 },
      { duration: '3m',  target: 250 },
      { duration: '3m',  target: 300 },
      { duration: '3m',  target: 0   },
    ],
  },

  // Pic brutal — simule un lancement produit ou une promo flash
  spike: {
    executor: 'ramping-vus',
    stages: [
      { duration: '30s', target: 20  },   // trafic normal
      { duration: '30s', target: 200 },   // pic soudain
      { duration: '3m',  target: 200 },   // maintien du pic
      { duration: '30s', target: 20  },   // retour normal
      { duration: '1m',  target: 0   },
    ],
  },

  // Endurance — vérifie qu'il n'y a pas de fuite mémoire / connexions
  soak: {
    executor: 'ramping-vus',
    stages: [
      { duration: '5m',  target: 100 },
      { duration: '30m', target: 100 },
      { duration: '5m',  target: 0   },
    ],
  },
};

// ─── Options globales ──────────────────────────────────────────────────────
export const options = {
  scenarios: {
    ndeya_traffic: profiles[PROFILE] || profiles.load,
  },
  ...(EFFECTIVE_MAX_RPS > 0 ? { rps: EFFECTIVE_MAX_RPS } : {}),
  thresholds: {
    // Taux d'erreurs HTTP < 1 %
    http_req_failed:      ['rate<0.01'],
    // Temps de réponse global
    http_req_duration:    ['p(95)<2000', 'p(99)<4000'],
    // Métriques par catégorie
    ndeya_ok_rate:       ['rate>0.99'],
    ndeya_home_ms:       ['p(95)<1500'],
    ndeya_product_ms:    ['p(95)<2000'],
    ndeya_search_ms:     ['p(95)<1800'],
    ndeya_cart_ms:       ['p(95)<1000'],
  },
  userAgent: 'k6-ndeya/2.0',
  discardResponseBodies: false,
};

// ─── Headers de base ───────────────────────────────────────────────────────
const baseHeaders = {
  'Accept':          'application/json',
  'Accept-Language': 'fr-FR,fr;q=0.9',
};
if (PERF_TOKEN) {
  baseHeaders['X-Performance-Test-Token'] = PERF_TOKEN;
}

// ─── Helper requête ─────────────────────────────────────────────────────────
function req(name, method, path, body = null, extraHeaders = {}) {
  const params = {
    headers: { ...baseHeaders, ...extraHeaders },
    tags:    { endpoint: name },
    timeout: '15s',
  };

  const url = `${BASE_URL}${path}`;
  const res  = method === 'POST'
    ? http.post(url, body ? JSON.stringify(body) : null, params)
    : http.get(url, params);

  const ok = check(res, {
    [`${name} — 2xx`]:      (r) => r.status >= 200 && r.status < 300,
    [`${name} — <5s`]:      (r) => r.timings.duration < 5000,
  });

  okRate.add(ok);
  if (!ok) errors.add(1);

  // Détecte un cache Redis (certains reverse proxies exposent X-Cache ou CF-Cache-Status)
  const xCache = res.headers['X-Cache'] || res.headers['Cf-Cache-Status'] || '';
  cacheHitRate.add(xCache.toLowerCase().includes('hit'));

  return res;
}

// ─── Scénarios utilisateurs ────────────────────────────────────────────────

/**
 * Visiteur vitrine — 55% du trafic
 * Parcourt la home, les produits, les catégories
 */
function scenarioBrowse() {
  group('home', () => {
    const r = req('api_home',    'GET', '/api/client/home');
    homeLatency.add(r.timings.duration);

    req('api_config',           'GET', '/api/client/config');
    req('api_featured',         'GET', '/api/client/featured-products?limit=8');
    req('api_categories',       'GET', '/api/client/categories-preview');
  });

  sleep(rand(1, 2));

  group('catalogue', () => {
    req('api_products_p1',      'GET', '/api/client/products?page=1&per_page=12&sort=recent');
    req('api_new_arrivals',     'GET', '/api/client/new-arrivals?limit=8');
    req('api_shop_stats',       'GET', '/api/client/shop-stats');
    req('api_promotions',       'GET', '/api/client/active-promotions');
  });

  sleep(rand(2, 4));
}

/**
 * Visiteur produit — 25% du trafic
 * Cherche un produit, l'ouvre, regarde les similaires
 */
function scenarioProduct() {
  group('recherche', () => {
    const r = req('api_search', 'GET', `/api/client/search?q=${encodeURIComponent(SEARCH_QUERY)}`);
    searchLatency.add(r.timings.duration);
    req('api_search_sugg',      'GET', `/api/client/search/suggestions?q=${encodeURIComponent(SEARCH_QUERY)}`);
  });

  sleep(rand(1, 3));

  group('fiche produit', () => {
    const r = req('api_product_page', 'GET', `/api/client/products/${PRODUCT_SLUG}/page-data`);
    productLatency.add(r.timings.duration);
    req('api_product_images',   'GET', `/api/client/products/${PRODUCT_SLUG}`);
  });

  sleep(rand(2, 4));
}

/**
 * Acheteur — 15% du trafic
 * Ajoute au panier, consulte le panier
 */
function scenarioCart() {
  group('panier', () => {
    const r1 = req('api_cart_get',   'GET', '/api/client/cart');
    cartLatency.add(r1.timings.duration);
    req('api_cart_count',            'GET', '/api/client/cart/count');
    req('api_wishlist_count',        'GET', '/api/client/wishlist/count');
  });

  sleep(rand(1, 2));
}

/**
 * Navigation catégorie — 5% du trafic
 */
function scenarioCategory() {
  group('catégorie', () => {
    req('api_cat_products', 'GET', `/api/client/categories/${CATEGORY_SLUG}/products?page=1&per_page=12`);
    req('api_cat_show',     'GET', `/api/client/categories/${CATEGORY_SLUG}`);
    req('api_products_cat', 'GET', `/api/client/products?category=${CATEGORY_SLUG}&page=1&per_page=12`);
  });

  sleep(rand(2, 3));
}

// ─── Distribution du trafic ────────────────────────────────────────────────
export default function () {
  activeVUs.add(1);

  const dice = Math.random();

  if (dice < 0.55) {
    scenarioBrowse();    // 55% visiteurs vitrine
  } else if (dice < 0.80) {
    scenarioProduct();   // 25% fiche produit / recherche
  } else if (dice < 0.95) {
    scenarioCart();      // 15% panier
  } else {
    scenarioCategory();  //  5% navigation catégorie
  }

  activeVUs.add(-1);
}

// ─── Utilitaire ────────────────────────────────────────────────────────────
function rand(min, max) {
  return Math.random() * (max - min) + min;
}

// ─── Résumé terminal ────────────────────────────────────────────────────────
export function handleSummary(data) {
  const dur   = data.metrics.http_req_duration?.values || {};
  const p50   = (dur['p(50)']  ?? 0).toFixed(0);
  const p95   = (dur['p(95)']  ?? 0).toFixed(0);
  const p99   = (dur['p(99)']  ?? 0).toFixed(0);
  const reqs  = data.metrics.http_reqs?.values?.count        ?? 0;
  const rate  = data.metrics.http_reqs?.values?.rate         ?? 0;
  const fails = ((data.metrics.http_req_failed?.values?.rate ?? 0) * 100).toFixed(2);
  const errs  = data.metrics.ndeya_errors?.values?.count    ?? 0;
  const cache = ((data.metrics.ndeya_cache_hit?.values?.rate ?? 0) * 100).toFixed(1);

  const homeP95    = (data.metrics.ndeya_home_ms?.values?.['p(95)']    ?? 0).toFixed(0);
  const productP95 = (data.metrics.ndeya_product_ms?.values?.['p(95)'] ?? 0).toFixed(0);
  const searchP95  = (data.metrics.ndeya_search_ms?.values?.['p(95)']  ?? 0).toFixed(0);
  const cartP95    = (data.metrics.ndeya_cart_ms?.values?.['p(95)']    ?? 0).toFixed(0);

  const lines = [
    '',
    '╔══════════════════════════════════════════════════════════╗',
    '║           NDEYA SHOP — Résultats k6 v2                 ║',
    '╠══════════════════════════════════════════════════════════╣',
    `║  Profil        : ${pad(PROFILE, 38)}║`,
    `║  Base URL      : ${pad(BASE_URL.replace('https://', ''), 38)}║`,
    '╠══════════════════════════════════════════════════════════╣',
    `║  Requêtes total  : ${pad(reqs, 36)}║`,
    `║  Débit moyen     : ${pad(rate.toFixed(1) + ' req/s', 36)}║`,
    `║  Taux d'erreurs  : ${pad(fails + '%', 36)}║`,
    `║  Erreurs comptées: ${pad(errs, 36)}║`,
    `║  Cache hits      : ${pad(cache + '%', 36)}║`,
    '╠══════════════════════════════════════════════════════════╣',
    '║  Latences globales                                       ║',
    `║    p50  : ${pad(p50 + ' ms', 46)}║`,
    `║    p95  : ${pad(p95 + ' ms', 46)}║`,
    `║    p99  : ${pad(p99 + ' ms', 46)}║`,
    '╠══════════════════════════════════════════════════════════╣',
    '║  Latences par endpoint (p95)                             ║',
    `║    /home          : ${pad(homeP95    + ' ms', 35)}║`,
    `║    /products/:slug: ${pad(productP95 + ' ms', 35)}║`,
    `║    /search        : ${pad(searchP95  + ' ms', 35)}║`,
    `║    /cart          : ${pad(cartP95    + ' ms', 35)}║`,
    '╚══════════════════════════════════════════════════════════╝',
    '',
  ];

  return {
    stdout: lines.join('\n'),
    'results-summary.json': JSON.stringify(data, null, 2),
  };
}

function pad(str, len) {
  const s = String(str);
  return s + ' '.repeat(Math.max(0, len - s.length));
}
