@echo off
echo ========================================
echo   Migration Parametres Facades
echo ========================================
echo.

REM Verifier si Docker est actif
docker ps >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERREUR] Docker n'est pas en cours d'execution
    echo Veuillez demarrer Docker Desktop et reessayer
    pause
    exit /b 1
)

REM Verifier le conteneur backend
docker ps | findstr archimeuble-backend >nul
if %errorlevel% neq 0 (
    echo [ERREUR] Le conteneur archimeuble-backend n'est pas actif
    echo Assurez-vous que le backend est demarre
    pause
    exit /b 1
)

echo [OK] Docker et conteneur backend actifs
echo.

REM Executer la migration
echo Application de la migration 011_create_facade_settings.sql...
docker exec -i archimeuble-backend sqlite3 /app/database/archimeuble.db < backend/migrations/011_create_facade_settings.sql

if %errorlevel% equ 0 (
    echo [OK] Migration appliquee avec succes !
    echo.
    
    REM Verification
    echo Verification des parametres...
    docker exec archimeuble-backend sqlite3 /app/database/archimeuble.db "SELECT * FROM facade_settings;"
    
    echo.
    echo ========================================
    echo   Migration terminee !
    echo ========================================
    echo.
    echo Les parametres de configuration des facades sont maintenant configurables
    echo depuis l'admin dashboard ^> Facades ^> Parametres
    echo.
) else (
    echo [ERREUR] Echec de la migration
    pause
    exit /b 1
)

pause
