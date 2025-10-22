#!/usr/bin/env python3
"""
Script pour créer la base de données SQLite
Fonctionne sans dépendances externes
"""
import sqlite3
import os

# Chemin vers le fichier SQL et la base de données
script_dir = os.path.dirname(os.path.abspath(__file__))
sql_file = os.path.join(script_dir, 'init_db.sql')
db_file = os.path.join(script_dir, 'archimeuble.db')

print("Creation de la base de donnees ArchiMeuble...")

# Lire le fichier SQL
with open(sql_file, 'r', encoding='utf-8') as f:
    sql_script = f.read()

# Créer la base de données
conn = sqlite3.connect(db_file)
cursor = conn.cursor()

# Exécuter le script SQL
cursor.executescript(sql_script)
conn.commit()

# Vérifier les tables créées
cursor.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
tables = cursor.fetchall()

print("\nBase de donnees creee avec succes !")
print(f"Fichier : {db_file}\n")
print("Tables creees :")
for table in tables:
    print(f"  - {table[0]}")

# Verifier les donnees inserees
cursor.execute("SELECT COUNT(*) FROM models")
model_count = cursor.fetchone()[0]

cursor.execute("SELECT COUNT(*) FROM admins")
admin_count = cursor.fetchone()[0]

print(f"\nDonnees inserees :")
print(f"  - {model_count} modeles")
print(f"  - {admin_count} administrateur(s)")

conn.close()
print("\nInitialisation terminee !")
