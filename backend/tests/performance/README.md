# Tests de performance NDEYA avec k6

Serveur cible actuel: 2 vCPU, 4 GB RAM. Le but est de trouver la limite propre du site sans casser la prod.

Attention: ne lance pas `realtime`, `stress`, `spike` ou `soak` directement sur la prod pendant que des visiteurs utilisent le site. Ces profils peuvent saturer PHP-FPM/Postgres et rendre l'application indisponible. Sur `https://ndeya-shop.site`, le script bloque maintenant ces profils sauf si tu ajoutes explicitement `-e ALLOW_PROD_STRESS=1`.

## Installation k6

Sur ton PC:

```bash
choco install k6
```

Ou sur Ubuntu:

```bash
sudo gpg -k
sudo apt install gnupg2 ca-certificates
curl -s https://dl.k6.io/key.gpg | sudo gpg --dearmor -o /usr/share/keyrings/k6-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt update
sudo apt install k6
```

## Important pour eviter les faux 429

k6 envoie beaucoup de requetes depuis une seule IP. Le site a un rate limit normal pour proteger la prod. Pour un test propre, ajoute un token secret dans l'environnement du backend:

```env
PERFORMANCE_TEST_BYPASS_TOKEN=un-secret-long-et-aleatoire
```

Puis redeploie/cache config:

```bash
php artisan optimize:clear
php artisan config:cache
```

Ensuite lance k6 avec le meme token:

```bash
k6 run tests/performance/ndeya-load-test.js -e BASE_URL=https://ndeya-shop.site -e PERF_TOKEN=un-secret-long-et-aleatoire
```

Sans ce token, les vrais visiteurs restent proteges normalement.

## Tests recommandes

1. Smoke test, pour verifier que le script marche:

```bash
k6 run tests/performance/ndeya-load-test.js -e PROFILE=smoke -e BASE_URL=https://ndeya-shop.site -e PERF_TOKEN=un-secret-long-et-aleatoire
```

2. Test prod-safe, pour verifier sans rendre le site inutilisable:

```bash
k6 run tests/performance/ndeya-load-test.js \
  -e PROFILE=prod_safe \
  -e BASE_URL=https://ndeya-shop.site \
  -e PERF_TOKEN=un-secret-long-et-aleatoire \
  -e MAX_RPS=10
```

3. Load test, cible raisonnable pour ton serveur:

```bash
k6 run tests/performance/ndeya-load-test.js \
  -e PROFILE=load \
  -e BASE_URL=https://ndeya-shop.site \
  -e PERF_TOKEN=un-secret-long-et-aleatoire \
  -e MAX_RPS=20
```

4. Stress test, pour trouver la limite. A lancer de preference sur staging, ou en prod seulement en maintenance:

```bash
k6 run tests/performance/ndeya-load-test.js \
  -e PROFILE=stress \
  -e BASE_URL=https://ndeya-shop.site \
  -e PERF_TOKEN=un-secret-long-et-aleatoire \
  -e ALLOW_PROD_STRESS=1
```

5. Spike test, pour simuler un gros pic d'un coup:

```bash
k6 run tests/performance/ndeya-load-test.js \
  -e PROFILE=spike \
  -e BASE_URL=https://ndeya-shop.site \
  -e PERF_TOKEN=un-secret-long-et-aleatoire \
  -e ALLOW_PROD_STRESS=1
```

## Ajouter un produit/categorie reel

Pour tester une vraie fiche produit et une vraie categorie:

```bash
k6 run tests/performance/ndeya-load-test.js \
  -e PROFILE=load \
  -e BASE_URL=https://ndeya-shop.site \
  -e PERF_TOKEN=un-secret-long-et-aleatoire \
  -e PRODUCT_SLUG=jupe-designer \
  -e CATEGORY_SLUG=jupes
```

## Comment lire le resultat

Bon pour ce serveur:
- `http_req_failed` proche de 0%
- `p95` sous 1000 ms
- `p99` sous 3000 ms
- CPU sous 80% pendant le palier
- RAM stable, pas de swap

Acceptable:
- `http_req_failed` sous 1%
- `p95` sous 2000 ms
- pas de redemarrage de container

A corriger:
- `p95` au-dessus de 2 secondes
- erreurs HTTP au-dessus de 1%
- beaucoup de 500/502/504
- CPU bloque a 100%
- RAM pleine ou swap

## Pendant le test, surveiller le serveur

```bash
docker stats
docker logs --tail=100 backend
docker logs --tail=100 nginx
docker logs --tail=100 queue
```

Le script ecrit aussi un resume dans:

```text
tests/performance/results-summary.json
```
