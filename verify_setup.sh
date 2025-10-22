#!/bin/bash
# Script de vérification pour vos camarades

echo "🔍 Vérification de l'installation ArchiMeuble..."
echo ""

# 1. Vérifier Docker
echo "1️⃣  Vérification de Docker..."
if ! command -v docker &> /dev/null; then
    echo "❌ Docker n'est pas installé"
    echo "   Installer depuis : https://www.docker.com/products/docker-desktop"
    exit 1
fi
echo "✅ Docker installé : $(docker --version)"

# 2. Vérifier docker-compose
echo ""
echo "2️⃣  Vérification de docker-compose..."
if ! command -v docker-compose &> /dev/null; then
    echo "❌ docker-compose n'est pas installé"
    exit 1
fi
echo "✅ docker-compose installé : $(docker-compose --version)"

# 3. Vérifier la structure des dossiers
echo ""
echo "3️⃣  Vérification de la structure..."
if [ ! -d "../database" ]; then
    echo "❌ Le dossier database/ est manquant"
    echo "   Il doit être au même niveau que back/"
    exit 1
fi
echo "✅ Dossier database/ trouvé"

if [ ! -d "../front" ]; then
    echo "❌ Le dossier front/ est manquant"
    echo "   Il doit être au même niveau que back/"
    exit 1
fi
echo "✅ Dossier front/ trouvé"

# 4. Vérifier la base de données
echo ""
echo "4️⃣  Vérification de la base de données..."
if [ ! -f "../database/archimeuble.db" ]; then
    echo "⚠️  La base de données n'existe pas encore"
    echo "   Initialiser avec : docker-compose --profile init up db-init"
else
    echo "✅ Base de données trouvée"
fi

# 5. Vérifier le dossier models
echo ""
echo "5️⃣  Vérification du dossier models..."
if [ ! -d "../front/public/models" ]; then
    echo "⚠️  Dossier models manquant, création..."
    mkdir -p ../front/public/models
    echo "✅ Dossier models créé"
else
    echo "✅ Dossier models trouvé"
fi

# 6. Tester le build Docker
echo ""
echo "6️⃣  Test du build Docker..."
if docker-compose build > /dev/null 2>&1; then
    echo "✅ Image Docker construite avec succès"
else
    echo "❌ Erreur lors du build Docker"
    echo "   Voir les logs avec : docker-compose build"
    exit 1
fi

echo ""
echo "🎉 Tout est prêt !"
echo ""
echo "Pour démarrer l'application :"
echo "  1. Lancer le backend :  docker-compose up"
echo "  2. Lancer le frontend : cd ../front && npm run dev"
echo ""
