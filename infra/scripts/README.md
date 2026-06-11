# Infra scripts — Backup / Restore (B8)

Deux jeux de sauvegardes **quotidiennes chiffrees** vers un bucket S3-compatible
(Hetzner Object Storage, AWS S3, Backblaze B2, Wasabi, MinIO) :

- **PostgreSQL** (`backup-postgres.sh` / `restore-postgres.sh`) — la base de donnees.
- **Images** (`backup-images.sh` / `restore-images.sh`) — le volume `app_storage`
  (`storage/app/public`) ou vivent les photos de coiffures uploadees.

Les deux partagent la **meme passphrase** (`BACKUP_PASSPHRASE`), le meme bucket et
la meme logique de retention ; seuls le `S3_PREFIX` et le contenu different
(`prod/postgres` vs `prod/images`).

---

## PostgreSQL

Sauvegarde quotidienne chiffree de la base PostgreSQL vers un bucket
S3-compatible (Hetzner Object Storage, AWS S3, Backblaze B2, Wasabi, MinIO).

## Pipeline

```
pg_dump -Fc  ->  openssl AES-256 (passphrase)  ->  aws s3 cp
```

- **Format `-Fc`** : binaire compresse natif Postgres, restoration selective.
- **Chiffrement client-side** : protege le dump meme si les credentials S3
  fuient ou si le provider est compromis. Sans la passphrase, le blob est
  illisible.
- **Streaming** : aucun fichier en clair n est ecrit sur le disque.
- **Retention** : les dumps anterieurs a `BACKUP_RETENTION_DAYS` (defaut 30)
  sont supprimes automatiquement a la fin de chaque backup.

## Variables d environnement

A definir dans le `.env` du serveur (ou via secrets manager) :

| Variable | Exemple | Description |
|---|---|---|
| `POSTGRES_HOST` | `postgres` ou IP | Hote DB |
| `POSTGRES_PORT` | `5432` | Port DB |
| `POSTGRES_DB` | `bichete-thomas` | Nom de la base |
| `POSTGRES_USER` | `postgres` | User DB |
| `PGPASSWORD` | `***` | Mot de passe DB (lu par pg_dump/pg_restore) |
| `BACKUP_PASSPHRASE` | chaine longue aleatoire | **Cle AES-256, a conserver hors-ligne** |
| `S3_BUCKET` | `bichete-backups` | Bucket de destination |
| `S3_PREFIX` | `prod/postgres` | Prefixe (sous-dossier) |
| `S3_ENDPOINT` | `https://nbg1.your-objectstorage.com` | Endpoint S3 (Hetzner FR-3 par exemple) |
| `AWS_ACCESS_KEY_ID` | `...` | Cle d acces S3 |
| `AWS_SECRET_ACCESS_KEY` | `...` | Cle secrete S3 |
| `BACKUP_RETENTION_DAYS` | `30` (optionnel) | Jours de retention |

> **Critique** : si tu perds `BACKUP_PASSPHRASE`, **toutes les sauvegardes
> deviennent irrecuperables**. Sauvegarder cette valeur dans un coffre-fort
> separe (1Password, Bitwarden, papier dans un coffre).

## Utilisation manuelle

```bash
# Charger les variables d env
set -a && source /etc/bichete/backup.env && set +a

# Lancer un backup
bash infra/scripts/backup-postgres.sh

# Lister les dumps existants
aws --endpoint-url "$S3_ENDPOINT" s3 ls "s3://$S3_BUCKET/$S3_PREFIX/"

# Restaurer un dump precis
bash infra/scripts/restore-postgres.sh bichete-thomas-20260508-230000.dump.enc
```

## Planification (cron sur le VPS Hetzner)

```cron
# /etc/cron.d/bichete-backup
# Backup quotidien a 03h00 UTC (04h00 Dakar = 05h00 Paris).
# Les logs vont dans /var/log/bichete-backup.log.
0 3 * * * root . /etc/bichete/backup.env && /opt/bichete/infra/scripts/backup-postgres.sh >> /var/log/bichete-backup.log 2>&1
```

## Test de restauration (a faire au moins **une fois par mois**)

Une sauvegarde non testee = pas de sauvegarde. Procedure :

1. Provisionner un Postgres jetable (autre container Docker, autre projet
   Hetzner, ou local).
2. Pointer les variables `POSTGRES_*` dessus (pas la prod !).
3. Lancer `restore-postgres.sh <nom-du-dump-recent>`.
4. Verifier la coherence : nombre de reservations, dernier paiement, etc.
5. Detruire le Postgres jetable.

## Dependances

Le serveur qui execute ces scripts doit avoir :
- `bash` 4+
- `pg_dump` / `pg_restore` (paquet `postgresql-client-16` sur Debian/Ubuntu)
- `openssl`
- `aws` CLI v2 ([install](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html))

Sur Hetzner Object Storage, generer les credentials via la console projet
(Security > Access Keys), puis tester :

```bash
aws --endpoint-url https://nbg1.your-objectstorage.com s3 ls
```

---

## Images (volume `app_storage`)

Les photos uploadees (coiffures, categories) vivent dans un **volume Docker
nomme** monte dans les conteneurs a `/var/www/html/storage/app/public`. Le
volume survit aux **redeploiements**, mais **pas** a la perte du serveur ni a
une suppression accidentelle du volume — d ou cette sauvegarde hors-serveur.

### Pipeline

```
docker run (tar le volume, lecture seule)  ->  gzip  ->  openssl AES-256  ->  aws s3 cp
```

