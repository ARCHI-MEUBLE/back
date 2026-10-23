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
mkdir -p /data/uploads/quote-requests
chmod 755 /data /data/uploads /data/models /data/uploads/models /data/uploads/quote-requests
chmod 700 /data/backups /data/sessions

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

# Appliquer les migrations versionnées (src/Db/migrations)
echo ""
echo "Applying database migrations..."
if ! php /app/bin/console migrate; then
    echo "ERROR: database migrations failed, aborting startup"
    exit 1
fi

# Installer le cron de backup automatique
echo ""
echo "Setting up automated backup cron job..."
# cron ne transmet pas l'environnement du conteneur : DATABASE_URL est écrit dans un fichier lisible par root seul
umask 077
printf 'export DATABASE_URL=%q\n' "$DATABASE_URL" > /etc/archimeuble-cron.env
umask 022
echo "0 3 * * * . /etc/archimeuble-cron.env && /usr/local/bin/backup-database.sh >> /data/backup-cron.log 2>&1" | crontab -
echo "Backup cron job installed (runs daily at 3:00 AM)"

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
# Le serveur intégré traite une requête à la fois sans workers : une génération 3D bloquerait tout le site
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
# Sans ces options, PHP garde ses valeurs par défaut (2 Mo par fichier, 8 Mo par requête) et ignore les photos plus lourdes
exec php -d session.save_path=/data/sessions -d upload_max_filesize=20M -d post_max_size=60M -S 0.0.0.0:$PORT public/index.php
