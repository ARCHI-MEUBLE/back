#!/usr/bin/env python3
"""
Script pour créer les tables manquantes dans la base de données ArchiMeuble
"""
import sqlite3
import os
import sys

DB_PATH = os.getenv('DB_PATH', '/data/archimeuble_test.db')

print(f"Connexion à la base de données: {DB_PATH}")

if not os.path.exists(DB_PATH):
    print(f"ERREUR: Base de données introuvable à {DB_PATH}")
    sys.exit(1)

try:
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    print("Création de la table calendly_appointments...")
    cursor.execute("""
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
    )
    """)

    print("Création de la table notifications...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        type TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE
    )
    """)

    print("Création de la table pricing...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS pricing (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT,
        price_per_m3 REAL NOT NULL DEFAULT 1000.0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
    """)

    # Insert default pricing data
    cursor.execute("""
    INSERT OR IGNORE INTO pricing (name, description, price_per_m3, is_active) VALUES
    ('default', 'Prix par défaut pour tous les meubles', 1500.0, 1),
    ('premium', 'Prix premium pour meubles haut de gamme', 2500.0, 1),
    ('budget', 'Prix économique', 1000.0, 1)
    """)

    cursor.execute("CREATE INDEX IF NOT EXISTS idx_pricing_active ON pricing(is_active)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_pricing_name ON pricing(name)")

    # Ajouter les colonnes manquantes à la table configurations
    print("\nVérification des colonnes dans configurations...")
    cursor.execute("PRAGMA table_info(configurations)")
    columns = [col[1] for col in cursor.fetchall()]

    if 'dxf_url' not in columns:
        print("Ajout de la colonne dxf_url...")
        cursor.execute("ALTER TABLE configurations ADD COLUMN dxf_url TEXT")
        print("✓ Colonne dxf_url ajoutée avec succès!")
    else:
        print("✓ Colonne dxf_url existe déjà")

    if 'status' not in columns:
        print("Ajout de la colonne status...")
        cursor.execute("ALTER TABLE configurations ADD COLUMN status TEXT DEFAULT 'en_attente_validation'")
        cursor.execute("UPDATE configurations SET status = 'en_attente_validation' WHERE status IS NULL")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_configurations_status ON configurations(status)")
        print("✓ Colonne status ajoutée avec succès!")
    else:
        print("✓ Colonne status existe déjà")

    conn.commit()
    print("\n✓ Tables créées avec succès!")

    # Vérifier les tables
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
    tables = cursor.fetchall()
    print(f"\nTables dans la base de données:")
    for table in tables:
        print(f"  - {table[0]}")

    conn.close()
    print("\nTerminé!")

except Exception as e:
    print(f"ERREUR: {e}")
    sys.exit(1)
