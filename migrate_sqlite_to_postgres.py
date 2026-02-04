#!/usr/bin/env python3
"""
Migration des données SQLite vers PostgreSQL
=============================================
Ce script lit les données de l'ancien fichier SQLite (.db)
et les insère dans la base PostgreSQL.

Usage:
    python3 migrate_sqlite_to_postgres.py [sqlite_db_path]

Exemples:
    python3 migrate_sqlite_to_postgres.py database/archimeuble.db
    python3 migrate_sqlite_to_postgres.py /data/database/archimeuble.db

Prérequis:
    - PostgreSQL doit être en cours d'exécution
    - Le schéma PostgreSQL doit être initialisé (init_db.sql)
    - DATABASE_URL doit être défini dans l'environnement ou .env
    - pip install psycopg2-binary python-dotenv
"""

import os
import sys
import sqlite3
import json
from datetime import datetime

try:
    import psycopg2
    import psycopg2.extras
except ImportError:
    print("ERROR: psycopg2 not installed. Run: pip install psycopg2-binary")
    sys.exit(1)

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # dotenv is optional

# Tables à migrer dans l'ordre (respecte les foreign keys)
TABLES_ORDER = [
    'users',
    'admins',
    'models',
    'customers',
    'clients',
    'configurations',
    'sessions',
    'projets',
    'devis',
    'avis',
    'saved_configurations',
    'cart_items',
    'orders',
    'order_items',
    'notifications',
    'admin_notifications',
    'sample_types',
    'sample_colors',
    'templates',
    'email_templates',
    'rate_limits',
    'calendly_appointments',
    'categories',
    'pricing',
    'facades',
    'facade_materials',
    'facade_drilling_types',
    'facade_settings',
    'email_verifications',
    'installments',
    'sample_orders',
    'sample_order_items',
    'payment_links',
    'quote_requests',
    'quote_request_items',
]

# Colonnes booléennes par table (convertir 0/1 en False/True)
BOOLEAN_COLUMNS = {
    'customers': ['email_verified'],
    'orders': ['confirmation_email_sent'],
    'notifications': ['is_read'],
    'admin_notifications': ['is_read'],
    'sample_types': ['active'],
    'sample_colors': ['active'],
    'templates': ['is_active'],
    'facades': ['is_active'],
    'facade_materials': ['is_active'],
    'facade_drilling_types': ['is_active'],
    'facade_settings': [],
    'categories': ['is_active'],
    'pricing': ['is_active'],
    'calendly_appointments': ['confirmation_sent', 'reminder_sent', 'reminder_24h_sent', 'reminder_1h_sent'],
}

# Tables avec SERIAL (séquences à réinitialiser)
SERIAL_TABLES = [
    'admins', 'models', 'customers', 'clients', 'configurations',
    'sessions', 'projets', 'devis', 'avis', 'saved_configurations',
    'cart_items', 'orders', 'order_items', 'notifications',
    'admin_notifications', 'sample_types', 'sample_colors',
    'templates', 'email_templates', 'rate_limits',
    'calendly_appointments', 'categories', 'pricing',
    'facades', 'facade_materials', 'facade_drilling_types',
    'facade_settings', 'email_verifications', 'installments',
    'sample_orders', 'sample_order_items', 'payment_links',
    'quote_requests', 'quote_request_items',
]


def get_sqlite_connection(db_path):
    """Ouvre une connexion SQLite en lecture seule."""
    if not os.path.exists(db_path):
        print(f"ERROR: SQLite database not found: {db_path}")
        sys.exit(1)

    conn = sqlite3.connect(f"file:{db_path}?mode=ro", uri=True)
    conn.row_factory = sqlite3.Row
    return conn


def get_postgres_connection():
    """Ouvre une connexion PostgreSQL."""
    database_url = os.environ.get('DATABASE_URL')
    if not database_url:
        print("ERROR: DATABASE_URL not set. Set it in .env or environment.")
        sys.exit(1)

    try:
        conn = psycopg2.connect(database_url)
        conn.autocommit = False
        return conn
    except Exception as e:
        print(f"ERROR: Cannot connect to PostgreSQL: {e}")
        sys.exit(1)


def get_sqlite_tables(sqlite_conn):
    """Liste les tables existantes dans SQLite."""
    cursor = sqlite_conn.execute(
        "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
    )
    return [row[0] for row in cursor.fetchall()]


