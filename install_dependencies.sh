#!/bin/bash
###############################################################################
# Script d'installation des dépendances PHP pour ArchiMeuble Backend
# Les dépendances sont maintenant commitées dans Git pour simplifie
###############################################################################

echo "==========================================="
echo "Vérification des dépendances PHP..."
echo "==========================================="

VENDOR_DIR="/app/vendor"
STRIPE_DIR="$VENDOR_DIR/stripe"

# Vérifier si les dépendances sont déjà présentes (commitées dans Git)
if [ -f "$STRIPE_DIR/init.php" ]; then
    echo "✓ Stripe SDK déjà présent (depuis Git)"
    echo "✓ Toutes les dépendances sont installées!"
    echo "==========================================="
    exit 0
fi

# Si vendor n'existe pas, essayer Composer (fallback)
if [ -f "/app/composer.json" ]; then
    echo "→ Installation via Composer..."
    cd /app
    composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null

    if [ -f "$STRIPE_DIR/init.php" ]; then
        echo "✓ Dépendances Composer installées avec succès"
        echo "==========================================="
        exit 0
    fi
fi

# Si rien n'a fonctionné
echo "⚠ ERREUR: Stripe SDK introuvable!"
echo "⚠ Le dossier vendor/ doit être présent dans le repo Git"
echo "==========================================="
exit 1
