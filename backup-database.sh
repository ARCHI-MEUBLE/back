#!/bin/bash
# Script de backup automatique de la base de données
# À exécuter via cron tous les jours à 3h du matin

set -e

# Variables
DB_PATH="${DB_PATH:-/data/archimeuble_test.db}"
BACKUP_DIR="/data/backups"
MAX_BACKUPS=30

# Créer le dossier de backup si nécessaire
mkdir -p "$BACKUP_DIR"

# Nom du fichier de backup avec timestamp
BACKUP_DATE=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_FILE="$BACKUP_DIR/database-backup-$BACKUP_DATE.db"

echo "[$(date)] Starting database backup..."

# Vérifier que la base existe
if [ ! -f "$DB_PATH" ]; then
    echo "[ERROR] Database file not found: $DB_PATH"
    exit 1
fi

# Créer le backup
cp "$DB_PATH" "$BACKUP_FILE"

# Vérifier que le backup a réussi
if [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "[SUCCESS] Backup created: $BACKUP_FILE (size: $BACKUP_SIZE)"
else
    echo "[ERROR] Backup failed"
    exit 1
fi

# Supprimer les anciens backups (garder seulement les 30 derniers)
BACKUP_COUNT=$(ls -1 "$BACKUP_DIR"/database-backup-*.db 2>/dev/null | wc -l)

if [ "$BACKUP_COUNT" -gt "$MAX_BACKUPS" ]; then
    echo "Cleaning old backups (keeping last $MAX_BACKUPS)..."
    ls -1t "$BACKUP_DIR"/database-backup-*.db | tail -n +$((MAX_BACKUPS + 1)) | xargs rm -f
    echo "Old backups cleaned"
fi

echo "[$(date)] Backup completed successfully"
