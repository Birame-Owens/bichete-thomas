#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Restauration des images depuis une archive chiffree du bucket S3-compatible.
#
# Pipeline inverse de backup-images.sh :
#   aws s3 cp  ->  openssl decrypt AES-256  ->  docker run (tar extract dans le volume)
#
# /!\ Par defaut, on PURGE le contenu du volume avant d extraire (restauration
#     fidele a l instant du backup). Toujours tester sur un volume jetable avant
#     de restaurer en prod.
#
# Usage :
#   ./restore-images.sh <nom-de-l-archive>
#   Exemple : ./restore-images.sh images-20260611-030000.tar.gz.enc
#
# Pour lister les archives disponibles :
#   aws --endpoint-url "$S3_ENDPOINT" s3 ls "s3://$S3_BUCKET/$S3_PREFIX/"
#
# Variables d environnement requises : memes que backup-images.sh
# Variable optionnelle :
#   RESTORE_KEEP_EXISTING=1  -- n efface pas le volume, fusionne les fichiers
# ----------------------------------------------------------------------------

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "Usage : $0 <nom-de-l-archive>" >&2
  echo "Exemple : $0 images-20260611-030000.tar.gz.enc" >&2
  exit 1
fi

ARCHIVE_NAME="$1"

required_vars=(
  IMAGES_VOLUME
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

DOCKER_BIN="${DOCKER_BIN:-docker}"
S3_URI="s3://${S3_BUCKET}/${S3_PREFIX}/${ARCHIVE_NAME}"

# Le volume cible doit exister (le creer si besoin : `docker volume create ...`).
if ! $DOCKER_BIN volume inspect "$IMAGES_VOLUME" >/dev/null 2>&1; then
  echo "ERREUR : volume Docker cible introuvable : ${IMAGES_VOLUME}" >&2
  exit 1
fi

# Par defaut on vide le volume avant restauration (sauf RESTORE_KEEP_EXISTING=1).
if [ "${RESTORE_KEEP_EXISTING:-0}" = "1" ]; then
  PRE_EXTRACT=":"
  echo "[restore-images] fusion (le contenu existant est conserve)"
else
  PRE_EXTRACT='rm -rf /data/* /data/.[!.]* 2>/dev/null || true'
  echo "[restore-images] /!\\ le contenu actuel du volume va etre efface puis remplace"
fi

echo "[restore-images] telechargement et restauration : ${S3_URI}"

# Pipeline en streaming : aucun fichier intermediaire en clair sur le disque.
# Si le dechiffrement echoue (mauvaise passphrase), tar s arrete en erreur et le
# `set -e` du sh interne empeche une extraction partielle silencieuse.
aws --endpoint-url "$S3_ENDPOINT" s3 cp "$S3_URI" - \
  | openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -pass env:BACKUP_PASSPHRASE \
  | $DOCKER_BIN run --rm -i \
      -v "${IMAGES_VOLUME}":/data \
      alpine sh -c "set -e; ${PRE_EXTRACT}; tar -C /data -xzf -"

echo "[restore-images] termine OK"
echo "[restore-images] pense a verifier l affichage des photos sur le site."
