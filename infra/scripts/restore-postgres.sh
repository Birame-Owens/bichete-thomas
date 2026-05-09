#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Restauration d un dump PostgreSQL chiffre depuis le bucket S3-compatible (B8).
#
# Pipeline inverse de backup-postgres.sh :
#   aws s3 cp  ->  openssl decrypt AES-256  ->  pg_restore
#
# /!\ pg_restore avec --clean DROP les tables existantes avant de recreer.
#     Toujours tester sur un environnement de staging avant de restaurer en prod.
#
# Usage :
#   ./restore-postgres.sh <nom-du-dump>
#   Exemple : ./restore-postgres.sh bichete-thomas-20260508-230000.dump.enc
#
# Pour lister les dumps disponibles :
#   aws --endpoint-url "$S3_ENDPOINT" s3 ls "s3://$S3_BUCKET/$S3_PREFIX/"
#
# Variables d environnement requises : memes que backup-postgres.sh
# ----------------------------------------------------------------------------

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "Usage : $0 <nom-du-dump>" >&2
  echo "Exemple : $0 bichete-thomas-20260508-230000.dump.enc" >&2
  exit 1
fi

DUMP_NAME="$1"

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

S3_URI="s3://${S3_BUCKET}/${S3_PREFIX}/${DUMP_NAME}"

echo "[restore] telechargement et restauration : ${S3_URI}"
echo "[restore] /!\\ les donnees existantes vont etre ecrasees (--clean --if-exists)"

# Pipeline en streaming : aucun fichier intermediaire en clair sur le disque.
# Si l etape de dechiffrement echoue (mauvaise passphrase), pg_restore s arrete
# avec une erreur au lieu de detruire la base.
aws --endpoint-url "$S3_ENDPOINT" s3 cp "$S3_URI" - \
  | openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -pass env:BACKUP_PASSPHRASE \
  | pg_restore \
      --host="$POSTGRES_HOST" \
      --port="$POSTGRES_PORT" \
      --username="$POSTGRES_USER" \
      --dbname="$POSTGRES_DB" \
      --clean \
      --if-exists \
      --no-owner \
      --no-privileges \
      --exit-on-error

echo "[restore] termine OK"
