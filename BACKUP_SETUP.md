# Système de Backup - Configuration

## Pour les développeurs

### 1. Créer le fichier de configuration local

Créer un fichier `.backup-config.json` à la racine du projet :

```json
{
  "apiUrl": "https://api-dev.archimeuble.com",
  "apiKey": "DEMANDER_LA_CLE_A_LADMIN"
}
```

> ⚠️ **Ne jamais committer ce fichier** (il est dans .gitignore)

### 2. Télécharger les backups

```bash
node download-backup.js
```

Les backups seront téléchargés dans `./local-backups/`

---

## Variables d'environnement Railway

| Variable | Description |
|----------|-------------|
| `BACKUP_API_KEY` | Clé secrète pour l'API de backup |
| `DB_PATH` | Chemin de la base de données (`/data/archimeuble_dev.db`) |

---

## Fonctionnement

- **Backups automatiques** : Tous les jours à 3h00 du matin
- **Rétention** : 30 derniers backups conservés
- **Stockage** : `/data/backups/` sur Railway

## API Endpoints (confidentiels)

| Action | Méthode | Endpoint |
|--------|---------|----------|
| Lister les backups | GET | `/backend/api/system/db-maintenance?key=XXX` |
| Télécharger un backup | GET | `/backend/api/system/db-maintenance/download/FILENAME?key=XXX` |
| Restaurer un backup | POST | `/backend/api/system/db-maintenance?key=XXX` |

---

## Contact

Pour obtenir la clé API, contacter l'administrateur du projet.
