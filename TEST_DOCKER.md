# Tests pour vérifier que l'erreur JSON.parse est résolue

## Causes possibles de l'erreur "JSON.parse: unexpected character"

L'erreur se produit quand le backend PHP retourne **autre chose que du JSON** :

### 1. Erreur fatale PHP
```
Fatal error: Uncaught Exception: Base de données introuvable...
```
→ Le frontend essaie de parser du HTML d'erreur comme JSON

### 2. Erreur SQLite / Database
```
Erreur : Base de données introuvable à : /app/database/archimeuble.db
```
→ Message texte brut au lieu de JSON

### 3. Erreur Python
```
Python: command not found
ModuleNotFoundError: No module named 'trimesh'
```
→ Erreur shell au lieu de JSON

### 4. Chemin hardcodé Anaconda
```
F:\ANACONDA\python.exe: No such file or directory
```
→ Sur une autre machine, Python n'existe pas à cet endroit

## Ce que Docker résout

### ✅ 1. Database.php utilise maintenant PDO
**Avant** (ligne 12) :
```php
private $sqlite3Command = 'F:\\ANACONDA\\Library\\bin\\sqlite3.exe';
```
→ ❌ Chemin hardcodé, n'existe pas sur d'autres machines

**Après** :
```php
$this->pdo = new PDO('sqlite:' . $this->dbPath);
```
→ ✅ PDO natif PHP, fonctionne partout

### ✅ 2. generate.php détecte Python automatiquement
**Avant** (ligne 123) :
```php
$pythonExe = 'F:\\ANACONDA\\python.exe';
```
→ ❌ Chemin hardcodé Windows

**Après** (ligne 126) :
```php
$pythonExe = getenv('PYTHON_PATH') ?: 'python3';
```
→ ✅ Utilise la variable d'environnement Docker ou fallback

### ✅ 3. Toutes les dépendances Python dans Docker
**Avant** :
- Dépend d'Anaconda installé localement
- Dépend que trimesh, pyvista, numpy, pillow soient installés

**Après** :
- Docker installe automatiquement toutes les dépendances
- Environnement isolé et reproductible

## Tests à faire pour vérifier

### Test 1 : Lancer le backend avec Docker

```bash
cd back
docker-compose build
docker-compose up
```

**Attendu** :
```
backend_1  | PHP 8.2.x Development Server (http://0.0.0.0:8000) started
```

**Erreur possible si la DB n'existe pas** :
```
Erreur : Base de données introuvable à : /app/database/archimeuble.db
```

**Solution** :
```bash
docker-compose --profile init up db-init
```

### Test 2 : Appeler l'API models

```bash
curl http://localhost:8000/api/models
```

**Attendu (JSON valide)** :
```json
[
  {
    "id": 1,
    "name": "Meuble Scandinave 3 modules",
    "prompt": "M1(1700,500,730)EFH3(F,T,F)",
    "price": 450.00
  }
]
```

**Erreur si DB manquante (HTML/texte)** :
```
Fatal error: Uncaught Exception: Base de données introuvable...
```
→ Frontend crashe avec "JSON.parse: unexpected character"

### Test 3 : Générer un meuble 3D

```bash
curl -X POST http://localhost:8000/api/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt":"M1(1700,500,730)EFH3(F,T,F)"}'
```

**Attendu (JSON valide)** :
```json
{
  "success": true,
  "glb_url": "/models/meuble_xxxxx.glb",
  "prompt": "M1(1700,500,730)EFH3(F,T,F)"
}
```

**Erreur si Python manque (texte brut)** :
```
sh: 1: python3: not found
```
→ Frontend crashe avec "JSON.parse: unexpected character"

## Checklist avant de donner à vos camarades

- [ ] La base de données `database/archimeuble.db` existe
- [ ] Le dossier `front/public/models` existe
- [ ] Docker Desktop est installé et démarré
- [ ] Le backend démarre sans erreur : `docker-compose up`
- [ ] L'API models répond du JSON : `curl http://localhost:8000/api/models`
- [ ] Le frontend se connecte au backend sans erreur

## Si l'erreur persiste avec Docker

1. **Vérifier les logs du backend** :
```bash
docker-compose logs -f backend
```

2. **Vérifier que la DB est montée** :
```bash
docker exec -it archimeuble-backend ls -la /app/database/
```

3. **Vérifier que Python fonctionne** :
```bash
docker exec -it archimeuble-backend python3 --version
docker exec -it archimeuble-backend pip list
```

4. **Tester manuellement une requête** :
```bash
docker exec -it archimeuble-backend php -r "require 'backend/core/Database.php'; echo 'DB OK';"
```

## Garantie

Si Docker est correctement configuré et que :
- La base de données existe dans `database/archimeuble.db`
- Le backend démarre avec `docker-compose up`
- Les 3 repos sont au même niveau (back, front, database)

Alors **l'erreur JSON.parse ne devrait PLUS se produire** car :
1. PHP utilise PDO natif (pas de dépendance externe)
2. Python est dans Docker avec toutes les dépendances
3. Les chemins ne sont plus hardcodés
4. Toutes les erreurs retournent du JSON propre
