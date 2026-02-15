#!/bin/bash
set -e

echo "========================================="
echo "ArchiMeuble Backend - Startup"
echo "========================================="

# Variables
PORT="${PORT:-8000}"
DATABASE_URL="${DATABASE_URL}"

echo "PORT: $PORT"
echo ""

# Créer les répertoires nécessaires
mkdir -p /data
mkdir -p /data/uploads/models
mkdir -p /data/models
mkdir -p /data/sessions
mkdir -p /data/backups
chmod -R 777 /data

echo "Directories created: /data, /data/uploads, /data/models, /data/sessions, /data/backups"

# Attendre que PostgreSQL soit prêt
echo ""
echo "Waiting for PostgreSQL..."
echo "DATABASE_URL set: $([ -n "$DATABASE_URL" ] && echo 'YES' || echo 'NO')"
MAX_RETRIES=15
RETRY_COUNT=0
while ! psql "$DATABASE_URL" -c "SELECT 1" > /dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ "$RETRY_COUNT" -ge "$MAX_RETRIES" ]; then
        echo "PostgreSQL not ready after $MAX_RETRIES attempts. Starting anyway..."
        break
    fi
    echo "  Waiting for PostgreSQL... (attempt $RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done
echo "PostgreSQL is ready"

# Initialiser le schéma de base
echo ""
echo "Initializing database schema..."
psql "$DATABASE_URL" -f /app/init_db.sql 2>&1 || echo "WARNING: Some schema initialization errors (may be normal if tables exist)"
echo "Database schema initialized"

# Vérifier que toutes les tables existent avec le script Python
echo ""
echo "Ensuring all tables exist with Python..."
if python3 /app/create_missing_tables.py; then
    echo "Tables verified successfully"
else
    echo "Table verification script failed (non-fatal, continuing...)"
fi

# Installer le cron de backup automatique
echo ""
echo "Setting up automated backup cron job..."
if ! crontab -l 2>/dev/null | grep -q "backup-database.sh"; then
    echo "0 3 * * * /usr/local/bin/backup-database.sh >> /data/backup-cron.log 2>&1" | crontab -
    echo "Backup cron job installed (runs daily at 3:00 AM)"
else
    echo "Backup cron job already installed"
fi

# Démarrer le service cron en arrière-plan
echo "Starting cron service..."
if cron 2>/dev/null; then
    echo "Cron service started"
else
    echo "Cron service could not start (non-fatal)"
fi

# Démarrer le serveur PHP
echo ""
echo "Starting PHP server on port $PORT..."
echo "Sessions stored in: /data/sessions"
echo "========================================="
exec php -d session.save_path=/data/sessions -S 0.0.0.0:$PORT router.php
