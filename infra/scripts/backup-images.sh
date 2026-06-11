#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Sauvegarde chiffree du volume des images (storage/app/public) vers un bucket
# S3-compatible. Pendant local du backup PostgreSQL : meme bucket, meme
# chiffrement, meme logique de retention.
#
# Pipeline :
#   docker run (tar le volume)  ->  gzip  ->  openssl AES-256  ->  aws s3 cp
#
# Les images uploadees (coiffures, categories...) vivent dans un volume Docker
# NOMME (app_storage), monte dans les conteneurs a
#   /var/www/html/storage/app/public
# Le volume survit aux redeploiements, mais PAS a la perte du serveur ni a une
# suppression accidentelle du volume. D ou cette sauvegarde hors-serveur.
#
# On lit le volume via un conteneur jetable monte en lecture seule, plutot que
# via le chemin host (/var/lib/docker/volumes/...), pour rester portable quel
# que soit le driver de stockage Docker ou le prefixe de nom ajoute par Coolify.
#
# Variables d environnement requises (cf infra/scripts/README.md) :
#   IMAGES_VOLUME          -- nom EXACT du volume Docker des images
#                             (ex: bichette-thomas_app_storage ; voir `docker volume ls`)
#   BACKUP_PASSPHRASE      -- cle AES-256 (MEME que le backup Postgres, a garder hors-ligne)
#   S3_BUCKET, S3_PREFIX   -- ex: bichete-backups, prod/images
#   S3_ENDPOINT            -- ex: https://nbg1.your-objectstorage.com (Hetzner)
#   AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY
# Variables optionnelles :
#   BACKUP_RETENTION_DAYS  -- defaut 30 ; les archives plus vieilles sont supprimees
#   DOCKER_BIN             -- defaut "docker" (ex: "sudo docker" si besoin)
# ----------------------------------------------------------------------------

set -euo pipefail

# Verification stricte des variables requises pour echouer tot et clairement.
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

RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
DOCKER_BIN="${DOCKER_BIN:-docker}"

# Refuse de continuer si le volume n existe pas : evite d uploader une archive
# vide qui ecraserait (en retention) une vraie sauvegarde par une coquille.
if ! $DOCKER_BIN volume inspect "$IMAGES_VOLUME" >/dev/null 2>&1; then
  echo "ERREUR : volume Docker introuvable : ${IMAGES_VOLUME}" >&2
  echo "Astuce : lister les volumes avec '${DOCKER_BIN} volume ls'." >&2
  exit 1
fi

# Nom de l archive : images-YYYYMMDD-HHMMSS.tar.gz.enc
# Timestamp UTC -> tri lexicographique = tri chronologique (simplifie la purge).
TIMESTAMP="$(date -u +%Y%m%d-%H%M%S)"
ARCHIVE_NAME="images-${TIMESTAMP}.tar.gz.enc"
S3_URI="s3://${S3_BUCKET}/${S3_PREFIX}/${ARCHIVE_NAME}"

echo "[backup-images] demarrage : ${ARCHIVE_NAME} -> ${S3_URI}"

# Pipeline en streaming : rien n est ecrit en clair sur le disque local.
# - conteneur alpine jetable, volume monte en lecture seule (:ro)
# - tar -C /data . : archive le CONTENU du volume (chemins relatifs)
# - openssl : chiffrement client-side (illisible sans la passphrase)
# set -o pipefail propage l echec de n importe quelle etape.
$DOCKER_BIN run --rm -i \
    -v "${IMAGES_VOLUME}":/data:ro \
    alpine sh -c 'tar -C /data -czf - .' \
  | openssl enc -aes-256-cbc -pbkdf2 -iter 100000 -salt -pass env:BACKUP_PASSPHRASE \
  | aws --endpoint-url "$S3_ENDPOINT" s3 cp - "$S3_URI"

echo "[backup-images] upload OK : ${S3_URI}"

# ----------------------------------------------------------------------------
# Retention : suppression des archives anterieures a $RETENTION_DAYS.
# Filtre cote client (portable sur tous les providers S3-compatibles).
# ----------------------------------------------------------------------------
THRESHOLD="$(date -u -d "${RETENTION_DAYS} days ago" '+%Y-%m-%dT%H:%M:%SZ')"

echo "[backup-images] purge des archives anterieures a ${THRESHOLD}"

aws --endpoint-url "$S3_ENDPOINT" s3api list-objects-v2 \
    --bucket "$S3_BUCKET" \
    --prefix "${S3_PREFIX}/" \
    --query "Contents[?LastModified<'${THRESHOLD}'].Key" \
    --output text \
  | tr '\t' '\n' \
  | while IFS= read -r key; do
      [ -n "$key" ] || continue
      [ "$key" = "None" ] && continue
      echo "[backup-images] suppression : ${key}"
      aws --endpoint-url "$S3_ENDPOINT" s3 rm "s3://${S3_BUCKET}/${key}"
    done

echo "[backup-images] termine OK"
