# 🔐 Système de Backup Sécurisé - ArchiMeuble

**⚠️ DOCUMENT CONFIDENTIEL - NE PAS PARTAGER**

Ce document décrit le système de backup caché et sécurisé. Aucune interface utilisateur n'expose ces fonctionnalités.

---

## 📋 Vue d'ensemble

### Fonctionnalités

- ✅ **Backup automatique quotidien** à 3h du matin
- ✅ **Conservation des 30 derniers backups**
- ✅ **Endpoint API caché** avec authentification par clé secrète
- ✅ **Rate limiting** : 10 requêtes/heure par IP
- ✅ **Logs d'accès** complets (succès ET échecs)
- ✅ **Script de téléchargement** pour ton PC
- ✅ **Aucune référence** dans l'interface publique

### Sécurité

- 🔒 URL non devinable : `/api/system/db-maintenance`
- 🔒 Authentification par clé API séparée des comptes admin
- 🔒 Aucun lien/bouton dans l'interface web
- 🔒 Rate limiting strict
- 🔒 Logs de toutes les tentatives d'accès

---

## ⚙️ Configuration initiale

### 1. Générer une clé API secrète

Sur ton PC (terminal) :

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

Tu obtiens quelque chose comme :
```
7f3e8d9c2a1b4f6e8d7c3a9b2e1f4d6c8a7b3e9d2f1c4b6a8e7d3c9f2a1b4e6d
```

### 2. Configurer la clé sur Railway

1. Va sur ton projet Railway
2. Variables → Add variable
3. Ajoute :
   ```
   BACKUP_API_KEY=7f3e8d9c2a1b4f6e8d7c3a9b2e1f4d6c8a7b3e9d2f1c4b6a8e7d3c9f2a1b4e6d
   ```
4. Redémarre le service

### 3. Installer le cron de backup automatique

Via Railway CLI :

```bash
railway login
railway link  # Sélectionne ton projet
railway shell

# Dans le shell Railway :
bash /app/setup-backup-cron.sh
```

Le backup automatique est maintenant configuré !

---

## 🌐 Utilisation de l'API

### URL de base

```
https://back-production-XXXX.up.railway.app/backend/api/system/db-maintenance
```

**⚠️ Remplace** `back-production-XXXX.up.railway.app` **par ton URL Railway réelle**

### 1. Lister les backups

```bash
curl "https://back-production-XXXX.up.railway.app/backend/api/system/db-maintenance?key=TA_CLE_API"
```

**Réponse :**
```json
{
  "success": true,
  "count": 15,
  "backups": [
    {
      "filename": "database-backup-2025-11-12_03-00-00.db",
      "size": "2.45 MB",
      "size_bytes": 2569216,
      "date": "2025-11-12 03:00:00",
      "timestamp": 1699761600
    },
    ...
  ]
}
```

### 2. Télécharger un backup

```bash
curl -O "https://back-production-XXXX.up.railway.app/backend/api/system/db-maintenance/download/database-backup-2025-11-12_03-00-00.db?key=TA_CLE_API"
```

Le fichier se télécharge dans le dossier courant.

### 3. Restaurer un backup

```bash
curl -X POST "https://back-production-XXXX.up.railway.app/backend/api/system/db-maintenance?key=TA_CLE_API" \
  -H "Content-Type: application/json" \
  -d '{"filename":"database-backup-2025-11-12_03-00-00.db"}'
```

