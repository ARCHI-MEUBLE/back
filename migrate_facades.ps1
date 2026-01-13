#!/usr/bin/env pwsh
# Script PowerShell pour appliquer la migration des façades

Write-Host "=== Migration du module Façades ===" -ForegroundColor Cyan
Write-Host ""

$DB_PATH = "database/archimeuble.db"
$MIGRATION_FILE = "backend/migrations/010_create_facades.sql"

# Vérifier que le fichier de base de données existe
if (-Not (Test-Path $DB_PATH)) {
    Write-Host "❌ Erreur: Base de données non trouvée: $DB_PATH" -ForegroundColor Red
    exit 1
}

# Vérifier que le fichier de migration existe
if (-Not (Test-Path $MIGRATION_FILE)) {
    Write-Host "❌ Erreur: Fichier de migration non trouvé: $MIGRATION_FILE" -ForegroundColor Red
    exit 1
}

Write-Host "📂 Base de données: $DB_PATH" -ForegroundColor Yellow
Write-Host "📄 Migration: $MIGRATION_FILE" -ForegroundColor Yellow
Write-Host ""

# Vérifier si SQLite3 est disponible
$sqliteCmd = Get-Command sqlite3 -ErrorAction SilentlyContinue
if (-Not $sqliteCmd) {
    Write-Host "⚠️  SQLite3 n'est pas installé ou pas dans le PATH" -ForegroundColor Yellow
    Write-Host "Installation via winget..." -ForegroundColor Yellow
    winget install SQLite.SQLite
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Erreur lors de l'installation de SQLite3" -ForegroundColor Red
        exit 1
    }
}

Write-Host "🚀 Application de la migration..." -ForegroundColor Green

# Appliquer la migration
Get-Content $MIGRATION_FILE | sqlite3 $DB_PATH

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Migration appliquée avec succès!" -ForegroundColor Green
    Write-Host ""
    
    # Vérifier que les tables ont été créées
    Write-Host "📊 Vérification des tables créées:" -ForegroundColor Cyan
    $tables = sqlite3 $DB_PATH "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'facade%';"
    
    if ($tables) {
        Write-Host ""
        foreach ($table in $tables -split "`n") {
            if ($table) {
                Write-Host "  ✓ $table" -ForegroundColor Green
            }
        }
        Write-Host ""
        
        # Afficher le nombre de matériaux insérés
        $materialCount = sqlite3 $DB_PATH "SELECT COUNT(*) FROM facade_materials;"
        Write-Host "📦 Matériaux initiaux: $materialCount" -ForegroundColor Cyan
        
        # Afficher le nombre de types de perçages insérés
        $drillingCount = sqlite3 $DB_PATH "SELECT COUNT(*) FROM facade_drilling_types;"
        Write-Host "🔧 Types de perçages initiaux: $drillingCount" -ForegroundColor Cyan
        Write-Host ""
        
        Write-Host "🎉 Installation terminée! Vous pouvez maintenant:" -ForegroundColor Green
        Write-Host "   1. Accéder à la page utilisateur: http://localhost:3000/facades" -ForegroundColor White
        Write-Host "   2. Accéder au dashboard admin: http://localhost:3000/admin/facades" -ForegroundColor White
    } else {
        Write-Host "⚠️  Aucune table trouvée. La migration a peut-être échoué." -ForegroundColor Yellow
    }
} else {
    Write-Host "❌ Erreur lors de l'application de la migration" -ForegroundColor Red
    exit 1
}
