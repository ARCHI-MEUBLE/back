#!/usr/bin/env python3
"""
Script pour créer les tables manquantes dans la base de données ArchiMeuble (PostgreSQL)
"""
import os
import sys
from urllib.parse import urlparse

try:
    import psycopg2
    import psycopg2.extras
except ImportError:
    print("Installation de psycopg2-binary...")
    os.system("pip install psycopg2-binary")
    import psycopg2
    import psycopg2.extras

DATABASE_URL = os.getenv('DATABASE_URL', '')

if not DATABASE_URL:
    print("ERREUR: DATABASE_URL non défini")
    sys.exit(1)

print(f"Connexion à PostgreSQL...")

def get_table_columns(cursor, table_name):
    """Retourne la liste des colonnes d'une table"""
    cursor.execute(
        "SELECT column_name FROM information_schema.columns WHERE table_name = %s AND table_schema = 'public'",
        (table_name,)
    )
    return [row[0] for row in cursor.fetchall()]

def table_exists(cursor, table_name):
    """Vérifie si une table existe"""
    cursor.execute(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = %s",
        (table_name,)
    )
    return cursor.fetchone() is not None

try:
    conn = psycopg2.connect(DATABASE_URL)
    conn.autocommit = False
    cursor = conn.cursor()

    print("Vérification de la table calendly_appointments...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS calendly_appointments (
        id SERIAL PRIMARY KEY,
        calendly_event_id TEXT UNIQUE NOT NULL,
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        event_type TEXT,
        start_time TIMESTAMP NOT NULL,
        end_time TIMESTAMP NOT NULL,
        timezone TEXT DEFAULT 'Europe/Paris',
        config_url TEXT,
        additional_notes TEXT,
        meeting_url TEXT,
        phone_number TEXT,
        status TEXT DEFAULT 'scheduled',
        confirmation_sent BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    """)
    conn.commit()

    # Vérifier colonnes manquantes dans calendly_appointments
    cal_columns = get_table_columns(cursor, 'calendly_appointments')
    if cal_columns:
        if 'meeting_url' not in cal_columns:
            cursor.execute("ALTER TABLE calendly_appointments ADD COLUMN meeting_url TEXT")
        if 'phone_number' not in cal_columns:
            cursor.execute("ALTER TABLE calendly_appointments ADD COLUMN phone_number TEXT")
        if 'timezone' not in cal_columns:
            cursor.execute("ALTER TABLE calendly_appointments ADD COLUMN timezone TEXT DEFAULT 'Europe/Paris'")
        if 'confirmation_sent' not in cal_columns:
            cursor.execute("ALTER TABLE calendly_appointments ADD COLUMN confirmation_sent BOOLEAN DEFAULT FALSE")
    conn.commit()
    print("OK calendly_appointments")

    print("Vérification de la table notifications...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS notifications (
        id SERIAL PRIMARY KEY,
        customer_id INTEGER,
        type TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        related_order_id INTEGER,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP,
        related_id INTEGER,
        category TEXT,
        related_type TEXT
    )
    """)
    conn.commit()

    # Vérifier colonnes manquantes dans notifications
    notif_columns = get_table_columns(cursor, 'notifications')
    if 'related_id' not in notif_columns:
        cursor.execute("ALTER TABLE notifications ADD COLUMN related_id INTEGER")
    if 'category' not in notif_columns:
        cursor.execute("ALTER TABLE notifications ADD COLUMN category TEXT")
    if 'related_type' not in notif_columns:
        cursor.execute("ALTER TABLE notifications ADD COLUMN related_type TEXT")
    conn.commit()
    print("OK notifications")

    print("Vérification de la table pricing...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS pricing (
        id SERIAL PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        description TEXT,
        price_per_m3 DECIMAL(10,2) NOT NULL DEFAULT 1000.0,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    """)
    conn.commit()

    # Insert default pricing data
    cursor.execute("""
    INSERT INTO pricing (name, description, price_per_m3, is_active) VALUES
    ('default', 'Prix par défaut pour tous les meubles', 1500.0, TRUE),
    ('premium', 'Prix premium pour meubles haut de gamme', 2500.0, TRUE),
    ('budget', 'Prix économique', 1000.0, TRUE)
    ON CONFLICT (name) DO NOTHING
    """)
    conn.commit()

    cursor.execute("CREATE INDEX IF NOT EXISTS idx_pricing_active ON pricing(is_active)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_pricing_name ON pricing(name)")
    conn.commit()
    print("OK pricing")

    # Colonnes manquantes dans configurations
    print("\nVérification des colonnes dans configurations...")
    config_columns = get_table_columns(cursor, 'configurations')
    if config_columns:
        if 'dxf_url' not in config_columns:
            cursor.execute("ALTER TABLE configurations ADD COLUMN dxf_url TEXT")
        if 'status' not in config_columns:
            cursor.execute("ALTER TABLE configurations ADD COLUMN status TEXT DEFAULT 'en_attente_validation'")
            cursor.execute("UPDATE configurations SET status = 'en_attente_validation' WHERE status IS NULL")
            cursor.execute("CREATE INDEX IF NOT EXISTS idx_configurations_status ON configurations(status)")
    conn.commit()
    print("OK configurations")

    # Colonnes manquantes dans models
    print("Vérification des colonnes dans models...")
    model_columns = get_table_columns(cursor, 'models')
    if model_columns:
        if 'category' not in model_columns:
            cursor.execute("ALTER TABLE models ADD COLUMN category TEXT")
        if 'config_data' not in model_columns:
            cursor.execute("ALTER TABLE models ADD COLUMN config_data TEXT")
        if 'hover_image_url' not in model_columns:
            cursor.execute("ALTER TABLE models ADD COLUMN hover_image_url TEXT")
    conn.commit()
    print("OK models")

    # Vérifier et corriger la table payment_links
    print("Vérification de la table payment_links...")
    if table_exists(cursor, 'payment_links'):
        payment_columns = get_table_columns(cursor, 'payment_links')

        if 'expires_at' not in payment_columns:
            cursor.execute("ALTER TABLE payment_links ADD COLUMN expires_at TIMESTAMP")
        if 'payment_type' not in payment_columns:
            cursor.execute("ALTER TABLE payment_links ADD COLUMN payment_type TEXT DEFAULT 'full'")
        if 'accessed_at' not in payment_columns:
            cursor.execute("ALTER TABLE payment_links ADD COLUMN accessed_at TIMESTAMP")
        if 'paid_at' not in payment_columns:
            cursor.execute("ALTER TABLE payment_links ADD COLUMN paid_at TIMESTAMP")
        if 'amount' not in payment_columns:
            cursor.execute("ALTER TABLE payment_links ADD COLUMN amount DECIMAL(10,2)")
            # Migration optionnelle des montants
            try:
                orders_cols = get_table_columns(cursor, 'orders')
                if 'deposit_amount' in orders_cols and 'remaining_amount' in orders_cols and 'total_amount' in orders_cols:
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
                    print("  Montants migrés")
            except Exception as migration_err:
                print(f"  Migration des montants ignorée (non-fatal): {migration_err}")
    else:
        cursor.execute("""
        CREATE TABLE payment_links (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            status TEXT DEFAULT 'active',
            expires_at TIMESTAMP NOT NULL,
            payment_type TEXT DEFAULT 'full',
            amount DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            accessed_at TIMESTAMP,
            paid_at TIMESTAMP,
            created_by_admin TEXT,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
        """)
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_token ON payment_links(token)")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_order ON payment_links(order_id)")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_status ON payment_links(status)")
        cursor.execute("CREATE INDEX IF NOT EXISTS idx_payment_links_expires ON payment_links(expires_at)")
    conn.commit()
    print("OK payment_links")

    # Table stripe_payment_intents
    print("Vérification de la table stripe_payment_intents...")
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS stripe_payment_intents (
        id SERIAL PRIMARY KEY,
        payment_intent_id TEXT NOT NULL UNIQUE,
        order_id INTEGER NOT NULL,
        customer_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        currency TEXT DEFAULT 'eur',
        status TEXT NOT NULL,
        metadata TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )
    """)
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_stripe_pi_payment_intent ON stripe_payment_intents(payment_intent_id)")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_stripe_pi_order ON stripe_payment_intents(order_id)")
    conn.commit()
    print("OK stripe_payment_intents")

    # Colonnes Stripe dans orders
    print("Vérification des colonnes Stripe dans orders...")
    orders_columns = get_table_columns(cursor, 'orders')
    if orders_columns:
        stripe_cols = [
            ('stripe_payment_intent_id', 'TEXT'),
            ('deposit_stripe_intent_id', 'TEXT'),
            ('balance_stripe_intent_id', 'TEXT'),
            ('deposit_payment_status', "TEXT DEFAULT 'pending'"),
            ('balance_payment_status', "TEXT DEFAULT 'pending'"),
            ('deposit_amount', 'DECIMAL(10,2)'),
            ('deposit_percentage', 'DECIMAL(5,2) DEFAULT 0'),
            ('remaining_amount', 'DECIMAL(10,2)'),
            ('payment_strategy', "TEXT DEFAULT 'full'"),
            ('name', 'TEXT'),
            ('confirmation_email_sent', 'BOOLEAN DEFAULT FALSE'),
        ]
        for col_name, col_type in stripe_cols:
            if col_name not in orders_columns:
                cursor.execute(f"ALTER TABLE orders ADD COLUMN {col_name} {col_type}")
                print(f"  + {col_name} ajouté à orders")
    conn.commit()
    print("OK orders")

    # Colonne stripe_customer_id dans customers
    print("Vérification stripe_customer_id dans customers...")
    customers_columns = get_table_columns(cursor, 'customers')
    if customers_columns:
        if 'stripe_customer_id' not in customers_columns:
            cursor.execute("ALTER TABLE customers ADD COLUMN stripe_customer_id TEXT")
            cursor.execute("CREATE INDEX IF NOT EXISTS idx_customers_stripe ON customers(stripe_customer_id)")
        if 'email_verified' not in customers_columns:
            cursor.execute("ALTER TABLE customers ADD COLUMN email_verified BOOLEAN DEFAULT FALSE")
    conn.commit()
    print("OK customers")

    # Tables catalogue
    print("Vérification des tables catalogue...")
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS catalogue_items (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT,
            material VARCHAR(100),
            dimensions VARCHAR(100),
            unit_price DECIMAL(10,2) NOT NULL,
            unit VARCHAR(50) DEFAULT 'pièce',
            stock_quantity INTEGER DEFAULT 0,
            min_order_quantity INTEGER DEFAULT 1,
            is_available BOOLEAN DEFAULT TRUE,
            image_url VARCHAR(500),
            weight DECIMAL(8,2),
            tags TEXT,
            variation_label VARCHAR(100) DEFAULT 'Couleur / Finition',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS catalogue_item_variations (
            id SERIAL PRIMARY KEY,
            catalogue_item_id INTEGER NOT NULL,
            color_name VARCHAR(100) NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            is_default BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE,
            UNIQUE(catalogue_item_id, color_name)
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS cart_catalogue_items (
            id SERIAL PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            catalogue_item_id INTEGER NOT NULL,
            variation_id INTEGER,
            quantity INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (catalogue_item_id) REFERENCES catalogue_items(id) ON DELETE CASCADE
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS order_catalogue_items (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL,
            catalogue_item_id INTEGER,
            variation_id INTEGER,
            product_name TEXT NOT NULL,
            variation_name TEXT,
            quantity INTEGER DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            image_url TEXT,
            name TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )
    """)
    conn.commit()
    print("OK tables catalogue")

    # Tables façades
    print("Vérification des tables façades...")
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS facades (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            width DECIMAL(10,2) NOT NULL,
            height DECIMAL(10,2) NOT NULL,
            depth DECIMAL(10,2) NOT NULL,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            image_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS facade_materials (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            color_hex VARCHAR(7) NOT NULL,
            texture_url VARCHAR(500),
            price_modifier DECIMAL(10,2) DEFAULT 0,
            price_per_m2 DECIMAL(10,2) DEFAULT 150,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS facade_drilling_types (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            icon_svg TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS facade_settings (
            id SERIAL PRIMARY KEY,
            setting_key TEXT UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)

    cursor.execute("""
        CREATE TABLE IF NOT EXISTS saved_facades (
            id SERIAL PRIMARY KEY,
            customer_id INTEGER,
            facade_id INTEGER,
            configuration_data TEXT NOT NULL,
            preview_image TEXT,
            total_price DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (facade_id) REFERENCES facades(id) ON DELETE CASCADE
        )
    """)
    conn.commit()
    print("OK tables façades")

    # Données par défaut pour facade_settings
    default_facade_settings = [
        ('max_width', '600', 'Largeur maximale en mm'),
        ('max_height', '2300', 'Hauteur maximale en mm'),
        ('fixed_depth', '19', 'Épaisseur fixe en mm'),
        ('hinge_edge_margin', '20', 'Marge des charnières en mm'),
        ('hinge_hole_diameter', '26', 'Diamètre des trous de charnières en mm'),
        ('hinge_base_price', '34.20', 'Prix de base d\'une charnière'),
        ('hinge_coefficient', '0.05', 'Coefficient multiplicateur par charnière'),
        ('material_price_per_m2', '150', 'Prix du matériau au m²'),
    ]

    for key, value, desc in default_facade_settings:
        cursor.execute("""
            INSERT INTO facade_settings (setting_key, setting_value, description)
            VALUES (%s, %s, %s)
            ON CONFLICT (setting_key) DO NOTHING
        """, (key, value, desc))
    conn.commit()
    print("OK paramètres façades")

    # Matériaux par défaut si table vide
    cursor.execute("SELECT COUNT(*) FROM facade_materials")
    if cursor.fetchone()[0] == 0:
        default_materials = [
            ('Chêne Naturel', '#D8C7A1', None, 0, 150),
            ('Chêne Foncé', '#8B7355', None, 10, 160),
            ('Blanc Mat', '#FFFFFF', None, -5, 145),
            ('Noir Mat', '#1A1917', None, 5, 155),
            ('Gris Anthracite', '#4A4A4A', None, 0, 150),
        ]
        for name, color, texture, modifier, price_m2 in default_materials:
            cursor.execute("""
                INSERT INTO facade_materials (name, color_hex, texture_url, price_modifier, price_per_m2)
                VALUES (%s, %s, %s, %s, %s)
            """, (name, color, texture, modifier, price_m2))
        conn.commit()
        print("OK matériaux par défaut façades")

    # Colonnes manquantes dans sample_types et sample_colors
    print("Vérification colonnes sample_types/sample_colors...")
    st_columns = get_table_columns(cursor, 'sample_types')
    if st_columns:
        if 'price_per_m2' not in st_columns:
            cursor.execute("ALTER TABLE sample_types ADD COLUMN price_per_m2 DECIMAL(10,2) DEFAULT 0")
        if 'unit_price' not in st_columns:
            cursor.execute("ALTER TABLE sample_types ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0")

    sc_columns = get_table_columns(cursor, 'sample_colors')
    if sc_columns:
        if 'price_per_m2' not in sc_columns:
            cursor.execute("ALTER TABLE sample_colors ADD COLUMN price_per_m2 DECIMAL(10,2) DEFAULT 0")
        if 'unit_price' not in sc_columns:
            cursor.execute("ALTER TABLE sample_colors ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0")
    conn.commit()
    print("OK sample_types/sample_colors")

    # Paramètres de prix manquants dans pricing_config
    print("Vérification des paramètres de prix manquants...")
    if table_exists(cursor, 'pricing_config'):
        params_to_add = [
            ('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par mètre linéaire'),
            ('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', "Prix d'une poignée barre horizontale"),
            ('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', "Prix d'une poignée barre verticale"),
            ('handles', 'knob', 'price_per_unit', 10, 'eur', "Prix d'un bouton de porte"),
            ('handles', 'recessed', 'price_per_unit', 20, 'eur', "Prix d'une poignée encastrée"),
            ('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois pour socle au m³'),
            ('bases', 'wood', 'height', 80, 'mm', 'Hauteur fixe du socle bois'),
            ('bases', 'metal', 'base_foot_count', 2, 'units', 'Nombre minimum de pieds (base)'),
            ('display', 'price', 'display_mode', 0, 'units', "Mode d'affichage (0=Direct, 1=Intervalle)"),
            ('display', 'price', 'deviation_range', 100, 'eur', "Écart pour l'affichage en intervalle"),
        ]

        for category, item_type, param_name, param_value, unit, description in params_to_add:
            cursor.execute("""
                INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description, is_active)
                VALUES (%s, %s, %s, %s, %s, %s, TRUE)
                ON CONFLICT (category, item_type, param_name) DO NOTHING
            """, (category, item_type, param_name, param_value, unit, description))
        conn.commit()
        print("OK paramètres de prix")
    else:
        print("  Table pricing_config n'existe pas encore")

    conn.commit()
    print("\nTables vérifiées/créées avec succès!")

    # Lister les tables
    cursor.execute("""
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    """)
    tables = cursor.fetchall()
    print(f"\nTables dans la base de données ({len(tables)}):")
    for table in tables:
        print(f"  - {table[0]}")

    conn.close()
    print("\nTerminé!")

except Exception as e:
    print(f"ERREUR: {e}")
    import traceback
    traceback.print_exc()
    sys.exit(1)
