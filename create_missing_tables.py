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
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        event_type TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        timezone TEXT DEFAULT 'Europe/Paris',
        config_url TEXT,
        additional_notes TEXT,
        meeting_url TEXT,
        phone_number TEXT,
        status TEXT DEFAULT 'scheduled',
        confirmation_sent INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
    """)

    # Migration: vérifier si table a l'ancien schéma
    print("\nVérification des colonnes dans calendly_appointments...")
    cursor.execute("PRAGMA table_info(calendly_appointments)")
    cal_columns = {col[1]: col for col in cursor.fetchall()}

    # Si anciennes colonnes existent OU colonnes manquantes, recréer la table
    needs_migration = (
        'customer_name' in cal_columns or
        'customer_email' in cal_columns or
        'client_name' not in cal_columns or
        'timezone' not in cal_columns or
        'confirmation_sent' not in cal_columns
    )

    if needs_migration:
        print("Migration: Mise à jour du schéma de la table...")

        # Compter les lignes existantes
        cursor.execute("SELECT COUNT(*) FROM calendly_appointments")
        count = cursor.fetchone()[0]

        if count > 0:
            print(f"⚠️  Attention: {count} rendez-vous existants seront migrés")

            # Identifier les colonnes communes pour la migration
            old_has_customer = 'customer_name' in cal_columns
            old_has_client = 'client_name' in cal_columns
            old_has_event_id = 'calendly_event_id' in cal_columns

            # Créer nouvelle table
            cursor.execute("""
            CREATE TABLE calendly_appointments_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                calendly_event_id TEXT UNIQUE,
                client_name TEXT NOT NULL,
                client_email TEXT NOT NULL,
                event_type TEXT,
                start_time DATETIME NOT NULL,
                end_time DATETIME NOT NULL,
                timezone TEXT DEFAULT 'Europe/Paris',
                config_url TEXT,
                additional_notes TEXT,
                meeting_url TEXT,
                phone_number TEXT,
                status TEXT DEFAULT 'scheduled',
                confirmation_sent INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            """)

            # Construire la requête de copie basée sur les colonnes disponibles
            name_col = 'customer_name' if old_has_customer else 'client_name'
            email_col = 'customer_email' if old_has_customer else 'client_email'
            event_id_col = 'calendly_event_id' if old_has_event_id else "'unknown_' || id"

            cursor.execute(f"""
            INSERT INTO calendly_appointments_new
            (id, calendly_event_id, client_name, client_email, event_type, start_time, end_time, status, created_at, updated_at)
            SELECT id, {event_id_col}, {name_col}, {email_col}, event_type, start_time, end_time, status, created_at, updated_at
            FROM calendly_appointments
            """)

            # Supprimer ancienne table et renommer
            cursor.execute("DROP TABLE calendly_appointments")
            cursor.execute("ALTER TABLE calendly_appointments_new RENAME TO calendly_appointments")
            print(f"✓ Migration terminée ({count} rendez-vous migrés)")
        else:
            # Table vide, on peut la supprimer et recréer
            cursor.execute("DROP TABLE calendly_appointments")
            cursor.execute("""
            CREATE TABLE calendly_appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                calendly_event_id TEXT UNIQUE NOT NULL,
                client_name TEXT NOT NULL,
                client_email TEXT NOT NULL,
                event_type TEXT,
                start_time DATETIME NOT NULL,
                end_time DATETIME NOT NULL,
                timezone TEXT DEFAULT 'Europe/Paris',
                config_url TEXT,
                additional_notes TEXT,
                meeting_url TEXT,
                phone_number TEXT,
                status TEXT DEFAULT 'scheduled',
                confirmation_sent INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
            """)
            print("✓ Table recréée avec le nouveau schéma")
    else:
        print("✓ Schéma déjà à jour")

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

    # Ajouter les colonnes manquantes à la table models
    print("\nVérification des colonnes dans models...")
    cursor.execute("PRAGMA table_info(models)")
    model_columns = [col[1] for col in cursor.fetchall()]

    if 'category' not in model_columns:
        print("Ajout de la colonne category à la table models...")
        cursor.execute("ALTER TABLE models ADD COLUMN category TEXT")
        print("✓ Colonne category ajoutée avec succès!")
    else:
        print("✓ Colonne category existe déjà")

    if 'config_data' not in model_columns:
        print("Ajout de la colonne config_data à la table models...")
        cursor.execute("ALTER TABLE models ADD COLUMN config_data TEXT")
        print("✓ Colonne config_data ajoutée avec succès!")
    else:
        print("✓ Colonne config_data existe déjà")

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
