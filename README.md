# ArchiMeuble — Backend API

Backend PHP pour ArchiMeuble avec génération de meubles 3D paramétriques. Le projet utilise **PHP**, **SQLite**, et **Python** pour la génération de modèles 3D.

## Prérequis

- PHP 8.0 ou plus récent
- Python 3.8+ (recommandé: Anaconda)
- SQLite3
- Extensions PHP:
  - PDO SQLite
  - JSON

### Dépendances Python

```bash
pip install trimesh pyvista numpy pillow
```

## Installation

1. Cloner le repository:
```bash
git clone <votre-repo-back>
cd back
```

2. Configurer l'environnement:
```bash
cp .env.example .env
```

3. Modifier le fichier `.env` avec vos paramètres:
```
DB_PATH=./database/archimeuble.db
PYTHON_PATH=F:\ANACONDA\python.exe
FRONTEND_URL=http://localhost:3000
```

4. Initialiser la base de données:
```bash
php backend/core/init_db.php
```

## Démarrage du serveur

### Développement (port 8000)

```bash
php -S localhost:8000 router.php
```

Le serveur PHP sera accessible sur [http://localhost:8000](http://localhost:8000)

### En production

Configurer Apache ou Nginx pour pointer vers `router.php` comme point d'entrée.

## Endpoints API principaux

### Modèles de meubles
- `GET /api/models` - Liste tous les modèles
- `GET /api/models/{id}` - Récupère un modèle spécifique
- `POST /api/models` - Crée un nouveau modèle (admin)
- `PUT /api/models/{id}` - Modifie un modèle (admin)
- `DELETE /api/models/{id}` - Supprime un modèle (admin)

### Génération 3D
- `POST /api/generate` - Génère un fichier GLB à partir d'un prompt
  ```json
  {
    "prompt": "M1(1700,500,730)EFH3(F,T,F)"
  }
  ```

### Authentification admin
- `POST /api/admin/login` - Connexion admin
- `GET /api/admin/check` - Vérifier session admin

## Structure du projet

```
back/
├── backend/
│   ├── api/              # Endpoints API REST
│   │   ├── models.php
│   │   ├── generate.php
│   │   └── admin-auth.php
│   ├── core/             # Classes principales
│   │   ├── Database.php
│   │   ├── Session.php
│   │   └── Router.php
│   ├── models/           # Modèles de données
│   │   ├── Model.php
│   │   └── Admin.php
│   └── python/           # Scripts Python
│       ├── procedure_real.py
│       └── textures/
├── database/
│   └── archimeuble.db    # Base de données SQLite
└── router.php            # Point d'entrée
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

## CORS et sécurité

Le backend est configuré pour accepter les requêtes du frontend sur `http://localhost:3000`. Pour modifier:

```php
// Dans chaque fichier API
header('Access-Control-Allow-Origin: http://localhost:3000');
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
```

## Notes importantes

- Le backend et le frontend sont dans des repositories séparés
- Le backend génère les fichiers GLB directement dans `../front/public/models/`
- Assurez-vous que le frontend est cloné au même niveau que le backend:
  ```
  archimeuble/
  ├── back/    (ce repository)
  └── front/   (repository frontend)
  ```

## Dépannage

### Erreur "Database locked"
Vérifier les permissions du fichier `database/archimeuble.db`

### Erreur Python "Module not found"
Vérifier le chemin Python dans `.env` et installer les dépendances

### CORS errors
Vérifier que `FRONTEND_URL` dans `.env` correspond à l'URL du frontend
