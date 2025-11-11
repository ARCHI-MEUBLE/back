#!/bin/bash
# Script pour exécuter la migration des échantillons sur Railway
# Usage: ./run_migration.sh

set -e  # Arrêter en cas d'erreur

echo "🚀 Exécution de la migration des échantillons..."

# Vérifier si on est sur Railway
if [ -z "$RAILWAY_ENVIRONMENT" ]; then
    echo "⚠️  Ce script doit être exécuté sur Railway"
    echo "Utiliser: railway run bash backend/database/migrations/run_migration.sh"
    exit 1
fi

# Chemin de la base de données
DB_PATH="${DB_PATH:-/data/archimeuble.db}"

echo "📂 Base de données: $DB_PATH"

# Vérifier que la base existe
if [ ! -f "$DB_PATH" ]; then
    echo "❌ Erreur: Base de données non trouvée à $DB_PATH"
    exit 1
fi

# Exécuter la migration
echo "🔄 Application de la migration create_sample_orders.sql..."
sqlite3 "$DB_PATH" < backend/database/migrations/create_sample_orders.sql

if [ $? -eq 0 ]; then
    echo "✅ Migration appliquée avec succès!"
    echo "📊 Vérification des tables créées:"
    sqlite3 "$DB_PATH" "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%sample%' ORDER BY name;"
else
    echo "❌ Erreur lors de l'application de la migration"
    exit 1
fi

echo "✨ Migration terminée!"
