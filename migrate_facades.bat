@echo off
REM Script Batch pour migrer les facades dans Docker
REM Ce script fonctionne meme si PowerShell est bloque

echo.
echo ========================================
echo   Migration du module Facades (Docker)
echo ========================================
echo.

REM Verifier que Docker est lance
docker ps >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] Docker n'est pas lance ou n'est pas accessible
    echo Veuillez demarrer Docker Desktop et reessayer
    pause
    exit /b 1
)

echo [INFO] Docker est actif
echo.

REM Verifier que le conteneur backend existe
docker ps -a --filter "name=archimeuble-backend" --format "{{.Names}}" | findstr /C:"archimeuble-backend" >nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERREUR] Le conteneur 'archimeuble-backend' n'existe pas
    echo Veuillez d'abord demarrer le backend avec: docker-compose up -d
    pause
    exit /b 1
)

echo [INFO] Conteneur backend trouve
echo.

REM Verifier que le conteneur est en cours d'execution
docker ps --filter "name=archimeuble-backend" --format "{{.Names}}" | findstr /C:"archimeuble-backend" >nul
if %ERRORLEVEL% NEQ 0 (
    echo [ATTENTION] Le conteneur n'est pas en cours d'execution
    echo Demarrage du conteneur...
    docker start archimeuble-backend
    timeout /t 3 /nobreak >nul
)

echo [INFO] Application de la migration SQL...
echo.

REM Appliquer la migration SQL via Docker
docker exec archimeuble-backend sqlite3 /app/database/archimeuble.db < backend/migrations/010_create_facades.sql

if %ERRORLEVEL% EQU 0 (
    echo.
    echo [SUCCES] Migration appliquee avec succes!
    echo.
    
    REM Verifier les tables creees
    echo [INFO] Verification des tables creees:
    docker exec archimeuble-backend sqlite3 /app/database/archimeuble.db "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'facade%%';"
    
    echo.
    echo [INFO] Nombre de materiaux:
    docker exec archimeuble-backend sqlite3 /app/database/archimeuble.db "SELECT COUNT(*) FROM facade_materials;"
    
    echo.
    echo [INFO] Nombre de types de percages:
    docker exec archimeuble-backend sqlite3 /app/database/archimeuble.db "SELECT COUNT(*) FROM facade_drilling_types;"
    
    echo.
    echo ========================================
    echo   Installation terminee!
    echo ========================================
    echo.
    echo Vous pouvez maintenant:
    echo   1. Acceder a la page: http://localhost:3000/facades
    echo   2. Acceder au dashboard: http://localhost:3000/admin/facades
    echo.
) else (
    echo.
    echo [ERREUR] Erreur lors de l'application de la migration
    echo Verifiez que le fichier backend/migrations/010_create_facades.sql existe
)

pause
