#!/usr/bin/env python3
"""
Script de migration pour ajouter les colonnes category et config_data à la table models
"""
import sqlite3
import os
import sys

DB_PATH = os.getenv('DB_PATH', '/data/archimeuble_dev.db')

print(f"Connexion à la base de données: {DB_PATH}")

if not os.path.exists(DB_PATH):
    print(f"ERREUR: Base de données introuvable à {DB_PATH}")
    sys.exit(1)

try:
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    print("\nVérification des colonnes dans models...")
    cursor.execute("PRAGMA table_info(models)")
    columns = {col[1]: col for col in cursor.fetchall()}

    print(f"Colonnes actuelles: {list(columns.keys())}")

    # Ajouter category si elle n'existe pas
    if 'category' not in columns:
        print("Ajout de la colonne category...")
        cursor.execute("ALTER TABLE models ADD COLUMN category TEXT")
        print("✓ Colonne category ajoutée avec succès!")
    else:
        print("✓ Colonne category existe déjà")

    # Ajouter config_data si elle n'existe pas
    if 'config_data' not in columns:
        print("Ajout de la colonne config_data...")
        cursor.execute("ALTER TABLE models ADD COLUMN config_data TEXT")
        print("✓ Colonne config_data ajoutée avec succès!")
    else:
        print("✓ Colonne config_data existe déjà")

    conn.commit()
    print("\n✓ Migration terminée avec succès!")
    conn.close()

except Exception as e:
    print(f"ERREUR: {e}")
    sys.exit(1)
