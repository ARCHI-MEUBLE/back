# Script PowerShell pour créer la tâche planifiée Calendly
# Exécute send_calendly_reminders.bat toutes les 15 minutes

$taskName = "CalendlyReminders"
$scriptPath = "C:\Users\bensk\Desktop\archimeuble\back\send_calendly_reminders.bat"

# Supprimer la tâche si elle existe déjà
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

# Créer l'action (exécuter le script batch)
$action = New-ScheduledTaskAction -Execute $scriptPath

# Créer le déclencheur (toutes les 15 minutes, pendant 10 ans)
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 15) -RepetitionDuration (New-TimeSpan -Days 3650)

# Définir les paramètres d'exécution
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -RunLevel Highest

# Paramètres de la tâche
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

# Enregistrer la tâche
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description "Envoie les rappels Calendly toutes les 15 minutes (24h et 1h avant les RDV)"

Write-Host "Tâche planifiée '$taskName' créée avec succès!"
Write-Host "La tâche s'exécutera toutes les 15 minutes."
