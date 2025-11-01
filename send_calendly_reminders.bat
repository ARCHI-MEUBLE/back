@echo off
REM Script pour envoyer les rappels Calendly
REM À exécuter toutes les heures via le Planificateur de tâches Windows

echo [%date% %time%] Starting Calendly reminders check...

docker exec archimeuble-backend php /app/backend/api/calendly/send_reminders.php >> "%~dp0backend\logs\reminders.log" 2>&1

if %ERRORLEVEL% EQU 0 (
    echo [%date% %time%] Reminders check completed successfully
) else (
    echo [%date% %time%] Error during reminders check: %ERRORLEVEL%
)