def get_postgres_tables(pg_conn):
    """Liste les tables existantes dans PostgreSQL."""
    cursor = pg_conn.cursor()
    cursor.execute(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'"
    )
    return [row[0] for row in cursor.fetchall()]


def get_table_columns(pg_conn, table_name):
    """Récupère les colonnes d'une table PostgreSQL."""
    cursor = pg_conn.cursor()
    cursor.execute(
        "SELECT column_name FROM information_schema.columns WHERE table_name = %s AND table_schema = 'public' ORDER BY ordinal_position",
        (table_name,)
    )
    return [row[0] for row in cursor.fetchall()]


def get_sqlite_row_count(sqlite_conn, table):
    """Compte les lignes dans une table SQLite."""
    try:
        cursor = sqlite_conn.execute(f"SELECT COUNT(*) FROM \"{table}\"")
        return cursor.fetchone()[0]
    except Exception:
        return 0


def get_postgres_row_count(pg_conn, table):
    """Compte les lignes dans une table PostgreSQL."""
    try:
        cursor = pg_conn.cursor()
        cursor.execute(f"SELECT COUNT(*) FROM \"{table}\"")
        return cursor.fetchone()[0]
    except Exception:
        pg_conn.rollback()
        return 0


def convert_boolean(value, column, table):
    """Convertit les booléens SQLite (0/1) en Python bool pour PostgreSQL."""
    bool_cols = BOOLEAN_COLUMNS.get(table, [])
    if column in bool_cols:
        if value is None:
            return None
        return bool(value)
    return value


def migrate_table(sqlite_conn, pg_conn, table, pg_columns):
    """Migre les données d'une table SQLite vers PostgreSQL."""
    # Lire les données SQLite
    try:
        sqlite_cursor = sqlite_conn.execute(f"SELECT * FROM \"{table}\"")
        sqlite_columns = [desc[0] for desc in sqlite_cursor.description]
        rows = sqlite_cursor.fetchall()
    except Exception as e:
        print(f"  SKIP: Cannot read from SQLite table '{table}': {e}")
        return 0

    if not rows:
        return 0

    # Ne garder que les colonnes qui existent dans PostgreSQL
    common_columns = [col for col in sqlite_columns if col in pg_columns]

    if not common_columns:
        print(f"  SKIP: No common columns between SQLite and PostgreSQL for '{table}'")
        return 0

    # Préparer l'INSERT
    placeholders = ', '.join(['%s'] * len(common_columns))
    columns_str = ', '.join([f'"{col}"' for col in common_columns])
    insert_sql = f'INSERT INTO "{table}" ({columns_str}) VALUES ({placeholders}) ON CONFLICT DO NOTHING'

    pg_cursor = pg_conn.cursor()
    inserted = 0

    for row in rows:
        # Construire les valeurs avec conversions
        values = []
        for col in common_columns:
            idx = sqlite_columns.index(col)
            val = row[idx]

            # Convertir les booléens
            val = convert_boolean(val, col, table)

            # Convertir les JSON strings si nécessaire
            if isinstance(val, bytes):
                try:
                    val = val.decode('utf-8')
                except Exception:
                    val = str(val)

            values.append(val)

        try:
            pg_cursor.execute(insert_sql, values)
            inserted += 1
        except Exception as e:
            pg_conn.rollback()
            print(f"  ERROR inserting row into '{table}': {e}")
            # Re-start transaction
            pg_cursor = pg_conn.cursor()

    pg_conn.commit()
    return inserted


def reset_sequences(pg_conn):
    """Réinitialise les séquences SERIAL après insertion avec IDs explicites."""
    pg_cursor = pg_conn.cursor()

    for table in SERIAL_TABLES:
        seq_name = f"{table}_id_seq"
        try:
            pg_cursor.execute(f"""
                SELECT setval('{seq_name}', COALESCE((SELECT MAX(id) FROM "{table}"), 0))
            """)
        except Exception:
            pg_conn.rollback()
            pg_cursor = pg_conn.cursor()
            # Some tables might not have this sequence

    pg_conn.commit()
    print("\nSequences reset successfully.")


