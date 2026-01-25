@echo off
REM Script de test pour verifier l'installation du module Facades (Docker)

echo.
echo ========================================
echo   Test du Module Facades (Docker)
echo ========================================
echo.

set PASSED=0
set FAILED=0

REM Verifier Docker
echo [TEST] Docker est actif...
docker ps >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo   [PASS] Docker est actif
    set /a PASSED+=1
) else (
    echo   [FAIL] Docker n'est pas lance
    set /a FAILED+=1
)

REM Verifier conteneur backend
echo [TEST] Conteneur backend existe...
docker ps -a --filter "name=archimeuble-backend" --format "{{.Names}}" | findstr /C:"archimeuble-backend" >nul
if %ERRORLEVEL% EQU 0 (
    echo   [PASS] Conteneur archimeuble-backend trouve
    set /a PASSED+=1
) else (
    echo   [FAIL] Conteneur archimeuble-backend introuvable
    set /a FAILED+=1
)

REM Verifier base de donnees
echo [TEST] Base de donnees existe...
if exist "database\archimeuble.db" (
    echo   [PASS] Base de donnees existe
    set /a PASSED+=1
) else (
    echo   [FAIL] Base de donnees introuvable
    set /a FAILED+=1
)

REM Verifier migration SQL
echo [TEST] Fichier migration existe...
if exist "backend\migrations\010_create_facades.sql" (
    echo   [PASS] Migration SQL existe
    set /a PASSED+=1
) else (
    echo   [FAIL] Fichier migration introuvable
    set /a FAILED+=1
)

REM Verifier APIs
echo [TEST] API facades.php existe...
if exist "backend\api\facades.php" (
    echo   [PASS] API facades existe
    set /a PASSED+=1
) else (
    echo   [FAIL] API facades introuvable
    set /a FAILED+=1
)

echo [TEST] API facade-materials.php existe...
if exist "backend\api\facade-materials.php" (
    echo   [PASS] API materials existe
    set /a PASSED+=1
) else (
    echo   [FAIL] API materials introuvable
    set /a FAILED+=1
)

echo [TEST] API facade-drilling-types.php existe...
if exist "backend\api\facade-drilling-types.php" (
    echo   [PASS] API drilling-types existe
    set /a PASSED+=1
) else (
    echo   [FAIL] API drilling-types introuvable
    set /a FAILED+=1
)

REM Verifier Frontend
echo [TEST] Page facades.tsx existe...
if exist "..\front\src\pages\facades.tsx" (
    echo   [PASS] Page facades existe
    set /a PASSED+=1
) else (
    echo   [FAIL] Page facades introuvable
    set /a FAILED+=1
)

echo [TEST] Composant FacadeViewer.tsx existe...
if exist "..\front\src\components\facades\FacadeViewer.tsx" (
    echo   [PASS] FacadeViewer existe
    set /a PASSED+=1
) else (
    echo   [FAIL] FacadeViewer introuvable
    set /a FAILED+=1
)

echo [TEST] Types facade.ts existe...
if exist "..\front\src\types\facade.ts" (
    echo   [PASS] Types facade existe
    set /a PASSED+=1
) else (
    echo   [FAIL] Types facade introuvable
    set /a FAILED+=1
)

echo.
echo ========================================
echo   Resultats
echo ========================================
echo.
echo Tests reussis: %PASSED%
echo Tests echoues: %FAILED%
echo.

if %FAILED% EQU 0 (
    echo [SUCCES] Tous les tests sont passes!
    echo.
    echo Prochaines etapes:
    echo   1. Executer: migrate_facades.bat
    echo   2. Acceder a: http://localhost:3000/facades
) else (
    echo [ATTENTION] Certains tests ont echoue
    echo Consultez FACADES_README.md pour plus d'aide
)

echo.
pause
