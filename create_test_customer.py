#!/usr/bin/env python3
"""
Script pour créer un client de test avec hash BCrypt
Utilise bcrypt Python car PHP n'est pas disponible localement
"""
import sqlite3
import bcrypt

# Configuration
DB_PATH = 'database/archimeuble.db'
EMAIL = 'client1@archimeuble.com'
PASSWORD = 'client123'

# Générer le hash BCrypt
password_bytes = PASSWORD.encode('utf-8')
salt = bcrypt.gensalt()
password_hash = bcrypt.hashpw(password_bytes, salt).decode('utf-8')

print("=" * 50)
print("Création du client de test")
print("=" * 50)
print(f"\nEmail    : {EMAIL}")
print(f"Password : {PASSWORD}")
print(f"Hash     : {password_hash[:50]}...")

# Connexion à la base de données
conn = sqlite3.connect(DB_PATH)
cursor = conn.cursor()

# Vérifier si le client existe
cursor.execute("SELECT id FROM customers WHERE email = ?", (EMAIL,))
existing = cursor.fetchone()

if existing:
    print(f"\n⚠️  Client existe déjà (ID: {existing[0]}), mise à jour du mot de passe...")
    cursor.execute(
        "UPDATE customers SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?",
        (password_hash, EMAIL)
    )
else:
    print("\n➕ Création du nouveau client...")
    cursor.execute(
        """INSERT INTO customers 
           (email, password_hash, first_name, last_name, phone, address, city, postal_code, country) 
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)""",
        (EMAIL, password_hash, 'Client', 'Test', '0601020304', '123 Rue de Test', 'Paris', '75001', 'France')
    )

conn.commit()

# Vérifier la création
cursor.execute("SELECT * FROM customers WHERE email = ?", (EMAIL,))
customer = cursor.fetchone()

if customer:
    print("\n✅ Client créé/mis à jour avec succès!")
    print("\n" + "=" * 50)
    print("Informations du client:")
    print("=" * 50)
    print(f"ID         : {customer[0]}")
    print(f"Email      : {customer[1]}")
    print(f"Prénom     : {customer[3]}")
    print(f"Nom        : {customer[4]}")
    print(f"Téléphone  : {customer[5]}")
    print(f"Ville      : {customer[7]}")
    print(f"Créé le    : {customer[11]}")
    
    # Vérifier l'authentification
    print("\n" + "=" * 50)
    print("Vérification de l'authentification...")
    print("=" * 50)
    stored_hash = customer[2].encode('utf-8')
    if bcrypt.checkpw(password_bytes, stored_hash):
        print("✅ Authentification vérifiée avec succès!")
    else:
        print("❌ Erreur d'authentification!")
else:
    print("\n❌ Erreur lors de la création du client")

# Statistiques
print("\n" + "=" * 50)
print("Statistiques de la base de données:")
print("=" * 50)

tables = ['customers', 'saved_configurations', 'cart_items', 'orders']
for table in tables:
    try:
        cursor.execute(f"SELECT COUNT(*) FROM {table}")
        count = cursor.fetchone()[0]
        print(f"{table:25s} : {count} enregistrement(s)")
    except sqlite3.OperationalError:
        print(f"{table:25s} : Table non trouvée")

conn.close()
print("\n✅ Migration terminée avec succès!\n")
