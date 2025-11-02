-- Migration pour ajouter le lien de visioconférence
-- Cette migration s'exécute automatiquement si la colonne n'existe pas

ALTER TABLE calendly_appointments ADD COLUMN meeting_url TEXT;
