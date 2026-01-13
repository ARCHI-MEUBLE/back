# ⚡ Guide d'Installation Express - Module Façades

## 🎯 Ce que vous devez faire

### Étape 1 : Double-cliquez sur ce fichier
```
📁 back/
   └── migrate_facades.bat  👈 DOUBLE-CLIQUEZ ICI
```

### Étape 2 : Attendez la fin
Vous verrez :
```
========================================
  Migration du module Facades (Docker)
========================================

[INFO] Docker est actif
[INFO] Conteneur backend trouvé
[INFO] Application de la migration SQL...
[SUCCES] Migration appliquée avec succès!
```

### Étape 3 : Accédez à la page
Ouvrez votre navigateur sur :
```
http://localhost:3000/facades
```

## ✅ C'est terminé !

---

## 🔍 Comment vérifier que ça marche ?

1. **Dans le menu du site** : Vous devriez voir un lien **"Façades"** entre "Nos modèles" et "Catalogue"

2. **Page principale** : http://localhost:3000/facades
   - Visualiseur 3D à gauche
   - Panneau de configuration à droite
   - Vous pouvez configurer dimensions, couleurs et perçages

3. **Dashboard admin** : http://localhost:3000/admin/facades
   - Gérer les matériaux
   - Gérer les types de perçages

---

## 🐛 Ça ne marche pas ?

### Problème 1 : Le fichier .bat s'ouvre dans Notepad
**Solution** : Clic droit → **"Exécuter en tant qu'administrateur"**

### Problème 2 : "Docker n'est pas lancé"
**Solution** :
1. Ouvrez Docker Desktop
2. Attendez qu'il soit prêt
3. Relancez `migrate_facades.bat`

### Problème 3 : "Conteneur backend n'existe pas"
**Solution** :
```cmd
cd back
docker-compose up -d
```
Puis relancez `migrate_facades.bat`

### Problème 4 : Le lien "Façades" n'apparaît pas
**Solution** :
1. Arrêtez le serveur frontend (Ctrl+C)
2. Relancez : `npm run dev`
3. Rafraîchissez la page

---

## 📸 À quoi ça ressemble ?

### Menu de Navigation
```
ArchiMeuble   [Nos modèles] [Façades] [Catalogue] [Échantillons] ...
                              ^^^^^^^^
                              NOUVEAU!
```

### Page Façades
```
┌──────────────────────────────────────────┐
│  Configurateur de Façades     [💾 Save] │
├──────────────────────┬───────────────────┤
│                      │  [Dimensions]     │
│   🎨 Visualisation   │                   │
│      3D de la        │  Largeur: 600 mm  │
│      façade          │  Hauteur: 800 mm  │
│                      │                   │
│   (Rotation,         │  [Matériau]       │
│    Zoom)             │  [Perçages]       │
│                      │                   │
│                      │  Prix: 58.00 €    │
└──────────────────────┴───────────────────┘
```

---

## 🎓 Prochaines Étapes

1. ✅ Installation terminée
2. 🎨 Testez la création d'une façade
3. 🔧 Explorez le dashboard admin
4. 📚 Consultez [FACADES_README.md](FACADES_README.md) pour plus de détails

---

## 💬 Besoin d'aide ?

- **Documentation complète** : [FACADES_README.md](FACADES_README.md)
- **Feuille de route** : [FACADES_ROADMAP.md](../FACADES_ROADMAP.md)
- **Architecture** : [FACADES_SUMMARY.md](../FACADES_SUMMARY.md)

**Tout est prêt ! Bonne configuration ! 🚀**