- **Conteneur jetable** : on lit le volume via `docker run -v <volume>:/data:ro`,
  pas via le chemin host, pour rester portable (driver de stockage, prefixe de
  nom ajoute par Coolify).
- **Streaming + chiffrement client-side** : rien en clair sur le disque.
- **Retention** identique a Postgres (`BACKUP_RETENTION_DAYS`, defaut 30).

### Trouver le nom EXACT du volume

Coolify prefixe souvent le nom (`<projet>_app_storage`). Lister :

```bash
docker volume ls | grep app_storage
# ex: bichette-thomas_app_storage
```

### Variables d environnement

Memes que Postgres pour le S3 et la passphrase, plus :

| Variable | Exemple | Description |
|---|---|---|
| `IMAGES_VOLUME` | `bichette-thomas_app_storage` | Nom exact du volume Docker des images |
| `S3_PREFIX` | `prod/images` | Prefixe S3 dedie aux images |
| `DOCKER_BIN` | `docker` (optionnel) | Ex: `sudo docker` si l utilisateur cron n est pas dans le groupe docker |

> La passphrase `BACKUP_PASSPHRASE` doit etre **la meme** que celle du backup
> Postgres (sinon deux secrets a garder). Sans elle, les archives sont perdues.

### Utilisation manuelle

```bash
set -a && source /etc/bichete/backup.env && set +a

# Sauvegarder les images
IMAGES_VOLUME=bichette-thomas_app_storage S3_PREFIX=prod/images \
  bash infra/scripts/backup-images.sh

# Lister les archives
aws --endpoint-url "$S3_ENDPOINT" s3 ls "s3://$S3_BUCKET/prod/images/"

# Restaurer une archive (ecrase le contenu du volume par defaut)
IMAGES_VOLUME=bichette-thomas_app_storage S3_PREFIX=prod/images \
  bash infra/scripts/restore-images.sh images-20260611-030000.tar.gz.enc
```

### Planification (cron sur le VPS)

```cron
# /etc/cron.d/bichete-backup
# 03h00 UTC : Postgres. 03h15 UTC : images (decale pour ne pas saturer l upload).
0  3 * * * root . /etc/bichete/backup.env && /opt/bichete/infra/scripts/backup-postgres.sh >> /var/log/bichete-backup.log 2>&1
15 3 * * * root . /etc/bichete/backup.env && IMAGES_VOLUME=bichette-thomas_app_storage S3_PREFIX=prod/images /opt/bichete/infra/scripts/backup-images.sh >> /var/log/bichete-backup.log 2>&1
```

### Test de restauration (au moins une fois par mois)

```bash
# 1. Creer un volume jetable
docker volume create test_restore_images

# 2. Restaurer dedans (PAS le volume de prod !)
IMAGES_VOLUME=test_restore_images S3_PREFIX=prod/images \
  bash infra/scripts/restore-images.sh images-XXXXXXXX-XXXXXX.tar.gz.enc

# 3. Verifier le contenu
docker run --rm -v test_restore_images:/data:ro alpine sh -c 'ls -R /data | head'

# 4. Nettoyer
docker volume rm test_restore_images
```

### Dependances supplementaires

En plus de `openssl` et `aws` CLI : **`docker`** doit etre accessible par
l utilisateur qui execute le script (groupe `docker`, ou `DOCKER_BIN="sudo docker"`).

---

## Lanceur unique : `run-backups.sh` (recommande pour le cron)

Au lieu de planifier deux scripts, **`run-backups.sh`** enchaine les deux
sauvegardes (images + base) en lisant un seul fichier d'environnement.

Particularite DB : le dump tourne **a l'interieur du conteneur Postgres**
(`docker exec ... pg_dump`), donc :
- pas besoin d'installer `postgresql-client` sur l'hote ;
- pas besoin de mettre les identifiants DB dans `backup.env` (ils sont lus
  depuis l'env du conteneur) ;
- le `pg_dump` joint bien la base (il tourne dans le reseau Docker).

### Fichier `/etc/bichete/backup.env` (version minimale)

```env
# Secret de chiffrement (genere : openssl rand -base64 32). A garder hors-ligne.
BACKUP_PASSPHRASE=ta_cle_generee

# Backblaze B2 (S3-compatible)
S3_BUCKET=bichette-backups
S3_ENDPOINT=https://s3.eu-central-003.backblazeb2.com
AWS_ACCESS_KEY_ID=ton_keyID
AWS_SECRET_ACCESS_KEY=ton_applicationKey

# Volume Docker des images (docker volume ls | grep app_storage)
IMAGES_VOLUME=bichette-thomas_app_storage

# Optionnel : si l'auto-detection du conteneur postgres echoue
# PG_CONTAINER=bichette-thomas-postgres-1
```

> Les prefixes S3 par defaut sont `prod/images` et `prod/postgres`
> (surchargeables via `S3_PREFIX_IMAGES` / `S3_PREFIX_DB`).

### Lancement manuel

```bash
bash /opt/bichete/infra/scripts/run-backups.sh
# (charge tout seul /etc/bichete/backup.env)
```

### Cron (une seule ligne)

```cron
# /etc/cron.d/bichete-backup
# Tous les jours a 03h00 UTC. Logs dans /var/log/bichete-backup.log.
0 3 * * * root /opt/bichete/infra/scripts/run-backups.sh >> /var/log/bichete-backup.log 2>&1
```

> `run-backups.sh` charge lui-meme `/etc/bichete/backup.env` ; pas besoin de
> `source` dans la ligne cron. Pour un autre emplacement : `BACKUP_ENV_FILE=...`.
