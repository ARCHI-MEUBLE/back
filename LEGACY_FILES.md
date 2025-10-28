# Fichiers Legacy - Backend ArchiMeuble

Ce document liste les fichiers potentiellement inutilisés ou legacy dans le repository backend.

## 🔍 Analyse des Fichiers

### ✅ Fichiers Essentiels (À GARDER)

#### Structure Backend Principale
- `backend/` - **ESSENTIEL** - API REST structurée
  - `backend/api/` - Tous les endpoints
  - `backend/models/` - Modèles de données
  - `backend/core/` - Classes utilitaires
  - `backend/config/` - Configuration CORS/sessions

#### Configuration Docker
- `docker-compose.yml` - **ESSENTIEL** - Configuration Docker
- `Dockerfile` - **ESSENTIEL** - Image Docker
- `init_db.sh` - **ESSENTIEL** - Initialisation base de données
- `router.php` - **ESSENTIEL** - Routeur principal du backend

#### Scripts Python
- `procedure.py` - **ESSENTIEL** - Génération modèles 3D
- `requirements.txt` - **ESSENTIEL** - Dépendances Python

#### Documentation
- `README.md` - **ESSENTIEL** - Documentation principale
- `API_DOCUMENTATION.md` - **UTILE** - Documentation API
- `.env.example` - **ESSENTIEL** - Template configuration

### ⚠️ Fichiers Suspects (À VÉRIFIER)

#### Fichiers PHP Numérotés (Probablement Legacy)
Ces fichiers semblent être des tests ou versions anciennes:
- `00.php` - Script de test?
- `01.php` - Script de test?
- `02.php` - Script de test?
- `02bis.html` - HTML de test?
- `03.php` - Script de test?

**Action recommandée**: Vérifier s'ils sont utilisés, sinon SUPPRIMER

#### Anciens Formulaires (Remplacés par l'API)
- `formulaire.php` - Ancien formulaire? Remplacé par API?
- `formulaire_prix.php` - Calcul prix? Remplacé par API?
- `acheter.php` - Ancien système achat? Remplacé par API?
- `ajouteraupanier.php` - Ancien panier? Remplacé par API cart/
- `ajouterpresta.php` - Anciennes prestations?
- `viderpanier.php` - Ancien panier? Remplacé par API cart/clear

**Action recommandée**: Si non utilisés, DÉPLACER vers un dossier `legacy/` ou SUPPRIMER

#### Scripts Administratifs Locaux
- `create_admin.php` - Admin créé via init_db.sh maintenant?
- `update_admin_password.php` - Utilitaire admin
- `init_database_local.php` - Init locale? Doublon avec init_db.sh?
- `test_insert.php` - Script de test
- `test_shell.php` - Script de test
- `test_sqlite_command.php` - Script de test
- `display_client.php` - Affichage debug?

**Action recommandée**: GARDER les utilitaires (create_admin, update_admin_password), SUPPRIMER les tests

#### Fichiers de Tests et Debug
- `chatgpt.php` - Debug ChatGPT?
- `verify_setup.sh` - Script de vérification
- `save_image.php` - Sauvegarde image?
- `download_pieces.php` - Download pièces?
- `menuisier.php` - Ancien système menuisier?

**Action recommandée**: SUPPRIMER ou DÉPLACER vers `tests/`

### ❌ Fichiers À SUPPRIMER ou IGNORER

#### Fichiers de Configuration Next.js/TypeScript (ERREUR)
Ces fichiers ne devraient PAS être dans le backend:
- `next.config.js` - Configuration Next.js (FRONTEND ONLY)
- `next-env.d.ts` - TypeScript Next.js (FRONTEND ONLY)
- `tsconfig.json` - Configuration TypeScript (FRONTEND ONLY)
- `tailwind.config.ts` - Configuration Tailwind (FRONTEND ONLY)
- `postcss.config.js` - Configuration PostCSS (FRONTEND ONLY)
- `package.json` - NPM (si utilisé pour backend, OK, sinon FRONTEND ONLY)
- `.eslintrc.json` - ESLint (FRONTEND ONLY?)
- `styles/` - Styles CSS (FRONTEND ONLY)

**Action recommandée**: SUPPRIMER ces fichiers du backend (ils sont dans front/)

#### Fichiers de Données/Exemples
- `devis.json` - Exemple de devis? Legacy?
- `produit.json` - Exemple de produit? Legacy?
- `example.txt` - Exemple
- `code_python.txt` - Code Python? Doublon avec procedure.py?
- `facteur_prix.txt` - Facteurs de prix? En dur maintenant?
- `notice.txt` - Notice

**Action recommandée**: Si pas utilisés, SUPPRIMER

#### Images et Modèles 3D (Générés)
- `logo.png` - Logo (OK si utilisé, sinon frontend)
- `presta.png` - Image presta
- `meuble.glb` - Modèle test
- `meuble1.png`, `meuble2.png`, `meuble3.png` - Previews
- `meublen.glb`, `meublenp.glb`, `meublep.glb` - Modèles tests

**Action recommandée**: SUPPRIMER (fichiers générés, à ignorer dans .gitignore)

