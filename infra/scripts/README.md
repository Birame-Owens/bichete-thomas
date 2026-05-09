# Infra scripts — Backup / Restore PostgreSQL (B8)

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
