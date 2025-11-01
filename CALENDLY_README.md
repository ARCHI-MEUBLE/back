# Intégration Calendly - ArchiMeuble Backend

## Vue d'ensemble

Cette intégration backend permet de recevoir et traiter les webhooks Calendly lorsqu'un client prend ou annule un rendez-vous sur le site ArchiMeuble.

## Fichiers créés

### 1. **`backend/api/calendly/webhook.php`**

Endpoint principal qui reçoit les webhooks Calendly et :
- Enregistre tous les événements dans un fichier log
- Traite les créations de rendez-vous (`invitee.created`)
- Traite les annulations de rendez-vous (`invitee.canceled`)
- Prépare des emails de notification pour le menuisier
- Extrait les informations importantes (nom, email, date, lien de configuration)

### 2. **`backend/api/calendly/.htaccess`**

Configuration Apache pour :
- Rediriger toutes les requêtes vers `webhook.php`
- Gérer les CORS pour les requêtes cross-origin
- Désactiver l'affichage des erreurs en production

### 3. **`backend/logs/`**

Dossier pour stocker les logs :
- `calendly.log` : Tous les événements Calendly reçus
- `php_errors.log` : Erreurs PHP éventuelles

## Installation

### 1. Structure des dossiers

La structure devrait ressembler à :

```
archimeuble-back/
├── backend/
│   ├── api/
│   │   └── calendly/
│   │       ├── webhook.php
│   │       └── .htaccess
│   └── logs/
│       └── calendly.log (créé automatiquement)
```

### 2. Permissions

Assurez-vous que le dossier `logs` est accessible en écriture :

```bash
# Linux/Mac
chmod 755 backend/logs

# Windows (PowerShell)
# Les permissions sont déjà configurées
```

### 3. Configuration Apache

Le fichier `.htaccess` est déjà configuré. Vérifiez que `mod_rewrite` est activé :

```bash
# Sur un serveur Linux
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## Configuration Calendly

### Étape 1 : Créer un webhook

1. Connectez-vous à **Calendly** : https://calendly.com
2. Allez dans **Account** > **Integrations** > **Webhooks**
3. Cliquez sur **Add Webhook**

### Étape 2 : Configurer l'URL du webhook

**Développement local** (pour tester) :
```
http://localhost:8000/api/calendly/webhook.php
```

**Production** (une fois déployé) :
```
https://votre-domaine.com/api/calendly/webhook.php
```

**Note** : Pour tester en local, utilisez un outil comme **ngrok** pour exposer votre localhost :

```bash
# Installer ngrok (https://ngrok.com/)
ngrok http 8000

# Utilisez l'URL fournie par ngrok
https://xxxx-xx-xx-xxx-xxx.ngrok.io/api/calendly/webhook.php
```

### Étape 3 : Sélectionner les événements

Cochez les événements suivants :
- ✅ **invitee.created** - Quand un rendez-vous est créé
- ✅ **invitee.canceled** - Quand un rendez-vous est annulé

### Étape 4 : Sauvegarder

Cliquez sur **Create Webhook**. Calendly va envoyer un événement de test.

## Test du webhook

### Test manuel avec cURL

```bash
# Test d'un événement invitee.created
curl -X POST http://localhost:8000/api/calendly/webhook.php \
  -H "Content-Type: application/json" \
  -d '{
    "event": "invitee.created",
    "payload": {
      "name": "Test Client",
      "email": "test@example.com",
      "event_type_name": "Consultation téléphonique",
      "scheduled_event": {
        "start_time": "2025-11-15T14:00:00Z",
        "end_time": "2025-11-15T14:30:00Z"
      },
      "timezone": "Europe/Paris",
      "questions_and_answers": [
        {
          "question": "Lien de configuration",
          "answer": "https://archimeuble.com/config/123"
        }
      ]
    }
  }'
```

### Vérifier les logs

Après le test, vérifiez le fichier de log :

```bash
# Windows (PowerShell)
Get-Content C:\Users\bensk\Desktop\archimeuble\back\backend\logs\calendly.log -Tail 20

