# Configuration des Rappels Calendly Automatiques

Ce guide explique comment configurer les rappels automatiques (24h et 1h avant les rendez-vous) pour ArchiMeuble.

## 🎯 Comment ça fonctionne

Le système envoie automatiquement des emails de rappel :
- **24h avant** le rendez-vous (fenêtre : 23h-25h avant)
- **1h avant** le rendez-vous (fenêtre : 50min-70min avant)

## 🔐 Sécurité

L'endpoint est protégé par un token secret (`CRON_SECRET`) pour éviter les appels non autorisés.

---

## 📋 Configuration sur **Railway** (Recommandé)

### Option 1 : Railway Cron Jobs (si disponible dans votre plan)

1. Allez sur votre projet Railway
2. Cliquez sur **"New Service"** → **"Cron Job"**
3. Configurez :
   - **Schedule** : `*/15 * * * *` (toutes les 15 minutes)
   - **Command** : `curl "https://votre-backend.up.railway.app/backend/api/calendly/trigger-reminders.php?token=MjNmYTgwMGUtZjUxMC00MWUyLWJlZjktOTM5NjAz"`
4. Sauvegardez

### Option 2 : Variables d'environnement Railway

Ajoutez ces variables dans Railway :
```
CRON_SECRET=MjNmYTgwMGUtZjUxMC00MWUyLWJlZjktOTM5NjAz
CALENDLY_API_TOKEN=votre-token-calendly
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=votre-email@gmail.com
SMTP_PASSWORD=votre-app-password
SMTP_FROM_EMAIL=votre-email@gmail.com
SMTP_FROM_NAME=ArchiMeuble
ADMIN_EMAIL=pro.archimeuble@gmail.com
```

---

## 📋 Configuration sur **cron-job.org** (Gratuit et universel)

### Étape 1 : Créer un compte sur cron-job.org

1. Allez sur https://cron-job.org/
2. Créez un compte gratuit
3. Vérifiez votre email

### Étape 2 : Créer le Cron Job

1. Connectez-vous à cron-job.org
2. Cliquez sur **"Create cronjob"**
3. Configurez :
   - **Title** : `ArchiMeuble Calendly Reminders`
   - **Address (URL)** :
     ```
     https://votre-backend.up.railway.app/backend/api/calendly/trigger-reminders.php?token=MjNmYTgwMGUtZjUxMC00MWUyLWJlZjktOTM5NjAz
     ```
     ⚠️ **Remplacez** :
     - `votre-backend.up.railway.app` par votre URL Railway réelle
     - Le token doit correspondre à votre `CRON_SECRET` dans Railway

   - **Schedule** :
     - Sélectionnez **"Every 15 minutes"**
     - Ou configurez manuellement : `*/15 * * * *`

   - **Enable** : ✅ Coché

   - **Notification** :
     - ✅ Enable failure notifications
     - Ajoutez votre email pour recevoir des alertes en cas d'erreur

4. Cliquez sur **"Create cronjob"**

### Étape 3 : Tester

1. Sur cron-job.org, cliquez sur votre cron job
2. Cliquez sur **"Run now"** pour tester immédiatement
3. Vérifiez que le status est **200 OK**
4. Vous devriez voir :
   ```json
   {
     "success": true,
     "message": "Reminder check completed",
     "timestamp": "2025-11-02 12:00:00",
     "output": "..."
   }
   ```

---

## 🧪 Test manuel

Pour tester l'endpoint localement :

```bash
# Avec le bon token (doit fonctionner)
curl "http://localhost:8000/backend/api/calendly/trigger-reminders.php?token=MjNmYTgwMGUtZjUxMC00MWUyLWJlZjktOTM5NjAz"

# Sans token (doit renvoyer 403 Unauthorized)
curl "http://localhost:8000/backend/api/calendly/trigger-reminders.php"

# Avec un mauvais token (doit renvoyer 403 Unauthorized)
curl "http://localhost:8000/backend/api/calendly/trigger-reminders.php?token=mauvais-token"
```

---

## 📊 Monitoring

### Vérifier les logs Railway

```bash
railway logs --service backend
```

### Vérifier les logs dans l'application

Les logs des rappels sont enregistrés dans :
- Railway : `/app/logs/calendly_reminders.log`
- Local : `back/logs/calendly_reminders.log`

### Vérifier la base de données

```sql
SELECT * FROM calendly_appointments
WHERE status = 'scheduled'
ORDER BY start_time DESC;
```

---

## 🔧 Dépannage

### L'endpoint renvoie "CRON_SECRET not configured"

➡️ Vérifiez que la variable `CRON_SECRET` est bien définie dans Railway :
```bash
railway variables
```

### L'endpoint renvoie "Unauthorized"

➡️ Le token dans l'URL ne correspond pas au `CRON_SECRET` configuré. Vérifiez :
1. Le token dans l'URL cron-job.org
2. La variable `CRON_SECRET` dans Railway

### Aucun email n'est envoyé

➡️ Vérifiez que :
1. Les variables SMTP sont bien configurées dans Railway
2. Le compte Gmail a autorisé les "App Passwords"
3. Il y a des rendez-vous dans la base de données nécessitant un rappel

### Comment vérifier si des rappels doivent être envoyés ?

Connectez-vous à la base de données et exécutez :

```sql
-- Rappels 24h à envoyer
SELECT * FROM calendly_appointments
WHERE status = 'scheduled'
  AND reminder_24h_sent = 0
  AND datetime(start_time) BETWEEN datetime('now', '+23 hours') AND datetime('now', '+25 hours');

-- Rappels 1h à envoyer
SELECT * FROM calendly_appointments
WHERE status = 'scheduled'
  AND reminder_1h_sent = 0
  AND datetime(start_time) BETWEEN datetime('now', '+50 minutes') AND datetime('now', '+70 minutes');
```

---

## 🔑 Changer le token secret

Si vous voulez changer le token pour plus de sécurité :

1. Générez un nouveau token :
   ```bash
   openssl rand -base64 32
   ```

2. Mettez à jour dans Railway :
   ```bash
   railway variables --set CRON_SECRET=nouveau-token
   ```

3. Mettez à jour l'URL sur cron-job.org avec le nouveau token

---

## 📝 Résumé des URLs

- **Local** : `http://localhost:8000/backend/api/calendly/trigger-reminders.php?token=VOTRE_TOKEN`
- **Production Railway** : `https://votre-backend.up.railway.app/backend/api/calendly/trigger-reminders.php?token=VOTRE_TOKEN`

---

## ✅ Checklist de déploiement

- [ ] Variables d'environnement configurées dans Railway
- [ ] `CRON_SECRET` défini et sécurisé
- [ ] Compte cron-job.org créé
- [ ] Cron job configuré (toutes les 15 minutes)
- [ ] Test manuel effectué avec succès
- [ ] Notifications d'erreur activées sur cron-job.org
- [ ] Premier rappel testé en conditions réelles

---

**💡 Besoin d'aide ?** Consultez les logs ou testez manuellement l'endpoint pour identifier le problème.
