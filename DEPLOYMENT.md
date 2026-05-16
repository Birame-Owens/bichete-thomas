# Deploiement Coolify

## DNS

Chez le fournisseur du domaine, creer ces entrees DNS :

- `A` `@` -> `178.104.53.107`
- `A` `www` -> `178.104.53.107`

Attendre la propagation DNS, puis dans Coolify ajouter le domaine sur le service `nginx` :

- `https://bichettethomas.site`
- optionnel : `https://www.bichettethomas.site`

## Resource Coolify

Option recommandee :

1. `Projects` -> `New Resource`
2. Choisir le repository Git du projet.
3. Type : Docker Compose.
4. Compose file : `docker-compose.prod.yml`
5. Service expose au web : `nginx`
6. Port : `80`

Si le repo n est pas branche dans Coolify, choisir `Docker Compose Empty`, puis coller le contenu de `docker-compose.prod.yml`.

## Variables d environnement minimum

Dans Coolify, ajouter ces variables :

```env
APP_URL=https://bichettethomas.site
FRONTEND_URL=https://bichettethomas.site
SESSION_DOMAIN=bichettethomas.site

APP_KEY=base64:REMPLACER_PAR_UNE_CLE_LARAVEL
POSTGRES_DB=bichette_thomas
POSTGRES_USER=bichette
POSTGRES_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_FORT
REDIS_CLIENT=predis

ADMIN_EMAIL=admin@bichettethomas.site
ADMIN_NAME=Admin
ADMIN_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_FORT
GERANTE_EMAIL=gerante@bichettethomas.site
GERANTE_NAME=Gerante
GERANTE_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_FORT

NABOOPAY_BASE_URL=https://api.naboopay.com
NABOOPAY_API_KEY=REMPLACER_PAR_LA_CLE_NABOOPAY
NABOOPAY_WEBHOOK_SECRET=REMPLACER_PAR_LE_SECRET_WEBHOOK
NABOOPAY_SUCCESS_URL=https://bichettethomas.site/client?paiement=naboopay_success
NABOOPAY_ERROR_URL=https://bichettethomas.site/client?paiement=naboopay_cancel
NABOOPAY_FEES_CUSTOMER_SIDE=true
```

Generer `APP_KEY` localement :

```bash
docker compose exec -T backend php artisan key:generate --show
```

Ou avec PHP installe :

```bash
php artisan key:generate --show
```

## Apres le premier deploy

Dans le terminal Coolify du service `backend`, executer :

```bash
php artisan migrate --force
php artisan db:seed --force
```

Si les images upload ne s affichent pas, executer aussi :

```bash
php artisan storage:link
```

## NabooPay

Dans le dashboard NabooPay, configurer :

- Webhook URL : `https://bichettethomas.site/api/client/paiements/naboopay/webhook`
- Success URL : `https://bichettethomas.site/client?paiement=naboopay_success`
- Error URL : `https://bichettethomas.site/client?paiement=naboopay_cancel`

Le webhook doit envoyer le header `X-Signature`, calcule par NabooPay avec le secret configure dans `NABOOPAY_WEBHOOK_SECRET`.
