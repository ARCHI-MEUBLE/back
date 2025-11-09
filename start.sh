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

# Initialiser la base de données
echo ""
echo "Initializing database..."
export DB_PATH="$DB_PATH"
/usr/local/bin/init_db.sh

# Vérifier que la DB existe
if [ -f "$DB_PATH" ]; then
    echo "✓ Database initialized: $DB_PATH"
    echo "  Size: $(du -h $DB_PATH | cut -f1)"

    # Fix: Créer les tables manquantes (calendly_appointments, notifications)
    echo ""
    echo "Ensuring all tables exist..."

    if [ -f "/app/init_missing_tables.sql" ]; then
        sqlite3 "$DB_PATH" < /app/init_missing_tables.sql
        echo "✓ Missing tables created from init_missing_tables.sql"
    else
        echo "⚠ init_missing_tables.sql not found, using inline SQL"
        sqlite3 "$DB_PATH" <<'FIXSQL'
CREATE TABLE IF NOT EXISTS calendly_appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendly_event_id TEXT UNIQUE NOT NULL,
    customer_email TEXT NOT NULL,
    customer_name TEXT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    event_type TEXT,
    location TEXT,
    status TEXT DEFAULT 'scheduled',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE
);
FIXSQL
    fi

    echo "✓ All tables verified"
else
    echo "✗ WARNING: Database file not created!"
fi

# Créer le répertoire pour les sessions PHP
mkdir -p /data/sessions
chmod 777 /data/sessions

# Démarrer le serveur PHP avec les sessions dans /data
echo ""
echo "Starting PHP server on port $PORT..."
echo "Sessions stored in: /data/sessions"
echo "========================================="
exec php -d session.save_path=/data/sessions -S 0.0.0.0:$PORT router.php
