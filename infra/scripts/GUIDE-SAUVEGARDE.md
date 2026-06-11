# 🗄️ Guide de sauvegarde — Bichette Thomas

Recap concret de la mise en place des sauvegardes (base de donnees + images)
vers **Backblaze B2**, et de la procedure de restauration.

> Pour la doc technique des scripts, voir [`README.md`](./README.md).

---

## 1. Architecture

| Element | Donnees live (sur le serveur) | Sauvegarde (hors serveur) |
|---|---|---|
| **Base de donnees** | Volume Docker `..._postgres-data` | Backblaze B2, dossier `prod/postgres/` |
| **Images** (photos coiffures) | Volume Docker `..._app-storage` | Backblaze B2, dossier `prod/images/` |

Les volumes Docker survivent aux redeploiements Coolify, **mais pas** a la perte
du serveur. D'ou la copie **chiffree** chez Backblaze B2 (S3-compatible).

Le nom exact du volume images est prefixe par Coolify, le trouver avec :

```bash
docker volume ls | grep app-storage    # ex: g14541noq2pp1z9kphr9nou5_app-storage
```

---

## 2. Le bucket Backblaze B2

1. Compte sur backblaze.com -> **B2 Cloud Storage**.
2. Creer un **Bucket** prive (ex: `bichette-backups`).
3. **Application Keys** -> nouvelle cle restreinte au bucket -> noter **keyID** + **applicationKey**.
4. Noter l'**endpoint** du bucket (ex: `https://s3.eu-central-003.backblazeb2.com`).

---

## 3. Configuration serveur

### Secrets : `/etc/bichete/backup.env` (hote, `chmod 600`)

```env
BACKUP_PASSPHRASE=<cle aleatoire : openssl rand -base64 32>
S3_BUCKET=bichette-backups
S3_ENDPOINT=https://s3.eu-central-003.backblazeb2.com
AWS_ACCESS_KEY_ID=<keyID Backblaze>
AWS_SECRET_ACCESS_KEY=<applicationKey Backblaze>
IMAGES_VOLUME=<nom du volume, ex: g14541noq2pp1z9kphr9nou5_app-storage>
```

> Ces variables vont sur **l'hote** (le cron les lit), **PAS** dans l'env du
> projet Coolify (qui n'est visible que des conteneurs de l'app).

### Outils

```bash
apt-get update && apt-get install -y git openssl curl unzip
# aws CLI v2 (le paquet apt n'existe plus sur Ubuntu 24.04) :
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o /tmp/awscliv2.zip
cd /tmp && unzip -q awscliv2.zip && ./aws/install
aws --version
```

### Scripts

```bash
git clone https://github.com/Birame-Owens/bichete-thomas.git /opt/bichete
```

---

## 4. Sauvegarde

Un seul script : **`/opt/bichete/infra/scripts/run-backups.sh`**

- **Images** : archive le volume via un conteneur jetable -> gzip -> chiffre AES-256 -> Backblaze.
- **Base** : `pg_dump` execute *dans* le conteneur Postgres (auto-detecte) -> chiffre -> Backblaze.
- **Retention** : 30 jours (purge automatique des fichiers plus anciens).

### Lancement manuel

```bash
bash /opt/bichete/infra/scripts/run-backups.sh
# Doit finir par : === Sauvegardes TERMINEES OK ===
```

### Automatisation (cron quotidien 03h UTC)

```bash
cat > /etc/cron.d/bichete-backup <<'EOF'
PATH=/usr/local/bin:/usr/bin:/bin
0 3 * * * root /opt/bichete/infra/scripts/run-backups.sh >> /var/log/bichete-backup.log 2>&1
EOF
```

> Le `PATH` est indispensable : en cron il ne contient pas `/usr/local/bin`
> (ou se trouve `aws`), sinon erreur `aws: command not found`.

Suivre les logs : `tail -f /var/log/bichete-backup.log` (cree au 1er passage du cron).

---

## 5. Verifier qu'une sauvegarde est valide (sans tout restaurer)

```bash
set -a; . /etc/bichete/backup.env; set +a

# Base -> doit afficher "PGDMP" (signature d'un dump Postgres)
aws --endpoint-url "$S3_ENDPOINT" s3 cp "s3://$S3_BUCKET/prod/postgres/<FICHIER>.dump.enc" - \
  | openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -pass env:BACKUP_PASSPHRASE \
  | head -c 5; echo

# Images -> doit lister des fichiers
aws --endpoint-url "$S3_ENDPOINT" s3 cp "s3://$S3_BUCKET/prod/images/<FICHIER>.tar.gz.enc" - \
  | openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -pass env:BACKUP_PASSPHRASE \
  | tar -tzf - | head
```

`bad decrypt` / `bad magic number` = la passphrase ne correspond pas.

---

## 6. Restauration

### Lister les sauvegardes disponibles

```bash
set -a; . /etc/bichete/backup.env; set +a
aws --endpoint-url "$S3_ENDPOINT" s3 ls "s3://$S3_BUCKET/prod/postgres/"
aws --endpoint-url "$S3_ENDPOINT" s3 ls "s3://$S3_BUCKET/prod/images/"
```

### Images

```bash
IMAGES_VOLUME=<ton_volume> S3_PREFIX=prod/images \
  bash /opt/bichete/infra/scripts/restore-images.sh images-AAAAMMJJ-HHMMSS.tar.gz.enc
```

Par defaut le contenu du volume est ecrase. `RESTORE_KEEP_EXISTING=1` pour fusionner.

### Base de donnees (/!\\ destructif : ecrase la base actuelle)

```bash
set -a; . /etc/bichete/backup.env; set +a
PG=$(docker ps --filter ancestor=postgres:16-alpine --format '{{.Names}}' | head -n1)

aws --endpoint-url "$S3_ENDPOINT" s3 cp "s3://$S3_BUCKET/prod/postgres/bichette_thomas-AAAAMMJJ-HHMMSS.dump.enc" - \
  | openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -pass env:BACKUP_PASSPHRASE \
  | docker exec -i "$PG" sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges'
```

### Test de restauration sans risque (recommande 1x/mois)

Restaurer dans une cible **jetable** plutot qu'en prod :

```bash
# Volume images jetable
docker volume create test_restore_images
IMAGES_VOLUME=test_restore_images S3_PREFIX=prod/images \
  bash /opt/bichete/infra/scripts/restore-images.sh images-AAAAMMJJ-HHMMSS.tar.gz.enc
docker run --rm -v test_restore_images:/data:ro alpine sh -c 'ls -R /data | head'
docker volume rm test_restore_images
```

---

## 7. Regles d'or

- 🔑 **`BACKUP_PASSPHRASE` = la cle qui dechiffre tout.** A garder **hors du
  serveur** (gestionnaire de mots de passe). **Perdue = sauvegardes
  irrecuperables.** Ne jamais la changer (sinon les anciens backups deviennent
  illisibles).
- Les fichiers `.enc` sont **illisibles directement** (chiffres) : c'est normal,
  on les **restaure**, on ne les « lit » pas.
- Verifier de temps en temps que les fichiers du jour apparaissent dans Backblaze.
- Une sauvegarde **jamais testee** = pas de sauvegarde : faire un test de
  restauration de temps en temps.