def main():
    # Déterminer le chemin SQLite
    if len(sys.argv) > 1:
        sqlite_path = sys.argv[1]
    else:
        # Chercher dans les emplacements habituels
        candidates = [
            'database/archimeuble.db',
            '/data/database/archimeuble.db',
            '../database/archimeuble.db',
            'archimeuble.db',
        ]
        sqlite_path = None
        for path in candidates:
            if os.path.exists(path):
                sqlite_path = path
                break

        if not sqlite_path:
            print("Usage: python3 migrate_sqlite_to_postgres.py <sqlite_db_path>")
            print("\nNo SQLite database found in default locations:")
            for path in candidates:
                print(f"  - {path}")
            sys.exit(1)

    print("=" * 60)
    print("ArchiMeuble - Migration SQLite → PostgreSQL")
    print("=" * 60)
    print(f"\nSource SQLite: {sqlite_path}")
    print(f"Target PostgreSQL: {os.environ.get('DATABASE_URL', 'NOT SET')[:50]}...")
    print()

    # Connexions
    sqlite_conn = get_sqlite_connection(sqlite_path)
    pg_conn = get_postgres_connection()

    # Lister les tables
    sqlite_tables = get_sqlite_tables(sqlite_conn)
    pg_tables = get_postgres_tables(pg_conn)

    print(f"SQLite tables: {len(sqlite_tables)}")
    print(f"PostgreSQL tables: {len(pg_tables)}")
    print()

    # Migrer chaque table dans l'ordre
    total_migrated = 0
    total_skipped = 0
    results = []

    for table in TABLES_ORDER:
        if table not in sqlite_tables:
            continue

        if table not in pg_tables:
            print(f"  SKIP: '{table}' does not exist in PostgreSQL")
            total_skipped += 1
            continue

        sqlite_count = get_sqlite_row_count(sqlite_conn, table)
        if sqlite_count == 0:
            continue

        pg_columns = get_table_columns(pg_conn, table)
        print(f"Migrating '{table}' ({sqlite_count} rows)...", end=" ")

        inserted = migrate_table(sqlite_conn, pg_conn, table, pg_columns)
        pg_count = get_postgres_row_count(pg_conn, table)

        status = "OK" if pg_count >= sqlite_count else f"PARTIAL ({pg_count}/{sqlite_count})"
        print(f"{status} - {inserted} inserted")

        results.append({
            'table': table,
            'sqlite_count': sqlite_count,
            'pg_count': pg_count,
            'inserted': inserted,
        })
        total_migrated += inserted

    # Migrer les tables non listées dans TABLES_ORDER (au cas où)
    extra_tables = [t for t in sqlite_tables if t not in TABLES_ORDER and t in pg_tables]
    if extra_tables:
        print(f"\nMigrating {len(extra_tables)} extra tables not in predefined order...")
        for table in extra_tables:
            sqlite_count = get_sqlite_row_count(sqlite_conn, table)
            if sqlite_count == 0:
                continue

            pg_columns = get_table_columns(pg_conn, table)
            print(f"Migrating '{table}' ({sqlite_count} rows)...", end=" ")

            inserted = migrate_table(sqlite_conn, pg_conn, table, pg_columns)
            pg_count = get_postgres_row_count(pg_conn, table)
            print(f"OK - {inserted} inserted")

            results.append({
                'table': table,
                'sqlite_count': sqlite_count,
                'pg_count': pg_count,
                'inserted': inserted,
            })
            total_migrated += inserted

    # Réinitialiser les séquences
    print("\nResetting sequences...")
    reset_sequences(pg_conn)

    # Résumé
    print("\n" + "=" * 60)
    print("MIGRATION SUMMARY")
    print("=" * 60)
    print(f"Total rows migrated: {total_migrated}")
    print(f"Tables skipped: {total_skipped}")
    print()

    if results:
        print(f"{'Table':<35} {'SQLite':>8} {'PostgreSQL':>10} {'Inserted':>10}")
        print("-" * 65)
        for r in results:
            print(f"{r['table']:<35} {r['sqlite_count']:>8} {r['pg_count']:>10} {r['inserted']:>10}")

    print()

    # Vérification d'intégrité
    issues = []
    for r in results:
        if r['pg_count'] < r['sqlite_count']:
            issues.append(f"  - {r['table']}: {r['sqlite_count']} in SQLite, {r['pg_count']} in PostgreSQL")

    if issues:
        print("WARNINGS - Some tables have fewer rows in PostgreSQL:")
        for issue in issues:
            print(issue)
        print("\nThis may be normal if rows violate foreign key constraints.")
    else:
        print("All tables verified successfully!")

    # Fermer les connexions
    sqlite_conn.close()
    pg_conn.close()

    print("\nMigration complete!")


if __name__ == '__main__':
    main()