# Linux/Mac
tail -20 backend/logs/calendly.log
```

Vous devriez voir :
```
[2025-10-31 23:51:00] Event: invitee.created | Data: {...}
[2025-10-31 23:51:00] Email notification prepared for: pro.archimeuble@gmail.com | Client: Test Client (test@example.com) | Event: Consultation téléphonique
```

## Activation des emails

Par défaut, l'envoi d'emails est **désactivé** (commenté) pour éviter les spams pendant les tests.

Pour activer l'envoi d'emails en production :

1. Ouvrez `backend/api/calendly/webhook.php`
2. Trouvez la ligne :
   ```php
   // mail($to, $subject, $message, $headers);
   ```
3. Décommentez-la :
   ```php
   mail($to, $subject, $message, $headers);
   ```

### Configuration du serveur mail

Assurez-vous que votre serveur PHP peut envoyer des emails :

**Option 1 : Utiliser la fonction mail() native**
```ini
# php.ini
sendmail_path = /usr/sbin/sendmail -t -i
```

**Option 2 : Utiliser un service SMTP (recommandé)**

Installez PHPMailer :
```bash
composer require phpmailer/phpmailer
```

Modifiez le code dans `webhook.php` pour utiliser PHPMailer au lieu de `mail()`.

## Format des données reçues

### Événement `invitee.created`

```json
{
  "event": "invitee.created",
  "payload": {
    "name": "Jean Dupont",
    "email": "jean.dupont@example.com",
    "event_type_name": "Consultation téléphonique - 30 min",
    "scheduled_event": {
      "start_time": "2025-11-15T14:00:00Z",
      "end_time": "2025-11-15T14:30:00Z"
    },
    "timezone": "Europe/Paris",
    "questions_and_answers": [
      {
        "question": "Lien de configuration ArchiMeuble",
        "answer": "https://archimeuble.com/configurator/save/abc123"
      },
      {
        "question": "Notes supplémentaires",
        "answer": "Je souhaite un meuble scandinave pour mon salon"
      }
    ]
  }
}
```

### Événement `invitee.canceled`

```json
{
  "event": "invitee.canceled",
  "payload": {
    "name": "Jean Dupont",
    "email": "jean.dupont@example.com",
    "event_type_name": "Consultation téléphonique - 30 min"
  }
}
```

## Email de notification

Lorsqu'un rendez-vous est créé, le menuisier reçoit un email HTML contenant :

- **Type de consultation** : Téléphonique ou Visio
- **Nom du client**
- **Email du client**
- **Date et heure** : Formatées en français (dd/mm/yyyy à HH:mm)
- **Fuseau horaire**
- **Lien de configuration** : Si fourni par le client
- **Notes supplémentaires** : Si renseignées

### Exemple d'email

```
Sujet : Nouveau RDV Calendly - ArchiMeuble : Consultation téléphonique

Bonjour,

Un nouveau rendez-vous a été pris sur Calendly :

Type de consultation : Consultation téléphonique - 30 min
Client : Jean Dupont
Email : jean.dupont@example.com
Date et heure : 15/11/2025 à 14:00 - 14:30
Fuseau horaire : Europe/Paris

Lien de configuration : [Voir la configuration]

Pensez à vous préparer pour cet entretien et à vérifier votre agenda.

Cordialement,
Système ArchiMeuble
```

## Sécurité

### Validation de la signature (recommandé en production)

Calendly envoie un header `X-Calendly-Webhook-Signature` pour vérifier l'authenticité des webhooks.

Pour activer la validation :

1. Récupérez votre **Webhook Signing Key** dans Calendly
2. Ajoutez cette logique dans `webhook.php` :

```php
// Récupérer la signature
$signature = $_SERVER['HTTP_X_CALENDLY_WEBHOOK_SIGNATURE'] ?? '';
$signingKey = 'VOTRE_SIGNING_KEY'; // À mettre dans une variable d'env

// Calculer la signature attendue
$expectedSignature = hash_hmac('sha256', $input, $signingKey);

// Vérifier la signature
if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit();
}
```

### Protection CORS

Le fichier `.htaccess` est déjà configuré pour accepter les requêtes de tous les domaines (`*`).

En production, vous pouvez restreindre à Calendly :

```apache
Header set Access-Control-Allow-Origin "https://calendly.com"
```

## Dépannage

### Le webhook ne reçoit rien

1. **Vérifiez l'URL** : Doit être accessible publiquement (pas `localhost` en prod)
2. **Vérifiez les logs** : Consultez `backend/logs/calendly.log`
3. **Testez manuellement** : Utilisez cURL pour envoyer un événement de test
4. **Vérifiez Apache** : Assurez-vous que mod_rewrite est activé

### Erreur 500

1. Vérifiez les permissions du dossier `logs`
2. Consultez `backend/logs/php_errors.log`
3. Activez temporairement les erreurs PHP :
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

### Les emails ne partent pas

1. Vérifiez que `mail()` fonctionne sur votre serveur :
   ```bash
   php -r "mail('test@example.com', 'Test', 'Test');"
   ```
2. Consultez les logs du serveur mail
3. Utilisez PHPMailer avec SMTP comme alternative

## Maintenance

### Rotation des logs

Les logs peuvent devenir volumineux. Ajoutez une rotation automatique :

```bash
# Créer un script de rotation (Linux)
# /etc/logrotate.d/archimeuble-calendly

/var/www/archimeuble/backend/logs/calendly.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```

### Surveillance

Surveillez les logs régulièrement pour détecter :
- Des tentatives d'attaque (requêtes suspectes)
- Des erreurs récurrentes
- Des patterns inhabituels

## Prochaines étapes

1. **Tester avec de vrais rendez-vous** Calendly
2. **Activer l'envoi d'emails** en production
3. **Configurer la validation de signature** pour plus de sécurité
4. **Intégrer avec une base de données** pour stocker les rendez-vous
5. **Créer un dashboard admin** pour visualiser les rendez-vous

## Support

- **Documentation Calendly Webhooks** : https://developer.calendly.com/api-docs/docs/webhooks
- **Documentation PHP mail()** : https://www.php.net/manual/fr/function.mail.php

---

**Auteur** : ArchiMeuble Team
**Date** : 31/10/2025
**Version** : 1.0.0
