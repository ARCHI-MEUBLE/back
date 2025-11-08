#!/bin/bash
###############################################################################
# Script d'installation des dépendances PHP pour ArchiMeuble Backend
# Ce script utilise Composer pour installer automatiquement les dépendances
###############################################################################

echo "==========================================="
echo "Installation des dépendances PHP..."
echo "==========================================="

cd /app

# Installer les dépendances via Compose
if [ -f "composer.json" ]; then
    echo "→ Installation via Composer..."
    composer install --no-dev --optimize-autoloader --no-interaction

    if [ $? -eq 0 ]; then
        echo "✓ Dépendances Composer installées avec succès"
    else
        echo "⚠ Erreur lors de l'installation avec Composer"
        exit 1
    fi
else
    echo "⚠ Fichier composer.json introuvable"
    exit 1
fi

# Télécharger FPDF manuellement (non disponible via Composer)
VENDOR_DIR="/app/vendor"
mkdir -p "$VENDOR_DIR"

if [ -f "$VENDOR_DIR/fpdf/fpdf.php" ]; then
    echo "✓ FPDF déjà installé"
else
    echo "→ Téléchargement de FPDF..."
    mkdir -p "$VENDOR_DIR/fpdf"
    cd "$VENDOR_DIR/fpdf"

    if curl -sL "http://www.fpdf.org/en/download/fpdf186.tgz" | tar xz --strip-components=1 2>/dev/null; then
        echo "✓ FPDF installé avec succès"
    else
        echo "⚠ Échec du téléchargement de FPDF (optionnel)"
    fi
fi

# Créer un alias FPDF.php à la racine de vendor pour compatibilité
if [ -f "$VENDOR_DIR/fpdf/fpdf.php" ] && [ ! -f "$VENDOR_DIR/FPDF.php" ]; then
    ln -s fpdf/fpdf.php "$VENDOR_DIR/FPDF.php" 2>/dev/null || cp "$VENDOR_DIR/fpdf/fpdf.php" "$VENDOR_DIR/FPDF.php"
fi

echo "==========================================="
echo "✓ Toutes les dépendances sont installées!"
echo "==========================================="
