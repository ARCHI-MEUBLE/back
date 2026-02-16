#!/usr/bin/env pwsh
# Script de test pour vérifier l'installation du module Façades

Write-Host "=== Test du Module Façades ===" -ForegroundColor Cyan
Write-Host ""

$DB_PATH = "database/archimeuble.db"
$API_URL = "http://localhost:8000"
$passed = 0
$failed = 0

# Fonction de test
function Test-Step {
    param(
        [string]$Description,
        [scriptblock]$Test
    )
    
    Write-Host "🧪 Test: $Description" -ForegroundColor Yellow -NoNewline
    try {
        $result = & $Test
        if ($result) {
            Write-Host " ✅ PASS" -ForegroundColor Green
            $script:passed++
            return $true
        } else {
            Write-Host " ❌ FAIL" -ForegroundColor Red
            $script:failed++
            return $false
        }
    } catch {
        Write-Host " ❌ ERROR: $_" -ForegroundColor Red
        $script:failed++
        return $false
    }
}

Write-Host "📋 Vérification de l'environnement..." -ForegroundColor Cyan
Write-Host ""

# Test 1: Base de données existe
Test-Step "Base de données existe" {
    Test-Path $DB_PATH
}

# Test 2: Table facades existe
Test-Step "Table 'facades' existe" {
    $result = sqlite3 $DB_PATH "SELECT name FROM sqlite_master WHERE type='table' AND name='facades';" 2>$null
    $result -eq "facades"
}

# Test 3: Table facade_materials existe
Test-Step "Table 'facade_materials' existe" {
    $result = sqlite3 $DB_PATH "SELECT name FROM sqlite_master WHERE type='table' AND name='facade_materials';" 2>$null
    $result -eq "facade_materials"
}

# Test 4: Table facade_drilling_types existe
Test-Step "Table 'facade_drilling_types' existe" {
    $result = sqlite3 $DB_PATH "SELECT name FROM sqlite_master WHERE type='table' AND name='facade_drilling_types';" 2>$null
    $result -eq "facade_drilling_types"
}

# Test 5: Table saved_facades existe
Test-Step "Table 'saved_facades' existe" {
    $result = sqlite3 $DB_PATH "SELECT name FROM sqlite_master WHERE type='table' AND name='saved_facades';" 2>$null
    $result -eq "saved_facades"
}

# Test 6: Données initiales - Matériaux
Test-Step "Matériaux initiaux présents" {
    $count = sqlite3 $DB_PATH "SELECT COUNT(*) FROM facade_materials;" 2>$null
    [int]$count -ge 5
}

# Test 7: Données initiales - Types de perçages
Test-Step "Types de perçages initiaux présents" {
    $count = sqlite3 $DB_PATH "SELECT COUNT(*) FROM facade_drilling_types;" 2>$null
    [int]$count -ge 3
}

Write-Host ""
Write-Host "📡 Test des fichiers API..." -ForegroundColor Cyan
Write-Host ""

# Test 8: API facades.php existe
Test-Step "API facades.php existe" {
    Test-Path "backend/api/facades.php"
}

# Test 9: API facade-materials.php existe
Test-Step "API facade-materials.php existe" {
    Test-Path "backend/api/facade-materials.php"
}

# Test 10: API facade-drilling-types.php existe
Test-Step "API facade-drilling-types.php existe" {
    Test-Path "backend/api/facade-drilling-types.php"
}

Write-Host ""
Write-Host "🎨 Test des fichiers Frontend..." -ForegroundColor Cyan
Write-Host ""

# Test 11: Page facades.tsx existe
Test-Step "Page facades.tsx existe" {
    Test-Path "../front/src/pages/facades.tsx"
}

# Test 12: Composant FacadeViewer existe
Test-Step "Composant FacadeViewer.tsx existe" {
    Test-Path "../front/src/components/facades/FacadeViewer.tsx"
}

# Test 13: Composant FacadeControls existe
Test-Step "Composant FacadeControls.tsx existe" {
    Test-Path "../front/src/components/facades/FacadeControls.tsx"
}

# Test 14: Types TypeScript existent
Test-Step "Types facade.ts existe" {
    Test-Path "../front/src/types/facade.ts"
}

# Test 15: Page admin existe
Test-Step "Page admin/facades.tsx existe" {
    Test-Path "../front/src/pages/admin/facades.tsx"
}

# Test 16: CSS façades existe
Test-Step "CSS facades.css existe" {
    Test-Path "../front/src/styles/facades.css"
}

Write-Host ""
Write-Host "═══════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

if ($failed -eq 0) {
    Write-Host "✨ Tous les tests sont passés! ($passed/$($passed + $failed))" -ForegroundColor Green
    Write-Host ""
    Write-Host "🚀 Prochaines étapes:" -ForegroundColor Cyan
    Write-Host "   1. Démarrer le backend: php -S localhost:8000" -ForegroundColor White
    Write-Host "   2. Démarrer le frontend: cd ../front && npm run dev" -ForegroundColor White
    Write-Host "   3. Accéder à: http://localhost:3000/facades" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "⚠️  $failed test(s) échoué(s) sur $($passed + $failed)" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "💡 Solutions possibles:" -ForegroundColor Cyan
    Write-Host "   - Exécuter la migration: .\migrate_facades.ps1" -ForegroundColor White
    Write-Host "   - Vérifier que tous les fichiers ont été créés" -ForegroundColor White
    Write-Host "   - Consulter FACADES_README.md pour plus d'aide" -ForegroundColor White
    Write-Host ""
}

Write-Host "📚 Documentation complète: FACADES_README.md" -ForegroundColor Cyan
Write-Host ""
