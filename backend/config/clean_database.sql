-- Nettoyage de la base de données pour déploiement
-- Garde uniquement les 3 modèles par défaut et les échantillons

-- Supprimer les commandes de test
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders);
DELETE FROM orders;

-- Supprimer les configurations de test
DELETE FROM cart_items;
DELETE FROM configurations;

-- Supprimer les clients de test
DELETE FROM customers;

-- Supprimer les modèles créés pour test (garde uniquement les 3 premiers)
DELETE FROM models WHERE id > 3;

-- Supprimer les admins de test (garde uniquement admin@archimeuble.com)
DELETE FROM admins WHERE email <> 'admin@archimeuble.com';

-- Supprimer les rendez-vous Calendly de test
-- DELETE FROM calendly_appointments;

-- Supprimer les notifications
DELETE FROM admin_notifications;

-- Réinitialiser les séquences auto-increment
-- DELETE FROM sqlite_sequence WHERE name IN ('orders', 'order_items', 'configurations', 'customers', 'cart_items', 'notifications', 'admin_notifications');

VACUUM;