**⚠️ ATTENTION :** La restauration écrase la base actuelle (un backup d'urgence est créé automatiquement)

---

## 💻 Téléchargement automatique depuis ton PC

### Installation

1. Créer le fichier de configuration `.backup-config.json` :

```json
{
  "apiUrl": "https://back-production-XXXX.up.railway.app",
  "apiKey": "TA_CLE_API_ICI"
}
```

2. Ajouter le script au `package.json` :

```json
{
  "scripts": {
    "backup:download": "node download-backup.js"
  }
}
```

### Utilisation

```bash
# Télécharger le dernier backup
npm run backup:download
```

**Le backup sera sauvegardé dans** `./local-backups/`

---

## 🔍 Depuis Postman

### Configuration

1. **Méthode :** GET
2. **URL :** `https://back-production-XXXX.up.railway.app/backend/api/system/db-maintenance`
3. **Params :**
   - key: `TA_CLE_API`

### Collections utiles

**Lister backups :**
- GET `/backend/api/system/db-maintenance?key={{apiKey}}`

**Télécharger backup :**
- GET `/backend/api/system/db-maintenance/download/:filename?key={{apiKey}}`

**Restaurer backup :**
- POST `/backend/api/system/db-maintenance?key={{apiKey}}`
- Body (JSON): `{"filename": "database-backup-2025-11-12_03-00-00.db"}`

---

## 🚨 Procédure d'urgence

### Si la base est corrompue

1. **Lister les backups disponibles :**
   ```bash
   curl "https://ton-site.railway.app/backend/api/system/db-maintenance?key=CLE_API"
   ```

2. **Choisir le backup le plus récent :**
   ```json
   {
     "filename": "database-backup-2025-11-12_03-00-00.db"
   }
   ```

3. **Restaurer :**
   ```bash
   curl -X POST "https://ton-site.railway.app/backend/api/system/db-maintenance?key=CLE_API" \
     -H "Content-Type: application/json" \
     -d '{"filename":"database-backup-2025-11-12_03-00-00.db"}'
   ```

4. **Redémarrer le service Railway :**
   ```bash
   railway restart
   ```

### Si Railway CLI n'est pas accessible

1. **Via l'interface web Railway :**
   - Va dans le dashboard Railway
   - Settings → Restart

2. **Accès direct via SSH (si configuré) :**
   ```bash
   railway shell
   ls /data/backups/
   cp /data/backups/database-backup-XXXX.db /data/archimeuble_test.db
   ```

---

## 📊 Monitoring

### Voir les logs d'accès

Via Railway shell :

```bash
railway shell
cat /data/backup-access.log
```

**Exemple de log :**
```
[2025-11-12 10:30:45] SUCCESS | IP: 78.45.123.89 | Action: LIST_BACKUPS |
[2025-11-12 10:31:12] SUCCESS | IP: 78.45.123.89 | Action: DOWNLOAD_BACKUP | File: database-backup-2025-11-11_03-00-00.db
[2025-11-12 15:22:01] FAILED  | IP: 192.168.1.1 | Action: AUTH | Invalid API key
```

### Voir les logs du cron de backup

```bash
railway shell
cat /data/backup-cron.log
```

---

## 🛡️ Sécurité avancée (optionnel)

### Whitelist d'IPs

Modifier `/backend/api/system/db-maintenance.php` :

```php
$ALLOWED_IPS = ['78.45.123.89', '192.168.1.100']; // Tes IPs
$ip = $_SERVER['REMOTE_ADDR'];

if (!in_array($ip, $ALLOWED_IPS)) {
    logAccess('IP_BLOCKED', false, $ip);
    http_response_code(403);
    exit;
}
```

### Notifications par email

Ajouter après chaque log de succès :

```php
if ($action === 'DOWNLOAD_BACKUP' || $action === 'RESTORE_BACKUP') {
    mail('ton-email@gmail.com',
         'Backup access alert',
         "Action: $action\nIP: $ip\nDate: " . date('Y-m-d H:i:s'));
}
```

---

## 📝 Notes importantes

### Ce qui N'existe PAS (et ne doit JAMAIS exister)

- ❌ Bouton "Backups" dans le dashboard admin
- ❌ Lien vers cet endpoint dans la sidebar
- ❌ Mention de l'URL dans le code frontend
- ❌ Route publique vers cet endpoint
- ❌ Documentation publique

### Ce qui existe (et doit rester secret)

- ✅ Ce document (à garder privé)
- ✅ L'endpoint backend `/backend/api/system/db-maintenance`
- ✅ Le script `download-backup.js` (à garder sur ton PC)
- ✅ La clé API (variable d'environnement Railway)

---

## 🔧 Dépannage

### Erreur 503 "Service temporarily unavailable"

→ La variable `BACKUP_API_KEY` n'est pas configurée sur Railway

**Solution :**
```bash
railway variables set BACKUP_API_KEY=ta-cle-ici
railway restart
```

### Erreur 403 "Forbidden"

→ Clé API incorrecte

**Solution :** Vérifie que tu utilises la bonne clé

### Erreur 429 "Too many requests"

→ Rate limit atteint (10 req/h)

**Solution :** Attends 1 heure ou modifie `$MAX_REQUESTS_PER_HOUR` dans le code

### Aucun backup disponible

→ Le cron n'est pas installé

**Solution :**
```bash
railway shell
bash /app/setup-backup-cron.sh
```

---

## 📞 Support

Si tu as besoin d'aide, contacte le développeur (toi) en consultant ce document.

**Dernière mise à jour :** 12 novembre 2025
