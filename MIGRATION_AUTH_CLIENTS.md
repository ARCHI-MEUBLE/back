# 🔐 Migration Système d'Authentification Clients

**Date:** 28 octobre 2025  
**Statut:** ✅ RÉSOLU

## 🐛 Problème Initial

L'erreur "Email ou mot de passe incorrect" lors de la tentative de connexion avec `client1@archimeuble.com` était causée par :

1. **Table manquante** : La base de données contenait l'ancienne table `clients` (sans authentification) au lieu de la nouvelle table `customers` (avec colonne `password_hash`)
2. **Schéma obsolète** : Le script `init_db.sh` créait l'ancien schéma sans support d'authentification

## ✅ Solution Appliquée

### 1. Migration du schéma de base de données

Création de la nouvelle structure avec :
- Table `customers` (avec authentification)
- Table `saved_configurations` (configurations sauvegardées)
- Table `cart_items` (panier)
- Table `orders` (commandes)
- Table `order_items` (détails commandes)
- Table `admin_notifications` (notifications admin)

**Fichiers créés :**
- `migrate_customers.sql` - Script SQL de migration
- `create_test_customer.py` - Script Python pour créer le client de test

### 2. Création du compte client de test

**Identifiants de connexion :**
```
Email    : client1@archimeuble.com
Password : client123
```

**Informations du compte :**
- ID : 1
- Prénom : Client
- Nom : Test
- Téléphone : 0601020304
- Adresse : 123 Rue de Test, 75001 Paris
- Pays : France

### 3. Vérification de l'authentification

✅ Test API backend réussi :
```json
{
  "success": true,
  "message": "Connexion réussie",
  "customer": {
    "id": 1,
    "email": "client1@archimeuble.com",
    "first_name": "Client",
    "last_name": "Test",
    "phone": "0601020304",
    ...
  }
}
```

## 🔧 Compatibilité bcrypt

Le hash généré par Python bcrypt (`$2b$`) est **compatible** avec PHP `password_verify()` :
- Python bcrypt : préfixe `$2b$` (OpenBSD)
- PHP password_hash : préfixe `$2y$` (PHP-compatible)
- ✅ PHP >= 5.3.7 accepte les deux préfixes

## 📊 État de la Base de Données

```
customers              : 1 enregistrement
saved_configurations   : 0 enregistrement
cart_items            : 0 enregistrement
orders                : 0 enregistrement
```

## 🚀 Prochaines Étapes

1. **Tester la connexion frontend** : 
   - Ouvrir http://localhost:3000/auth/login
   - Se connecter avec `client1@archimeuble.com` / `client123`

2. **Mettre à jour init_db.sh** :
   - Intégrer le nouveau schéma dans `init_db.sh`
   - Ajouter la création des tables customers/orders au démarrage Docker

3. **Créer des comptes clients supplémentaires** :
   - Utiliser le script `create_test_customer.py`
   - Ou utiliser l'endpoint d'inscription `/api/customers/register.php`

## 📝 Notes Techniques

### Structure du Modèle Customer

Le modèle PHP `Customer.php` utilise :
- `password_hash()` pour hasher les mots de passe (BCRYPT)
- `password_verify()` pour vérifier l'authentification
- Session PHP pour maintenir l'état de connexion

### Endpoints API Disponibles

- `POST /api/customers/login.php` - Connexion
- `POST /api/customers/register.php` - Inscription
- `GET /api/customers/session.php` - Vérifier session
- `POST /api/customers/logout.php` - Déconnexion
- `GET /api/configurations/list.php` - Mes configurations
- `POST /api/configurations/save.php` - Sauvegarder config

## ⚠️ Important

La table `clients` (ancien schéma) existe toujours mais n'est **pas utilisée** par le nouveau système d'authentification. Elle peut être supprimée ou renommée si nécessaire.

---

**Résolution complète ✅** - Le système d'authentification clients fonctionne correctement !
