# Module Façades - ArchiMeuble

## 📋 Description

Module complet de configuration de façades avec visualisation 3D interactive utilisant Three.js. Les utilisateurs peuvent créer des façades sur mesure en personnalisant les dimensions, les matériaux/couleurs et en ajoutant des perçages précis.

## ✨ Fonctionnalités

### Pour les Utilisateurs
- **Visualisation 3D en temps réel** avec React Three Fiber
- **Configuration personnalisée** :
  - Dimensions (largeur, hauteur, épaisseur) en millimètres
  - Choix parmi plusieurs matériaux et couleurs
  - Ajout de perçages (circulaires ou rectangulaires) avec positionnement précis
- **Calcul automatique du prix** basé sur les dimensions, matériau et perçages
- **Sauvegarde des configurations** pour accès ultérieur
- **Export DXF** pour fabrication (en développement)

### Pour les Administrateurs
- **Dashboard de gestion** accessible via `/admin/facades`
- **Gestion des matériaux** :
  - Créer/modifier/supprimer des matériaux
  - Définir couleurs (hex) et textures
  - Ajuster les modificateurs de prix
  - Activer/désactiver des matériaux
- **Gestion des types de perçages** :
  - Créer/modifier/supprimer des types
  - Personnaliser icônes SVG
  - Définir prix et descriptions
  - Activer/désactiver des types

## 🗂️ Structure des Fichiers

### Backend
```
back/
├── backend/
│   ├── api/
│   │   ├── facades.php                 # API CRUD pour les façades
│   │   ├── facade-materials.php        # API pour les matériaux
│   │   └── facade-drilling-types.php   # API pour les types de perçages
│   └── migrations/
│       └── 010_create_facades.sql      # Migration base de données
```

### Frontend
```
front/src/
├── components/
│   └── facades/
│       ├── FacadeViewer.tsx           # Composant 3D Three.js
│       └── FacadeControls.tsx         # Panneau de contrôle
├── pages/
│   ├── facades.tsx                    # Page principale utilisateur
│   └── admin/
│       └── facades.tsx                # Dashboard admin
└── types/
    └── facade.ts                      # Types TypeScript
```

## 🚀 Installation

### 1. Base de données

Exécuter la migration SQL :

```bash
# Depuis le dossier back/
cd back

# Windows PowerShell
Get-Content backend/migrations/010_create_facades.sql | sqlite3 database/archimeuble.db

# Linux/Mac
sqlite3 database/archimeuble.db < backend/migrations/010_create_facades.sql
```

Ou utilisez le script PHP :
```bash
php apply_migration.php backend/migrations/010_create_facades.sql
```

### 2. Vérification

Vérifiez que les tables ont été créées :
```sql
sqlite3 database/archimeuble.db "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'facade%';"
```

Vous devriez voir :
- `facades`
- `facade_materials`
- `facade_drilling_types`
- `saved_facades`

## 📡 API Endpoints

### Façades
- `GET /backend/api/facades.php` - Liste toutes les façades
- `GET /backend/api/facades.php/{id}` - Récupère une façade
- `POST /backend/api/facades.php` - Crée une façade
- `PUT /backend/api/facades.php/{id}` - Met à jour une façade
- `DELETE /backend/api/facades.php/{id}` - Supprime une façade

### Matériaux
- `GET /backend/api/facade-materials.php?active=1` - Liste les matériaux actifs
- `POST /backend/api/facade-materials.php` - Crée un matériau
- `PUT /backend/api/facade-materials.php/{id}` - Met à jour un matériau
- `DELETE /backend/api/facade-materials.php/{id}` - Supprime un matériau

### Types de Perçages
- `GET /backend/api/facade-drilling-types.php?active=1` - Liste les types actifs
- `POST /backend/api/facade-drilling-types.php` - Crée un type
- `PUT /backend/api/facade-drilling-types.php/{id}` - Met à jour un type
- `DELETE /backend/api/facade-drilling-types.php/{id}` - Supprime un type

## 🎨 Utilisation

