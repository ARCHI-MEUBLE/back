# Migration des échantillons - Instructions

## Contexte
Cette migration ajoute le support complet pour les échantillons dans le panier et les commandes.

## Tables créées
- `cart_sample_items` - Échantillons dans le panier client
- `order_sample_items` - Échantillons dans les commandes finalisées

## Comment appliquer la migration sur Railway

### Option 1: Via Railway CLI (recommandé)
```bash
cd back
railway run bash backend/database/migrations/run_migration.sh
```

### Option 2: Manuelle via Railway shell
```bash
cd back
railway shell
# Une fois dans le shell Railway:
sqlite3 /data/archimeuble.db < backend/database/migrations/create_sample_orders.sql
```

### Option 3: Directement avec Railway run
```bash
cd back
railway run sqlite3 /data/archimeuble.db < backend/database/migrations/create_sample_orders.sql
```

## Vérification
Après migration, vérifier que les tables existent:
```bash
railway run sqlite3 /data/archimeuble.db "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%sample%';"
```

Résultat attendu:
- cart_sample_items
- order_sample_items
- sample_types (déjà existante)
- sample_colors (déjà existante)

## Rollback
Si besoin de supprimer les tables:
```sql
DROP TABLE IF EXISTS order_sample_items;
DROP TABLE IF EXISTS cart_sample_items;
```
