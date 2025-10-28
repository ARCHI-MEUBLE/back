# ArchiMeuble - Documentation API Système de Commandes

## 📋 Vue d'ensemble

Ce système permet aux clients de :
1. S'inscrire et se connecter
2. Sauvegarder leurs configurations de meubles
3. Ajouter des configurations au panier
4. Passer des commandes
5. Suivre leurs commandes

Les admins peuvent :
1. Voir toutes les commandes
2. Mettre à jour le statut des commandes
3. Recevoir des notifications
4. Gérer les commandes clients

---

## 🔐 Authentification Client

### 1. Inscription
```bash
POST http://localhost:8000/api/customers/register
Content-Type: application/json

{
  "email": "client@example.com",
  "password": "motdepasse123",
  "first_name": "Jean",
  "last_name": "Dupont",
  "phone": "0612345678",
  "address": "123 Rue Example",
  "city": "Paris",
  "postal_code": "75001"
}
```

### 2. Connexion
```bash
POST http://localhost:8000/api/customers/login
Content-Type: application/json

{
  "email": "client@example.com",
  "password": "motdepasse123"
}
```

### 3. Vérifier la session
```bash
GET http://localhost:8000/api/customers/session
```

### 4. Déconnexion
```bash
DELETE http://localhost:8000/api/customers/session
```

---

## 💾 Configurations Sauvegardées

### 1. Sauvegarder une configuration
```bash
POST http://localhost:8000/api/configurations/save
Content-Type: application/json

{
  "name": "Mon meuble salon",
  "prompt": "M1(1500,400,800)EbFH2(T,)",
  "price": 899.00,
  "model_id": 1,
  "glb_url": "http://localhost:8000/models/generated_12345.glb",
  "config_data": {
    "modules": 3,
    "height": 800,
    "depth": 400,
    "color": "#8B4513",
    "finish": "mat"
  }
}
```

### 2. Lister mes configurations
```bash
GET http://localhost:8000/api/configurations/list
```

### 3. Supprimer une configuration
```bash
DELETE http://localhost:8000/api/configurations/list?id=1
```

---

## 🛒 Panier

### 1. Voir le panier
```bash
GET http://localhost:8000/api/cart
```

### 2. Ajouter au panier
```bash
POST http://localhost:8000/api/cart
Content-Type: application/json

{
  "configuration_id": 1,
  "quantity": 1
}
```

### 3. Modifier la quantité
```bash
PUT http://localhost:8000/api/cart
Content-Type: application/json

{
  "configuration_id": 1,
  "quantity": 2
}
```

### 4. Retirer du panier
```bash
DELETE http://localhost:8000/api/cart?configuration_id=1
```

---

## 📦 Commandes

### 1. Créer une commande (depuis le panier)
```bash
POST http://localhost:8000/api/orders/create
Content-Type: application/json

{
  "shipping_address": "123 Rue Example, 75001 Paris",
  "billing_address": "123 Rue Example, 75001 Paris",
  "payment_method": "stripe",
  "notes": "Livraison entre 14h et 18h"
}
```

### 2. Lister mes commandes
```bash
GET http://localhost:8000/api/orders/list
```

### 3. Détail d'une commande
```bash
GET http://localhost:8000/api/orders/list?id=1
```

---

## 👨‍💼 API Admin

### 1. Lister toutes les commandes
```bash
GET http://localhost:8000/api/admin/orders
# Avec filtre de statut:
GET http://localhost:8000/api/admin/orders?status=pending
```

### 2. Détail d'une commande
```bash
GET http://localhost:8000/api/admin/orders?id=1
```

### 3. Mettre à jour le statut
```bash
PUT http://localhost:8000/api/admin/orders
Content-Type: application/json

{
  "order_id": 1,
  "status": "confirmed",
  "admin_notes": "Commande validée, en attente de production"
}
```

**Statuts disponibles:**
- `pending` : En attente
- `confirmed` : Confirmée
- `in_production` : En production
- `shipped` : Expédiée
- `delivered` : Livrée
- `cancelled` : Annulée

### 4. Lister les notifications
```bash
GET http://localhost:8000/api/admin/notifications
# Seulement non lues:
GET http://localhost:8000/api/admin/notifications?unread=true
```

### 5. Marquer une notification comme lue
```bash
PUT http://localhost:8000/api/admin/notifications
Content-Type: application/json

{
  "notification_id": 1
}
```

### 6. Marquer toutes comme lues
```bash
PUT http://localhost:8000/api/admin/notifications
Content-Type: application/json

{
  "mark_all_read": true
}
```

---

## 🧪 Tests PowerShell

### Test complet du workflow client
```powershell
# 1. Inscription
$body = @{
    email = "test@example.com"
    password = "password123"
    first_name = "Test"
    last_name = "User"
} | ConvertTo-Json

$register = Invoke-WebRequest -Uri "http://localhost:8000/api/customers/register" -Method POST -ContentType "application/json" -Body $body -SessionVariable session
Write-Host "Inscription: $($register.StatusCode)"

# 2. Sauvegarder une configuration
$body = @{
    name = "Mon premier meuble"
    prompt = "M1(1000,400,1000)EbF"
    price = 599.99
    config_data = @{ modules = 2 }
} | ConvertTo-Json

$config = Invoke-WebRequest -Uri "http://localhost:8000/api/configurations/save" -Method POST -ContentType "application/json" -Body $body -WebSession $session
Write-Host "Configuration sauvegardée: $($config.StatusCode)"

# 3. Ajouter au panier
$configId = ($config.Content | ConvertFrom-Json).configuration.id
$body = @{
    configuration_id = $configId
    quantity = 1
} | ConvertTo-Json

$cart = Invoke-WebRequest -Uri "http://localhost:8000/api/cart" -Method POST -ContentType "application/json" -Body $body -WebSession $session
Write-Host "Ajouté au panier: $($cart.StatusCode)"

# 4. Créer une commande
$body = @{
    shipping_address = "123 Rue Test, 75001 Paris"
    payment_method = "stripe"
} | ConvertTo-Json

$order = Invoke-WebRequest -Uri "http://localhost:8000/api/orders/create" -Method POST -ContentType "application/json" -Body $body -WebSession $session
Write-Host "Commande créée: $($order.StatusCode)"
Write-Host ($order.Content | ConvertFrom-Json | ConvertTo-Json)
```

---

## 📊 Structure de la base de données

- `customers` : Comptes clients
- `saved_configurations` : Configurations sauvegardées
- `cart_items` : Items du panier
- `orders` : Commandes
- `order_items` : Détails des commandes
- `admin_notifications` : Notifications admin

---

## ✅ Workflow complet

1. **Client s'inscrit/connecte**
2. **Client configure un meuble** dans le configurateur
3. **Client sauvegarde** la configuration
4. **Client consulte** ses configurations sauvegardées
5. **Client ajoute** des configurations au panier
6. **Client passe** une commande
7. **Admin reçoit** une notification
8. **Admin consulte** la commande avec tous les détails (prompt, config, GLB)
9. **Admin met à jour** le statut (confirmé → en production → expédié)
10. **Client suit** sa commande

---

## 🎯 Prochaines étapes Frontend

Créer les pages Next.js :
- `/auth/register` - Inscription
- `/auth/login` - Connexion
- `/my-configurations` - Mes configurations
- `/cart` - Mon panier
- `/checkout` - Passer commande
- `/my-orders` - Mes commandes
- `/admin/orders` - Gestion des commandes (admin)