### Page Utilisateur
1. Accédez à `http://localhost:3000/facades`
2. Configurez les dimensions dans l'onglet "Dimensions"
3. Sélectionnez un matériau dans l'onglet "Matériau"
4. Ajoutez des perçages dans l'onglet "Perçages"
5. Visualisez en temps réel dans le viewer 3D
6. Sauvegardez votre configuration

### Dashboard Admin
1. Accédez à `http://localhost:3000/admin/facades`
2. Onglet "Matériaux" : gérez les matériaux disponibles
3. Onglet "Types de Perçages" : gérez les types de perçages
4. Ajoutez, modifiez ou supprimez des éléments

## 🎯 Modèle de Données

### Table `facades`
```sql
- id: INTEGER PRIMARY KEY
- name: VARCHAR(255)
- description: TEXT
- width, height, depth: DECIMAL(10,2)
- base_price: DECIMAL(10,2)
- is_active: BOOLEAN
- image_url: VARCHAR(500)
- created_at, updated_at: DATETIME
```

### Table `facade_materials`
```sql
- id: INTEGER PRIMARY KEY
- name: VARCHAR(255)
- color_hex: VARCHAR(7)
- texture_url: VARCHAR(500)
- price_modifier: DECIMAL(10,2)
- is_active: BOOLEAN
- created_at: DATETIME
```

### Table `facade_drilling_types`
```sql
- id: INTEGER PRIMARY KEY
- name: VARCHAR(255)
- description: TEXT
- icon_svg: TEXT
- price: DECIMAL(10,2)
- is_active: BOOLEAN
- created_at: DATETIME
```

### Table `saved_facades`
```sql
- id: INTEGER PRIMARY KEY
- customer_id: INTEGER FK
- facade_id: INTEGER FK
- configuration_data: TEXT (JSON)
- preview_image: TEXT (base64)
- total_price: DECIMAL(10,2)
- created_at, updated_at: DATETIME
```

## 🔧 Configuration

### Variables d'environnement
Ajoutez dans `.env.local` :
```env
NEXT_PUBLIC_API_URL=http://localhost:8000
```

## 📝 Format de Configuration JSON

Exemple de `configuration_data` sauvegardé :
```json
{
  "width": 600,
  "height": 800,
  "depth": 19,
  "material": {
    "id": 1,
    "name": "Chêne Naturel",
    "color_hex": "#D8C7A1",
    "price_modifier": 0
  },
  "drillings": [
    {
      "id": "drilling-1641234567890",
      "type": "circular",
      "x": 50,
      "y": 30,
      "diameter": 30,
      "price": 5
    }
  ]
}
```

## 🎨 Personnalisation

### Ajouter un nouveau matériau par défaut
Modifiez la migration SQL `010_create_facades.sql` :
```sql
INSERT INTO facade_materials (name, color_hex, price_modifier) VALUES
    ('Votre Matériau', '#HEXCODE', 0);
```

### Personnaliser le calcul de prix
Dans [facades.tsx](front/src/pages/facades.tsx), modifiez :
```typescript
const basePrice = config.width * config.height * 0.0001; // Ajustez le facteur
```

## 🚧 Développements Futurs

- [ ] Export DXF fonctionnel
- [ ] Import de fichiers DXF existants
- [ ] Bibliothèque de templates de perçages prédéfinis
- [ ] Vue éclatée des façades
- [ ] Système de devis automatique
- [ ] Intégration avec le système de commandes
- [ ] Prévisualisation AR (Réalité Augmentée)
- [ ] Optimisation du découpage pour minimiser les chutes

## 🐛 Dépannage

### Les matériaux ne s'affichent pas
Vérifiez que la migration a bien été exécutée :
```bash
sqlite3 database/archimeuble.db "SELECT COUNT(*) FROM facade_materials;"
```

### Erreur CORS
Assurez-vous que le backend est lancé et accessible :
```bash
cd back
php -S localhost:8000
```

### Le viewer 3D ne charge pas
Vérifiez la console du navigateur. Assurez-vous que Three.js est installé :
```bash
cd front
npm install three @react-three/fiber @react-three/drei
```

## 📧 Support

Pour toute question ou suggestion concernant le module façades, contactez l'équipe de développement.

## 📄 Licence

Propriété de ArchiMeuble - Tous droits réservés
