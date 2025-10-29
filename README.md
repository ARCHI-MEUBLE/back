# ArchiMeuble — Backend API

Backend PHP pour ArchiMeuble avec génération de meubles 3D paramétriques. Le projet utilise **Docker**, **PHP**, **SQLite**, et **Python** pour la génération de modèles 3D.

## Prérequis

- **Docker** et **Docker Compose**
- Aucune autre installation nécessaire (PHP, Python, SQLite sont inclus dans le conteneur)

## Installation et démarrage

### 1. Cloner le repository

```bash
git clone <votre-repo-backend>
cd back
```

### 2. Configuration (optionnel)

Le backend utilise Docker et toutes les variables d'environnement sont déjà configurées dans `docker-compose.yml`.

**Aucune configuration manuelle nécessaire !**

Si vous souhaitez personnaliser la configuration, vous pouvez modifier `docker-compose.yml` :

```yaml
environment:
  - DB_PATH=/app/database/archimeuble.db      # Chemin de la base de données
  - PYTHON_PATH=/opt/venv/bin/python3          # Chemin Python
  - FRONTEND_URL=http://localhost:3000         # URL du frontend (CORS)
  - OUTPUT_DIR=/app/models                     # Dossier des modèles 3D
```

**Note** : Le fichier `.env` présent dans le dossier n'est **pas utilisé** avec Docker. Il est conservé uniquement pour compatibilité avec d'éventuels tests locaux sans Docker.

### 3. Lancer le backend avec Docker

```bash
docker compose up -d
```

**Important** : Au premier démarrage, le conteneur va :
- Créer automatiquement la base de données SQLite
- Initialiser toutes les tables nécessaires
- Insérer les 3 modèles de meubles par défaut
- Créer l'administrateur par défaut

Attendez quelques secondes que l'initialisation se termine. Vous pouvez vérifier les logs avec :

```bash
docker logs archimeuble-backend
```

Vous devriez voir :
```
Initialisation de la base de données ArchiMeuble...
Chemin de la base de données: /app/database/archimeuble.db
Répertoires créés dans /app
Base de données initialisée avec succès!
```

Le backend est maintenant accessible sur **http://localhost:8000**

### Options de lancement

```bash
# Lancer en arrière-plan
docker compose up -d

# Arrêter le backend
docker compose down

# Voir les logs
docker logs archimeuble-backend

# Suivre les logs en temps réel
docker logs -f archimeuble-backend

# Redémarrer le backend
docker compose restart

# Reconstruire l'image (après modification du Dockerfile)
docker compose build
docker compose up
```

## Configuration automatique

Au démarrage du conteneur Docker, le script `init_db.sh` s'exécute automatiquement pour :

✅ Créer toutes les tables nécessaires (users, admins, models, configurations, etc.)
✅ Insérer les 3 modèles de meubles TV par défaut
✅ Créer un administrateur par défaut

### Identifiants administrateur par défaut

- **Email** : `admin@archimeuble.com`
- **Mot de passe** : `admin123`

**Important** : Changez ces identifiants en production !

## Base de données

**Emplacement** : `back/database/archimeuble.db`

La base de données SQLite est :
- ✅ Initialisée automatiquement au démarrage
- ✅ Persistante (stockée dans `back/database/`)
- ✅ Accessible depuis le conteneur et l'hôte

### Tables créées automatiquement

- `users` - Utilisateurs du configurateur
- `admins` - Administrateurs
- `models` - Modèles de meubles (3 modèles pré-chargés)
- `configurations` - Configurations sauvegardées
- `sessions` - Sessions utilisateurs
- `clients` - Clients de l'entreprise
- `projets` - Projets clients
- `devis` - Devis générés

### Accéder à la base de données

```bash
# Via Docker
docker compose exec backend sqlite3 /app/database/archimeuble.db

# Exemples de commandes SQLite
docker compose exec backend bash -c "sqlite3 /app/database/archimeuble.db '.tables'"
docker compose exec backend bash -c "sqlite3 /app/database/archimeuble.db 'SELECT * FROM models;'"
```

## Endpoints API principaux

### Modèles de meubles

- `GET /api/models` - Liste tous les modèles
- `GET /api/models?id={id}` - Récupère un modèle spécifique
- `POST /api/models` - Crée un nouveau modèle (admin uniquement)
- `PUT /api/models` - Modifie un modèle (admin uniquement)
- `DELETE /api/models` - Supprime un modèle (admin uniquement)

### Génération 3D

- `POST /api/generate` - Génère un fichier GLB à partir d'un prompt
  ```json
  {
    "prompt": "M1(1700,500,730)EFH3(F,T,F)"
  }
  ```

### Authentification

#### Utilisateurs
- `POST /api/auth/register` - Inscription utilisateur
- `POST /api/auth/login` - Connexion utilisateur
- `GET /api/auth/session` - Vérifier session utilisateur
- `DELETE /api/auth/logout` - Déconnexion utilisateur

#### Administrateurs
- `POST /api/admin/login` - Connexion admin
- `POST /api/admin/logout` - Déconnexion admin
- `GET /api/admin/session` - Vérifier session admin

## Structure du projet

```
back/
├── backend/
│   ├── api/              # Endpoints API REST
│   │   ├── models.php
│   │   ├── generate.php
│   │   ├── admin.php
│   │   ├── admin-auth.php
│   │   └── auth.php
│   ├── core/             # Classes principales
│   │   ├── Database.php
│   │   ├── Session.php
│   │   ├── Router.php
│   │   └── Cors.php
│   ├── models/           # Modèles de données
│   │   ├── Model.php
│   │   ├── Admin.php
│   │   └── User.php
│   └── python/           # Scripts Python
│       ├── procedure_real.py
│       └── textures/
├── database/
│   └── archimeuble.db    # Base de données SQLite (UNIQUE)
├── docker-compose.yml    # Configuration Docker
├── Dockerfile            # Image Docker
├── init_db.sh           # Script d'initialisation de la BDD
└── router.php           # Point d'entrée
```