#### Dossiers Potentiellement Legacy
- `crm/` - Ancien CRM? Remplacé par admin dashboard?
- `devis/` - Anciens devis? Remplacés par orders?
- `php_local/` - Tests PHP locaux?
- `SVGnest/` - Bibliothèque SVG nesting (utilisée?)
- `decoupe_a_la_piece/` - Découpe? Utilisé?

**Action recommandée**: Vérifier le contenu, DÉPLACER vers `legacy/` ou SUPPRIMER

#### Fichiers de Tests HTTP
- `api-tests.http` - Tests REST (GARDER pour développement)
- `ArchiMeuble_Postman_Collection.json` - Collection Postman (GARDER)
- `http-client.env.json` - Environnement HTTP client (GARDER)

**Action recommandée**: GARDER (utiles pour tester l'API)

#### Scripts PowerShell
- `kill_php_servers.ps1` - Utilitaire pour tuer serveurs PHP locaux

**Action recommandée**: GARDER (utile en dev local sans Docker)

#### Fichiers Backup
- `requirements_backup.txt` - Backup requirements

**Action recommandée**: SUPPRIMER (garder seulement requirements.txt)

### 📊 Résumé des Actions

#### À SUPPRIMER IMMÉDIATEMENT (Fichiers Frontend dans Backend)
```
next.config.js
next-env.d.ts
tsconfig.json
tailwind.config.ts
postcss.config.js
styles/
```

#### À SUPPRIMER (Tests et Fichiers Générés)
```
00.php
01.php
02.php
02bis.html
03.php
test_insert.php
test_shell.php
test_sqlite_command.php
meuble.glb
meuble1.png
meuble2.png
meuble3.png
meublen.glb
meublenp.glb
meublep.glb
requirements_backup.txt
example.txt
notice.txt
```

#### À VÉRIFIER PUIS DÉCIDER
```
formulaire.php
formulaire_prix.php
acheter.php
ajouteraupanier.php
ajouterpresta.php
viderpanier.php
chatgpt.php
save_image.php
download_pieces.php
menuisier.php
display_client.php
init_database_local.php
devis.json
produit.json
code_python.txt
facteur_prix.txt
```

#### Dossiers À VÉRIFIER
```
crm/
devis/
php_local/
SVGnest/
decoupe_a_la_piece/
```

#### À GARDER
```
backend/
database/
models/
pieces/
textures/
uploads/
docker-compose.yml
Dockerfile
init_db.sh
router.php
procedure.py
requirements.txt
README.md
API_DOCUMENTATION.md
.env.example
.gitignore
api-tests.http
ArchiMeuble_Postman_Collection.json
http-client.env.json
kill_php_servers.ps1
create_admin.php
update_admin_password.php
verify_setup.sh
TEST_DOCKER.md
```

## 🚀 Plan d'Action Recommandé

### Option 1: Nettoyage Agressif (Recommandé pour nouveau départ)

```powershell
cd back

# 1. Créer un dossier legacy
mkdir legacy

# 2. Déplacer les fichiers suspects vers legacy
mv 00.php 01.php 02.php 02bis.html 03.php legacy/
mv formulaire*.php acheter.php ajouteraupanier.php ajouterpresta.php viderpanier.php legacy/
mv chatgpt.php save_image.php download_pieces.php menuisier.php display_client.php legacy/
mv test_*.php legacy/
mv meuble*.glb meuble*.png legacy/
mv devis.json produit.json code_python.txt facteur_prix.txt example.txt notice.txt legacy/
mv requirements_backup.txt legacy/

# 3. Déplacer les dossiers suspects
mv crm/ devis/ php_local/ SVGnest/ decoupe_a_la_piece/ legacy/

# 4. Supprimer fichiers frontend erronés
rm next.config.js next-env.d.ts tsconfig.json tailwind.config.ts postcss.config.js
rm -r styles/

# 5. Ajouter legacy/ au .gitignore
echo "legacy/" >> .gitignore
```

### Option 2: Nettoyage Prudent (Tester avant suppression)

1. **Créer une branche de test**: `git checkout -b cleanup-test`
2. **Déplacer vers legacy/** au lieu de supprimer
3. **Tester l'application complète**
4. **Si tout fonctionne**, merger dans dev et supprimer legacy/
5. **Si des fichiers manquent**, les restaurer depuis legacy/

## ⚠️ Attention

- **NE PAS** supprimer `backend/`, `database/`, `models/`, `pieces/`, `textures/`, `uploads/`
- **NE PAS** supprimer `docker-compose.yml`, `Dockerfile`, `init_db.sh`, `router.php`
- **NE PAS** supprimer `procedure.py`, `requirements.txt`
- **TOUJOURS** tester après nettoyage

## 📝 Mise à Jour du .gitignore

Ajouter ces lignes au `.gitignore` du backend:

```gitignore
# Legacy files (à supprimer après validation)
legacy/

# Fichiers générés (ne pas commiter)
*.glb
*.png
*.jpg
models/
uploads/

# Tests locaux
test_*.php
*_test.php
```

---

**Date d'analyse**: 2024
**Recommandation**: Nettoyage progressif en commençant par les fichiers frontend erronés et les tests évidents
