#!/bin/bash

# =============================================================================
# Script de déploiement automatisé pour Railway - ArchiMeuble Backend
# =============================================================================
# Ce script déploie la branche server_test du backend sur Railway
#
# Prérequis:
#   - Railway CLI installé: npm install -g @railway/cli
#   - Authentification Railway: railway login
#   - Projet Railway déjà créé et lié
#
# Usage:
#   ./deploy-railway.sh
# =============================================================================

set -e  # Arrêter le script en cas d'erreur

echo "🚂 =========================================="
echo "🚂 ArchiMeuble - Déploiement Railway"
echo "🚂 Environnement: SERVER TEST"
echo "🚂 =========================================="
echo ""

# Vérifier que Railway CLI est installé
if ! command -v railway &> /dev/null; then
    echo "❌ Erreur: Railway CLI n'est pas installé"
    echo "📦 Installation: npm install -g @railway/cli"
    exit 1
fi

echo "✅ Railway CLI détecté"
echo ""

# Vérifier qu'on est bien dans le dossier back
if [ ! -f "railway.toml" ]; then
    echo "❌ Erreur: Fichier railway.toml introuvable"
    echo "📁 Assurez-vous d'être dans le dossier back/"
    exit 1
fi

echo "✅ Configuration Railway détectée"
echo ""

# Afficher la branche actuelle
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo "📍 Branche actuelle: $CURRENT_BRANCH"
echo ""

# Vérifier si on est sur la branche server_test
if [ "$CURRENT_BRANCH" != "server_test" ]; then
    echo "⚠️  Vous n'êtes pas sur la branche server_test"
    read -p "Voulez-vous basculer sur server_test? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🔄 Basculement sur server_test..."
        git fetch origin
        git checkout server_test
        git pull origin server_test
        echo "✅ Branche server_test activée"
    else
        echo "❌ Déploiement annulé"
        exit 1
    fi
fi

echo ""
echo "🔍 Vérification des fichiers modifiés..."
git status --short

echo ""
read -p "📤 Voulez-vous pousser les modifications locales avant le déploiement? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📤 Push vers GitHub..."
    git push origin server_test
    echo "✅ Code poussé sur GitHub"
fi

echo ""
echo "🚀 Démarrage du déploiement sur Railway..."
echo ""

# Déployer sur Railway
railway up --detach

echo ""
echo "✅ Déploiement initié avec succès!"
echo ""
echo "📊 Pour voir les logs en temps réel:"
echo "   railway logs"
echo ""
echo "🌐 Pour ouvrir le projet dans Railway:"
echo "   railway open"
echo ""
echo "🔧 Pour voir les variables d'environnement:"
echo "   railway variables"
echo ""
echo "⚠️  N'oubliez pas de configurer les variables d'environnement dans Railway!"
echo "   Consultez le fichier .env.server_test pour la liste complète"
echo ""
echo "🎉 Déploiement terminé!"
