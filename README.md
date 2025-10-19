# ArchiCreator - Configurateur de Meubles 3D

Application web permettant de configurer et visualiser des meubles en 3D, avec génération automatique de fichiers de découpe et devis.

## Prérequis

- **Python 3.8+** installé et ajouté au PATH Windows
- **PHP 8.x** installé et ajouté au PATH Windows
- Git (pour cloner le projet)

## Installation et Lancement

### 1. Cloner le projet

```bash
git clone <URL_DE_VOTRE_REPO>
cd gauthier-2
```

### 2. Créer l'environnement virtuel Python

```bash
python -m venv .archimeuble
```

Cette commande crée un environnement virtuel isolé dans le dossier `.archimeuble`.

### 3. Activer l'environnement virtuel

**Sur Windows (cmd) :**
```bash
.\.archimeuble\Scripts\activate
```

**Sur Windows (PowerShell) :**
```bash
.\.archimeuble\Scripts\Activate.ps1
```

**Sur Linux/Mac :**
```bash
source .archimeuble/bin/activate
```

Vous devriez voir `(.archimeuble)` apparaître au début de votre ligne de commande.

### 4. Installer les dépendances Python

```bash
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

L'installation peut prendre plusieurs minutes (notamment pour VTK/PyVista).

### 5. Créer l'alias python3 (Windows uniquement)

```bash
copy ".\.archimeuble\Scripts\python.exe" ".\.archimeuble\Scripts\python3.exe"
```

### 6. Vérifier l'installation

```bash
python -c "import numpy, pyvista, vtk, trimesh, ezdxf, PIL, svgwrite, scipy, networkx; print('OK - Toutes les dépendances sont installées')"
```

### 7. Démarrer le serveur PHP

**IMPORTANT :** Le serveur PHP doit être lancé depuis le terminal où l'environnement virtuel Python est activé.

```bash
php -S localhost:8000
```

Si le port 8000 est déjà utilisé :
```bash
php -S localhost:8001
```

### 8. Accéder à l'application

Ouvrir un navigateur et aller sur :
- **Page d'accueil :** http://localhost:8000/00.php
- **Configurateur de meuble :** http://localhost:8000/02.php

## Utilisation quotidienne

Après la première installation, pour lancer l'application :

1. Ouvrir un terminal dans le dossier du projet
2. Activer l'environnement virtuel : `.\.archimeuble\Scripts\activate`
3. Lancer le serveur PHP : `php -S localhost:8000`
4. Ouvrir le navigateur sur http://localhost:8000/00.php

## Structure du projet

```
.
├── .archimeuble/          # Environnement virtuel Python (non versionné)
├── crm/                   # Données CRM
├── decoupe_a_la_piece/   # Fichiers de découpe
├── devis/                # Devis générés
├── pieces/               # Pièces générées
├── SVGnest/              # Module de nesting SVG
├── textures/             # Textures pour les rendus 3D
├── uploads/              # Fichiers uploadés
├── *.php                 # Pages PHP de l'application
├── procedure.py          # Script Python principal de génération
├── .gitignore           # Fichiers à ignorer par Git
└── README.md            # Ce fichier
```

## Dépannage

### Python introuvable ou "Code de retour : 9009"
- Vérifier que l'environnement virtuel est bien activé
- Vérifier que `python3.exe` existe dans `.archimeuble\Scripts\`
- Relancer le serveur PHP depuis le terminal activé

### Module manquant (ex: "No module named 'scipy'")
```bash
# Activer l'environnement virtuel puis :
python -m pip install scipy
# Ou réinstaller toutes les dépendances :
python -m pip install -r requirements.txt
```

### Erreurs d'écriture de fichiers (GLB, DXF, JSON)
- OneDrive peut verrouiller les fichiers
- Mettre la synchronisation OneDrive en pause
- Ou déplacer le projet hors du dossier OneDrive

### Le serveur PHP ne démarre pas (port déjà utilisé)
```bash
# Changer de port :
php -S localhost:8001
# Adapter l'URL dans le navigateur
```

### "Address already in use"
```bash
# Un autre serveur PHP tourne déjà
# Fermer les autres terminaux ou tuer le processus :
taskkill /F /IM php.exe
# Relancer le serveur
```

## Logs et Débogage

En cas d'erreur, consulter le fichier `log.txt` à la racine du projet.
Ce fichier contient les sorties des scripts Python et les messages d'erreur.

## Arrêt du serveur

Dans le terminal où le serveur PHP tourne :
- Appuyer sur `Ctrl + C`
- Optionnel : désactiver l'environnement virtuel avec `deactivate`

## Dépendances Python

- numpy : Calculs numériques
- pyvista : Visualisation 3D
- vtk : Toolkit de visualisation
- trimesh : Manipulation de maillages 3D
- ezdxf : Génération de fichiers DXF
- Pillow : Traitement d'images
- svgwrite : Génération de fichiers SVG
- scipy : Calculs scientifiques
- networkx : Manipulation de graphes
- svgpathtools : Manipulation de chemins SVG

## Contribuer

1. Créer une branche pour votre fonctionnalité : `git checkout -b feature/ma-fonctionnalite`
2. Commiter vos changements : `git commit -m "Ajout de ma fonctionnalité"`
3. Pousser vers la branche : `git push origin feature/ma-fonctionnalite`
4. Créer une Pull Request

## License

[À définir]

## Auteurs

[À définir]
