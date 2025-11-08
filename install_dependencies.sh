#!/bin/bash
###############################################################################
# Script d'installation des dépendances PHP pour ArchiMeuble Backend
# Ce script télécharge et installe automatiquement le SDK Stripe PHP
###############################################################################

set -e  # Arrêter le script en cas d'erreu

VENDOR_DIR="/app/vendor"
STRIPE_DIR="$VENDOR_DIR/stripe"

echo "==========================================="
echo "Installation des dépendances PHP..."
echo "==========================================="

# Créer le dossier vendor s'il n'existe pas
mkdir -p "$VENDOR_DIR"

# Vérifier si Stripe SDK est déjà installé
if [ -f "$STRIPE_DIR/init.php" ]; then
    echo "✓ Stripe SDK déjà installé"
else
    echo "→ Téléchargement du Stripe SDK PHP..."

    # Télécharger la dernière version du SDK Stripe
    STRIPE_VERSION="15.12.0"
    STRIPE_URL="https://github.com/stripe/stripe-php/archive/refs/tags/v${STRIPE_VERSION}.tar.gz"

    # Télécharger et extraire
    cd /tmp
    curl -L "$STRIPE_URL" -o stripe.tar.gz
    tar -xzf stripe.tar.gz

    # Déplacer dans vendor/
    mv "stripe-php-${STRIPE_VERSION}" "$STRIPE_DIR"

    # Nettoye
    rm stripe.tar.gz

    echo "✓ Stripe SDK v${STRIPE_VERSION} installé avec succès"
fi

# Vérifier si FPDF est installé
if [ -f "$VENDOR_DIR/FPDF.php" ]; then
    echo "✓ FPDF déjà installé"
else
    echo "→ Téléchargement de FPDF..."

    # Télécharger FPDF
    cd "$VENDOR_DIR"
    curl -L "http://www.fpdf.org/en/download/fpdf186.tgz" -o fpdf.tgz
    tar -xzf fpdf.tgz
    mv fpdf186/fpdf.php FPDF.php
    rm -rf fpdf186 fpdf.tgz

    echo "✓ FPDF installé avec succès"
fi

echo "==========================================="
echo "✓ Toutes les dépendances sont installées!"
echo "==========================================="
