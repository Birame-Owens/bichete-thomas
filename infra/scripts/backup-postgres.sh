#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Sauvegarde chiffree de la base PostgreSQL vers un bucket S3-compatible (B8).
#
# Pipeline :
#   pg_dump -Fc  ->  openssl AES-256  ->  aws s3 cp
#
# Le format -Fc (custom) est compresse nativement et permet une restauration
# selective. Le chiffrement cote client (openssl) protege le dump meme si les
# credentials S3 fuient ou si le provider est compromis : sans la passphrase,
# l attaquant n a qu un blob illisible.
#
# Variables d environnement requises (cf infra/scripts/README.md) :
#   POSTGRES_HOST, POSTGRES_PORT, POSTGRES_DB, POSTGRES_USER, PGPASSWORD
#   BACKUP_PASSPHRASE      -- cle AES-256 (a conserver hors-ligne, sinon plus de restore possible)
#   S3_BUCKET, S3_PREFIX   -- ex: bichete-backups, prod/postgres
#   S3_ENDPOINT            -- ex: https://nbg1.your-objectstorage.com (Hetzner)
#   AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY
# Variables optionnelles :
#   BACKUP_RETENTION_DAYS  -- defaut 30 ; les dumps plus vieux sont supprimes
# ----------------------------------------------------------------------------

set -euo pipefail

# Verification stricte des variables requises pour echouer tot et clairement.
required_vars=(
  POSTGRES_HOST POSTGRES_PORT POSTGRES_DB POSTGRES_USER PGPASSWORD
  BACKUP_PASSPHRASE
  S3_BUCKET S3_PREFIX S3_ENDPOINT
  AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY
)
for v in "${required_vars[@]}"; do
  if [ -z "${!v:-}" ]; then
    echo "ERREUR : variable d environnement manquante : $v" >&2
    exit 1
  fi
done

RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"

# Nom du dump : {db}-YYYYMMDD-HHMMSS.dump.enc
# Le timestamp en UTC garantit un tri lexicographique = tri chronologique,
# ce qui simplifie la suppression des anciens dumps.
TIMESTAMP="$(date -u +%Y%m%d-%H%M%S)"
DUMP_NAME="${POSTGRES_DB}-${TIMESTAMP}.dump.enc"
S3_URI="s3://${S3_BUCKET}/${S3_PREFIX}/${DUMP_NAME}"

echo "[backup] demarrage : ${DUMP_NAME} -> ${S3_URI}"

# Pipeline en streaming : aucune ecriture sur le disque local.
# Ca evite de saturer le disque du serveur et reduit la fenetre ou le dump
# clair existerait quelque part. Si une etape echoue, set -o pipefail propage.
pg_dump \
  --host="$POSTGRES_HOST" \
  --port="$POSTGRES_PORT" \
  --username="$POSTGRES_USER" \
  --dbname="$POSTGRES_DB" \
  --format=custom \
  --no-owner \
  --no-privileges \
  | openssl enc -aes-256-cbc -pbkdf2 -iter 100000 -salt -pass env:BACKUP_PASSPHRASE \
  | aws --endpoint-url "$S3_ENDPOINT" s3 cp - "$S3_URI" \
      --expected-size 5368709120  # ~5 Go ; aide multipart, augmenter si la base grossit

echo "[backup] upload OK : ${S3_URI}"

# ----------------------------------------------------------------------------
# Retention : suppression des dumps anterieurs a $RETENTION_DAYS.
# On utilise s3api list-objects-v2 + filtre cote client : c est moins elegant
# qu une lifecycle rule cote bucket, mais portable sur tous les providers
# S3-compatibles (Hetzner ne supporte pas toujours les lifecycle rules).
# ----------------------------------------------------------------------------
THRESHOLD="$(date -u -d "${RETENTION_DAYS} days ago" '+%Y-%m-%dT%H:%M:%SZ')"

echo "[backup] purge des dumps anterieurs a ${THRESHOLD}"

aws --endpoint-url "$S3_ENDPOINT" s3api list-objects-v2 \
    --bucket "$S3_BUCKET" \
    --prefix "${S3_PREFIX}/" \
    --query "Contents[?LastModified<'${THRESHOLD}'].Key" \
    --output text \
  | tr '\t' '\n' \
  | while IFS= read -r key; do
      [ -n "$key" ] || continue
      [ "$key" = "None" ] && continue
      echo "[backup] suppression : ${key}"
      aws --endpoint-url "$S3_ENDPOINT" s3 rm "s3://${S3_BUCKET}/${key}"
    done

echo "[backup] termine OK"
