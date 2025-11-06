-- Migration: Ajouter stripe_payment_intent_id à la table orders
-- Date: 2025-11-06
-- Description: Permet de tracker les paiements Stripe

ALTER TABLE orders ADD COLUMN stripe_payment_intent_id TEXT;
