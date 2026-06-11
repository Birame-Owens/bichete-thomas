#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Lanceur unique des sauvegardes (pour le cron de l'hote).
#
# Fait tourner, en streaming chiffre vers le bucket S3 :
#   1. Images  : volume Docker app_storage      (via backup-images.sh)
#   2. Base    : PostgreSQL                      (pg_dump DANS le conteneur)
#
# Pourquoi pg_dump dans le conteneur ? Parce que sur l'hote, le nom "postgres"
# n'est joignable que dans le reseau Docker, et l'hote n'a pas forcement le
# client postgresql. On execute donc pg_dump A L'INTERIEUR du conteneur (qui a
# l'outil et joint sa propre base), et on chiffre + envoie cote hote. Bonus :
# les identifiants DB sont lus depuis l'env du conteneur, donc inutile de les
# redupliquer dans backup.env.
#
# Prerequis sur l'hote : docker, openssl, aws (cli v2).
#
# Variables (dans /etc/bichete/backup.env) :
#   BACKUP_PASSPHRASE, S3_BUCKET, S3_ENDPOINT, AWS_ACCESS_KEY_ID,
#   AWS_SECRET_ACCESS_KEY, IMAGES_VOLUME
# Optionnelles :
#   BACKUP_ENV_FILE        -- chemin du fichier env (defaut /etc/bichete/backup.env)
#   S3_PREFIX_IMAGES        -- defaut prod/images
#   S3_PREFIX_DB            -- defaut prod/postgres
#   PG_CONTAINER            -- nom du conteneur postgres (sinon auto-detection)
#   BACKUP_RETENTION_DAYS   -- defaut 30
#   DOCKER_BIN              -- defaut "docker"
# ----------------------------------------------------------------------------

set -euo pipefail

ENV_FILE="${BACKUP_ENV_FILE:-/etc/bichete/backup.env}"
if [ ! -f "$ENV_FILE" ]; then
  echo "ERREUR : fichier d'environnement introuvable : ${ENV_FILE}" >&2
  exit 1
fi
# Charge les variables (export automatique pour les sous-processus).
set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCKER_BIN="${DOCKER_BIN:-docker}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

required_vars=(BACKUP_PASSPHRASE S3_BUCKET S3_ENDPOINT AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY IMAGES_VOLUME)
for v in "${required_vars[@]}"; do
  if [ -z "${!v:-}" ]; then
    echo "ERREUR : variable manquante dans ${ENV_FILE} : ${v}" >&2
    exit 1
  fi
done

# ── 1) Images ───────────────────────────────────────────────────────────────
echo "=================== Sauvegarde IMAGES ==================="
S3_PREFIX="${S3_PREFIX_IMAGES:-prod/images}" bash "${SCRIPT_DIR}/backup-images.sh"

# ── 2) Base de donnees ──────────────────────────────────────────────────────
echo "=================== Sauvegarde BASE ==================="

# Auto-detection du conteneur postgres (image postgres:16-alpine du compose).
# Surchargeable via PG_CONTAINER si tu fais tourner plusieurs Postgres.
PG_CONTAINER="${PG_CONTAINER:-$($DOCKER_BIN ps --filter ancestor=postgres:16-alpine --format '{{.Names}}' | head -n1)}"
if [ -z "$PG_CONTAINER" ]; then
  echo "ERREUR : conteneur Postgres introuvable." >&2
  echo "Astuce : '${DOCKER_BIN} ps --format \"{{.Names}}\" | grep -i postgres', puis renseigne PG_CONTAINER." >&2
  exit 1
fi
echo "[backup-db] conteneur Postgres : ${PG_CONTAINER}"

# Nom de la base lu depuis l'env du conteneur (pas de duplication de secrets).
PG_DB="$($DOCKER_BIN exec "$PG_CONTAINER" printenv POSTGRES_DB)"
DB_PREFIX="${S3_PREFIX_DB:-prod/postgres}"
TIMESTAMP="$(date -u +%Y%m%d-%H%M%S)"
DB_URI="s3://${S3_BUCKET}/${DB_PREFIX}/${PG_DB}-${TIMESTAMP}.dump.enc"

echo "[backup-db] ${PG_DB} -> ${DB_URI}"

# pg_dump s'execute DANS le conteneur (utilise son POSTGRES_USER/DB/PASSWORD),
# la sortie binaire est chiffree puis envoyee cote hote. Streaming pur.
$DOCKER_BIN exec "$PG_CONTAINER" sh -c \
    'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-privileges' \
  | openssl enc -aes-256-cbc -pbkdf2 -iter 100000 -salt -pass env:BACKUP_PASSPHRASE \
  | aws --endpoint-url "$S3_ENDPOINT" s3 cp - "$DB_URI"

echo "[backup-db] upload OK : ${DB_URI}"

# Retention DB (filtre cote client, portable tous providers S3-compatibles).
THRESHOLD="$(date -u -d "${RETENTION_DAYS} days ago" '+%Y-%m-%dT%H:%M:%SZ')"
echo "[backup-db] purge des dumps anterieurs a ${THRESHOLD}"
aws --endpoint-url "$S3_ENDPOINT" s3api list-objects-v2 \
    --bucket "$S3_BUCKET" \
    --prefix "${DB_PREFIX}/" \
    --query "Contents[?LastModified<'${THRESHOLD}'].Key" \
    --output text \
  | tr '\t' '\n' \
  | while IFS= read -r key; do
      [ -n "$key" ] || continue
      [ "$key" = "None" ] && continue
      echo "[backup-db] suppression : ${key}"
      aws --endpoint-url "$S3_ENDPOINT" s3 rm "s3://${S3_BUCKET}/${key}"
    done

echo "=================== Sauvegardes TERMINEES OK ==================="
