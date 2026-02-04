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

# URL de connexion PostgreSQL
DATABASE_URL="${DATABASE_URL:?DATABASE_URL is required}"

echo "📂 Base de données: PostgreSQL"

# Exécuter la migration
echo "🔄 Application de la migration create_sample_orders.sql..."
psql "$DATABASE_URL" < backend/database/migrations/create_sample_orders.sql

if [ $? -eq 0 ]; then
    echo "✅ Migration appliquée avec succès!"
    echo "📊 Vérification des tables créées:"
    psql "$DATABASE_URL" -c "SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE '%sample%' ORDER BY table_name;"
else
    echo "❌ Erreur lors de l'application de la migration"
    exit 1
fi

echo "✨ Migration terminée!"
