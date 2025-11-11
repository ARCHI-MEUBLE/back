#!/bin/bash
set -e

echo "========================================="
echo "ArchiMeuble Backend - Railway Startup"
echo "========================================="

# Variables
DB_PATH="${DB_PATH:-/data/archimeuble_test.db}"
PORT="${PORT:-8000}"

echo "DB_PATH: $DB_PATH"
echo "PORT: $PORT"
echo ""

# Créer le répertoire /data si nécessaire
mkdir -p /data
mkdir -p /data/uploads/models
chmod -R 777 /data

echo "✓ /data directory created and writable"

# Initialiser la base de données SEULEMENT si elle n'existe pas
if [ ! -f "$DB_PATH" ]; then
    echo ""
    echo "Database not found. Initializing new database..."
    export DB_PATH="$DB_PATH"
    /usr/local/bin/init_db.sh
else
    echo ""
    echo "✓ Existing database found: $DB_PATH"
    echo "  Size: $(du -h $DB_PATH | cut -f1)"
    echo "  Skipping initialization to preserve data"
fi

# Toujours vérifier que toutes les tables existent (ne supprime pas les données)
echo ""
echo "Ensuring all tables exist with Python..."
python3 /app/create_missing_tables.py

# Créer le répertoire pour les sessions PHP
mkdir -p /data/sessions
chmod 777 /data/sessions

# Démarrer le serveur PHP avec les sessions dans /data
echo ""
echo "Starting PHP server on port $PORT..."
echo "Sessions stored in: /data/sessions"
echo "========================================="
exec php -d session.save_path=/data/sessions -S 0.0.0.0:$PORT router.php
