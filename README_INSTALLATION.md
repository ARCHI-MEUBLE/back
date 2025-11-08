# ArchiMeuble Backend - Guide d'Installation

## 📋 Prérequis

- Docker Desktop installé et fonctionnel
- Git (pour cloner le projet)

## 🚀 Installation Rapide

### 1. Cloner le projet

```bash
git clone <votre-repo>
cd archimeuble_new_clone/back
```

### 2. Configuration de l'environnement

Le fichier `.env` contient déjà toutes les configurations nécessaires, notamment :
- Clés Stripe (mode TEST)
- Configuration SMTP pour les emails
- Configuration Calendly
- Configuration Crisp Chat

**Important** : Le fichier `.env` est déjà configuré avec des valeurs par défaut. Vous n'avez rien à modifier pour un environnement de développement local.

### 3. Lancer l'application avec Docker

**IMPORTANT** : Sur Windows, le fichier `install_dependencies.sh` doit avoir des fins de ligne Unix (LF) et non Windows (CRLF).

```bash
# Convertir les fins de ligne (OBLIGATOIRE sur Windows)
sed -i 's/\r$//' install_dependencies.sh

# Construire et lancer les conteneurs
docker compose up -d --build

# Vérifier que tout fonctionne
docker compose logs -f backend
```

Le backend sera accessible sur **http://localhost:8000**

**Note** : Au premier démarrage, le script `install_dependencies.sh` télécharge automatiquement le SDK Stripe PHP et FPDF. Cela peut prendre quelques secondes.

### 4. Initialiser la base de données (première fois seulement)

La base de données est automatiquement initialisée au démarrage du conteneur.

Identifiants admin par défaut :
- **Username** : `admin`
- **Password** : `admin123`
- **Email** : `admin@archimeuble.com`

## 📦 Dépendances PHP

Les dépendances PHP (Stripe SDK, FPDF) sont automatiquement installées lors du build Docker via le script `install_dependencies.sh`.

### Installation manuelle des dépendances (si nécessaire)

Si vous avez besoin d'installer les dépendances manuellement :

```bash
# Se connecter au conteneur
docker exec -it archimeuble-backend bash

# Lancer le script d'installation
./install_dependencies.sh
```

## 🔧 Commandes Utiles

```bash
# Voir les logs en temps réel
docker compose logs -f backend

# Redémarrer le backend
docker compose restart backend

# Arrêter tous les conteneurs
docker compose down

# Reconstruire après modification du Dockerfile
docker compose up -d --build

# Accéder au shell du conteneur
docker exec -it archimeuble-backend bash
```

## 📂 Structure des Dossiers

```
back/
├── backend/           # Code source PHP
│   ├── api/          # Endpoints API
│   ├── config/       # Fichiers de configuration
│   ├── core/         # Classes principales
│   └── models/       # Modèles de données
├── database/         # Base de données SQLite (persistée)
├── vendor/           # Dépendances PHP (ignoré par Git)
├── .env              # Variables d'environnement
├── Dockerfile        # Configuration Docker
├── docker-compose.yml
└── install_dependencies.sh  # Script d'installation des dépendances
```

## 🔐 Configuration Stripe

Le projet utilise les clés de test Stripe par défaut. Pour utiliser vos propres clés :

1. Créez un compte sur https://dashboard.stripe.com
2. Récupérez vos clés de test (`sk_test_...` et `pk_test_...`)
3. Modifiez le fichier `.env` :

```env
STRIPE_SECRET_KEY=sk_test_VOTRE_CLE_SECRETE
STRIPE_PUBLISHABLE_KEY=pk_test_VOTRE_CLE_PUBLIQUE
```

4. Redémarrez le conteneur :

```bash
docker compose restart backend
```

## 🐛 Résolution de Problèmes

### Le paiement ne fonctionne pas (erreur JSON)

**Symptôme** : `Unexpected token '<', "<br /> <b>"... is not valid JSON`

**Cause** : Le SDK Stripe n'est pas installé

**Solution** :
1. Vérifiez que le dossier `vendor/` existe et contient `stripe/`
2. Si manquant, reconstruisez le conteneur : `docker compose up -d --build`

### Erreur "vendor/stripe/init.php not found"

Le script `install_dependencies.sh` s'exécute automatiquement lors du build Docker et télécharge le SDK Stripe.

Si l'erreur persiste :
```bash
docker exec -it archimeuble-backend bash
./install_dependencies.sh
```

### La base de données ne se crée pas

```bash
# Supprimer la base existante
rm -rf database/*.db

# Redémarrer le conteneur
docker compose restart backend
```

## 📝 Notes Importantes

- Le dossier `vendor/` est ignoré par Git (voir `.gitignore`)
- Les dépendances sont automatiquement installées lors du build Docker
- La base de données est persistée dans le dossier `database/`
- Le fichier `.env` contient des secrets et ne doit **jamais** être commité en production

## 🔄 Mise à Jour

Pour mettre à jour le projet après un `git pull` :

```bash
# Reconstruire l'image Docker
docker compose up -d --build

# Vérifier les logs
docker compose logs -f backend
```