## Format des prompts

Les prompts suivent le format `M[type](largeur,profondeur,hauteur)MODULES(params)`:

- **M1** à **M5**: Type de meuble (1-5)
- **Dimensions**: largeur, profondeur, hauteur en mm
- **Modules**: E (étagère), F (façade), H (hauteur), b (base), S (séparateur)
- **Paramètres**: T/F (true/false), nombres, etc.

Exemples:
```
M1(1700,500,730)EFH3(F,T,F)           # 3 modules, 1700mm de large
M1(2000,400,600)EFH4(T,T,F,F)         # 4 modules, 2000mm de large
M1(1200,350,650)EFH2(F,T)             # 2 modules, 1200mm de large
```

## Génération de fichiers 3D

Les fichiers GLB générés sont sauvegardés dans:
```
../front/public/models/
```

Le backend génère les modèles 3D qui sont ensuite servis par le frontend Next.js.

## Configuration Docker

### Variables d'environnement

Définies dans `docker-compose.yml`:

```yaml
environment:
  - DB_PATH=/app/database/archimeuble.db
  - PYTHON_PATH=/opt/venv/bin/python3
  - FRONTEND_URL=http://localhost:3000
  - OUTPUT_DIR=/app/models
```

### Volumes

```yaml
volumes:
  - .:/app                              # Code source backend
  - ../front/public/models:/app/models  # Dossier de sortie des modèles 3D
```

## Tests

Pour tester l'API:

```bash
# Tester la génération 3D
curl -X POST http://localhost:8000/api/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt":"M1(1700,500,730)EFH3(F,T,F)"}'

# Lister les modèles
curl http://localhost:8000/api/models

# Créer un utilisateur
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Test123!","name":"Test User"}'

# Connexion admin
curl -X POST http://localhost:8000/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@archimeuble.com","password":"admin123"}'
```

## Dépannage

### Le conteneur ne démarre pas

```bash
# Voir les logs
docker logs archimeuble-backend

# Reconstruire l'image
docker compose down
docker compose build --no-cache
docker compose up
```

### Erreur "port 8000 already in use"

Un autre processus utilise le port 8000. Options :

1. Arrêter l'autre processus :
```bash
# Windows
netstat -ano | findstr :8000
taskkill /PID <PID> /F

# Linux/Mac
lsof -ti:8000 | xargs kill -9
```

2. Changer le port dans `docker-compose.yml` :
```yaml
ports:
  - "8001:8000"  # Utiliser le port 8001 au lieu de 8000
```

### La base de données n'a pas été créée ou est vide

Si le fichier `database/archimeuble.db` n'existe pas ou est vide après `docker compose up`, suivez ces étapes :

**1. Vérifier les logs du conteneur :**
```bash
docker logs archimeuble-backend
```

Recherchez les messages d'erreur lors de l'initialisation de la base de données.

**2. Arrêter et supprimer complètement le conteneur :**
```bash
docker compose down
```

**3. Optionnel - Nettoyer la base de données existante :**
```bash
# Sur Windows
del database\archimeuble.db

# Sur Linux/Mac
rm database/archimeuble.db
```

**4. Reconstruire et redémarrer :**
```bash
docker compose build --no-cache
docker compose up -d
```

**5. Vérifier que la base de données a été créée :**
```bash
# Vérifier que le fichier existe
dir database\archimeuble.db  # Windows
ls -l database/archimeuble.db  # Linux/Mac

# Vérifier le contenu
docker compose exec backend sqlite3 /app/database/archimeuble.db ".tables"
```

Vous devriez voir toutes les tables : `users`, `admins`, `models`, `configurations`, etc.

### Erreur "Database locked"

Arrêter tous les conteneurs et redémarrer :

```bash
docker compose down
docker compose up
```

### Problèmes de permissions

Sur Linux/Mac, si vous avez des problèmes de permissions :

```bash
chmod -R 755 database/
chmod 666 database/archimeuble.db
```

## Développement

### Hot reload

Le code source est monté via un volume Docker. Toute modification du code PHP est immédiatement prise en compte (pas besoin de redémarrer le conteneur).

### Ajouter des dépendances Python

1. Modifier `requirements.txt`
2. Reconstruire l'image :
```bash
docker compose down
docker compose build
docker compose up
```

### Accéder au shell du conteneur

```bash
docker compose exec backend bash
```

## Structure de l'architecture

```
archimeuble/
├── back/     (ce repository - Backend PHP + Docker)
│   ├── database/
│   │   └── archimeuble.db  ← BASE DE DONNÉES UNIQUE
│   └── ...
└── front/    (Frontend Next.js)
    └── public/
        ├── models/         ← Fichiers GLB générés
        ├── images/         ← Images par défaut
        └── uploads/        ← Images uploadées
```

## Portabilité

✅ **Fonctionne sur n'importe quelle machine** avec Docker installé
✅ **Pas de dépendances externes** (Python, SQLite, PHP dans le conteneur)
✅ **Configuration automatique** au démarrage
✅ **Une seule base de données** pour éviter la confusion

## Liens utiles

- Documentation Docker : https://docs.docker.com/
- PHP 8.2 : https://www.php.net/
- SQLite : https://www.sqlite.org/
