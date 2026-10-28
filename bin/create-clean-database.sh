#!/bin/bash
# Crée une nouvelle base à partir de l'ancienne, sans ce qui ne sert plus :
# copie complète de l'ancienne base dans une base vide, puis migrations (dont 0007, qui retire les tables
# inutilisées et l'ancienne grille de prix avec le verre). L'ancienne base n'est jamais modifiée.
#
# Usage : bin/create-clean-database.sh <URL de l'ancienne base> <URL de la nouvelle base, vide>
# Ensuite, faire pointer DATABASE_URL vers la nouvelle base (Railway : variable du service backend).

set -euo pipefail

SOURCE_URL="${1:-}"
TARGET_URL="${2:-}"
if [ -z "$SOURCE_URL" ] || [ -z "$TARGET_URL" ]; then
    echo "Usage : $0 <URL de l'ancienne base> <URL de la nouvelle base, vide>" >&2
    exit 1
fi
if [ "$SOURCE_URL" = "$TARGET_URL" ]; then
    echo "[ERREUR] La nouvelle base doit être différente de l'ancienne" >&2
    exit 1
fi

TARGET_TABLES=$(psql "$TARGET_URL" -At -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
if [ "$TARGET_TABLES" != "0" ]; then
    echo "[ERREUR] La nouvelle base contient déjà $TARGET_TABLES table(s) : rien n'est copié" >&2
    exit 1
fi

echo "Lignes des tables qui ne seront pas reprises (ancienne base) :"
for table in clients projets devis sessions templates saved_configurations saved_facades pricing; do
    if [ "$(psql "$SOURCE_URL" -At -c "SELECT to_regclass('public.$table') IS NOT NULL")" = "t" ]; then
        echo "  $table : $(psql "$SOURCE_URL" -At -c "SELECT count(*) FROM $table")"
    fi
done

echo "Copie de l'ancienne base..."
# pg_dump 17 écrit « SET transaction_timeout », inconnu d'un serveur PostgreSQL 16
pg_dump --no-owner --no-privileges "$SOURCE_URL" | sed '/^SET transaction_timeout/d' | psql "$TARGET_URL" --quiet --set ON_ERROR_STOP=1 > /dev/null

echo "Migrations sur la nouvelle base..."
DATABASE_URL="$TARGET_URL" php "$(dirname "$0")/console" migrate

echo "[OK] Nouvelle base prête : $(psql "$TARGET_URL" -At -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'") tables,"\
     "$(psql "$TARGET_URL" -At -c "SELECT count(*) FROM pricing_config") paramètres de prix"
