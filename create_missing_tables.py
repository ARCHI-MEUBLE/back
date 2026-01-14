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

    # Vérifier et ajouter les colonnes manquantes à notifications
    print("\nVérification des colonnes dans notifications...")
    cursor.execute("PRAGMA table_info(notifications)")
    notif_columns = [col[1] for col in cursor.fetchall()]

    if 'related_id' not in notif_columns:
        print("Ajout de la colonne related_id à notifications...")
        cursor.execute("ALTER TABLE notifications ADD COLUMN related_id INTEGER")
        print("✓ Colonne related_id ajoutée avec succès!")
    else:
        print("✓ Colonne related_id existe déjà")

    if 'category' not in notif_columns:
        print("Ajout de la colonne category à notifications...")
        cursor.execute("ALTER TABLE notifications ADD COLUMN category TEXT")
        print("✓ Colonne category ajoutée avec succès!")
    else:
        print("✓ Colonne category existe déjà")

    if 'related_type' not in notif_columns:
        print("Ajout de la colonne related_type à notifications...")
        cursor.execute("ALTER TABLE notifications ADD COLUMN related_type TEXT")
        print("✓ Colonne related_type ajoutée avec succès!")
    else:
        print("✓ Colonne related_type existe déjà")

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

    # Vérifier et corriger la table payment_links
    print("\nVérification de la table payment_links...")
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='payment_links'")
    if cursor.fetchone():
        cursor.execute("PRAGMA table_info(payment_links)")
        payment_columns = [col[1] for col in cursor.fetchall()]

        if 'expires_at' not in payment_columns:
            print("Ajout de la colonne expires_at à payment_links...")
            cursor.execute("ALTER TABLE payment_links ADD COLUMN expires_at DATETIME")
            print("✓ Colonne expires_at ajoutée avec succès!")
        else:
            print("✓ Colonne expires_at existe déjà")

        if 'payment_type' not in payment_columns:
            print("Ajout de la colonne payment_type à payment_links...")
            cursor.execute("ALTER TABLE payment_links ADD COLUMN payment_type TEXT DEFAULT 'full'")
            print("✓ Colonne payment_type ajoutée avec succès!")
        else:
            print("✓ Colonne payment_type existe déjà")

        if 'accessed_at' not in payment_columns:
            print("Ajout de la colonne accessed_at à payment_links...")
            cursor.execute("ALTER TABLE payment_links ADD COLUMN accessed_at DATETIME")
            print("✓ Colonne accessed_at ajoutée avec succès!")
        else:
            print("✓ Colonne accessed_at existe déjà")

        if 'paid_at' not in payment_columns:
            print("Ajout de la colonne paid_at à payment_links...")
            cursor.execute("ALTER TABLE payment_links ADD COLUMN paid_at DATETIME")
            print("✓ Colonne paid_at ajoutée avec succès!")
        else:
            print("✓ Colonne paid_at existe déjà")

        if 'amount' not in payment_columns:
            print("Ajout de la colonne amount à payment_links...")
            cursor.execute("ALTER TABLE payment_links ADD COLUMN amount DECIMAL(10,2)")
            print("✓ Colonne amount ajoutée avec succès!")

            # Mettre à jour les liens existants avec les bons montants
            print("Migration des montants existants...")
            cursor.execute("""
            UPDATE payment_links
            SET amount = (
                SELECT CASE
                    WHEN payment_links.payment_type = 'deposit' THEN o.deposit_amount
                    WHEN payment_links.payment_type = 'balance' THEN o.remaining_amount
                    ELSE o.total_amount
                END
                FROM orders o
                WHERE o.id = payment_links.order_id
            )
            WHERE amount IS NULL
            """)
            print("✓ Montants migrés avec succès!")
        else:
            print("✓ Colonne amount existe déjà")
    else:
        print("Création de la table payment_links...")
        cursor.execute("""
        CREATE TABLE payment_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            status TEXT DEFAULT 'active',
            expires_at DATETIME NOT NULL,
            payment_type TEXT DEFAULT 'full',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            accessed_at DATETIME,
            paid_at DATETIME,
            created_by_admin TEXT,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
        """)
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_token ON payment_links(token)")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_order ON payment_links(order_id)")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_status ON payment_links(status)")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_expires ON payment_links(expires_at)")
        print("✓ Table payment_links créée avec succès!")

    # Créer la table stripe_payment_intents
    print("\nCréation de la table stripe_payment_intents...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS stripe_payment_intents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_intent_id TEXT NOT NULL UNIQUE,
        order_id INTEGER NOT NULL,
        customer_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        currency TEXT DEFAULT 'eur',
        status TEXT NOT NULL,
        metadata TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )
    """)
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_stripe_pi_payment_intent ON stripe_payment_intents(payment_intent_id)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_stripe_pi_order ON stripe_payment_intents(order_id)")
    print("✓ Table stripe_payment_intents créée avec succès!")

    # Ajouter les colonnes Stripe manquantes à la table orders
    print("\nVérification des colonnes Stripe dans orders...")
    cursor.execute("PRAGMA table_info(orders)")
    orders_columns = [col[1] for col in cursor.fetchall()]

    if 'stripe_payment_intent_id' not in orders_columns:
        print("Ajout de la colonne stripe_payment_intent_id à orders...")
        cursor.execute("ALTER TABLE orders ADD COLUMN stripe_payment_intent_id TEXT")
        print("✓ Colonne stripe_payment_intent_id ajoutée avec succès!")
    else:
        print("✓ Colonne stripe_payment_intent_id existe déjà")

    if 'deposit_stripe_intent_id' not in orders_columns:
        print("Ajout de la colonne deposit_stripe_intent_id à orders...")
        cursor.execute("ALTER TABLE orders ADD COLUMN deposit_stripe_intent_id TEXT")
        print("✓ Colonne deposit_stripe_intent_id ajoutée avec succès!")
    else:
        print("✓ Colonne deposit_stripe_intent_id existe déjà")

    if 'balance_stripe_intent_id' not in orders_columns:
        print("Ajout de la colonne balance_stripe_intent_id à orders...")
        cursor.execute("ALTER TABLE orders ADD COLUMN balance_stripe_intent_id TEXT")
        print("✓ Colonne balance_stripe_intent_id ajoutée avec succès!")
    else:
        print("✓ Colonne balance_stripe_intent_id existe déjà")

    if 'deposit_payment_status' not in orders_columns:
        print("Ajout de la colonne deposit_payment_status à orders...")
        cursor.execute("ALTER TABLE orders ADD COLUMN deposit_payment_status TEXT DEFAULT 'pending'")
        print("✓ Colonne deposit_payment_status ajoutée avec succès!")
    else:
        print("✓ Colonne deposit_payment_status existe déjà")

    if 'balance_payment_status' not in orders_columns:
        print("Ajout de la colonne balance_payment_status à orders...")
        cursor.execute("ALTER TABLE orders ADD COLUMN balance_payment_status TEXT DEFAULT 'pending'")
        print("✓ Colonne balance_payment_status ajoutée avec succès!")
    else:
        print("✓ Colonne balance_payment_status existe déjà")

    # Ajouter la colonne stripe_customer_id à customers
    print("\nVérification de la colonne stripe_customer_id dans customers...")
    cursor.execute("PRAGMA table_info(customers)")
    customers_columns = [col[1] for col in cursor.fetchall()]

    if 'stripe_customer_id' not in customers_columns:
        print("Ajout de la colonne stripe_customer_id à customers...")
        cursor.execute("ALTER TABLE customers ADD COLUMN stripe_customer_id TEXT")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_customers_stripe ON customers(stripe_customer_id)")
        print("✓ Colonne stripe_customer_id ajoutée avec succès!")
    else:
        print("✓ Colonne stripe_customer_id existe déjà")

    # Créer la table order_catalogue_items si elle n'existe pas
    print("\nVérification de la table order_catalogue_items...")
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS order_catalogue_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            catalogue_item_id INTEGER,
            variation_id INTEGER,
            product_name TEXT NOT NULL,
            variation_name TEXT,
            quantity INTEGER DEFAULT 1,
            unit_price REAL NOT NULL,
            total_price REAL NOT NULL,
            image_url TEXT,
            name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
    """)
    print("✓ Table order_catalogue_items vérifiée/créée")

    # Vérifier et ajouter la colonne name à order_catalogue_items (pour les anciennes bases)
    cursor.execute("PRAGMA table_info(order_catalogue_items)")
    order_catalogue_columns = [col[1] for col in cursor.fetchall()]

    if 'name' not in order_catalogue_columns:
        print("Ajout de la colonne name à order_catalogue_items...")
        cursor.execute("ALTER TABLE order_catalogue_items ADD COLUMN name TEXT")
        print("✓ Colonne name ajoutée avec succès!")
    else:
        print("✓ Colonne name existe déjà")

    # Ajouter les paramètres de prix manquants (penderie, poignées, etc.)
    print("\nVérification des paramètres de prix manquants...")
    cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='pricing_config'")
    if cursor.fetchone():
        # Liste des paramètres à ajouter
        params_to_add = [
            # PENDERIE
            ('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par mètre linéaire'),

            # POIGNÉES
            ('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', "Prix d'une poignée barre horizontale"),
            ('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', "Prix d'une poignée barre verticale"),
            ('handles', 'knob', 'price_per_unit', 10, 'eur', "Prix d'un bouton de porte"),
            ('handles', 'recessed', 'price_per_unit', 20, 'eur', "Prix d'une poignée encastrée"),

            # SOCLE BOIS - paramètres complémentaires
            ('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois pour socle au m³'),
            ('bases', 'wood', 'height', 80, 'mm', 'Hauteur fixe du socle bois'),
            ('bases', 'metal', 'base_foot_count', 2, 'units', 'Nombre minimum de pieds (base)'),

            # AFFICHAGE PRIX
            ('display', 'price', 'display_mode', 0, 'units', "Mode d'affichage (0=Direct, 1=Intervalle)"),
            ('display', 'price', 'deviation_range', 100, 'eur', "Écart pour l'affichage en intervalle"),
        ]

        for category, item_type, param_name, param_value, unit, description in params_to_add:
            # Vérifier si le paramètre existe déjà
            cursor.execute("""
                SELECT id FROM pricing_config
                WHERE category = ? AND item_type = ? AND param_name = ?
            """, (category, item_type, param_name))

            if cursor.fetchone() is None:
                cursor.execute("""
                    INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                """, (category, item_type, param_name, param_value, unit, description))
                print(f"  + Ajouté: {category}/{item_type}/{param_name}")
        print("✓ Paramètres de prix vérifiés/ajoutés")
    else:
        print("⚠️  Table pricing_config n'existe pas encore")

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
