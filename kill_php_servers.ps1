# Script PowerShell pour arrêter tous les serveurs PHP locaux
# Et ne garder que Docker

Write-Host "Arret de tous les serveurs PHP locaux..." -ForegroundColor Yellow

# Trouver tous les processus PHP
$phpProcesses = Get-Process -Name "php" -ErrorAction SilentlyContinue

if ($phpProcesses) {
    Write-Host "Processus PHP trouves : $($phpProcesses.Count)" -ForegroundColor Cyan

    foreach ($proc in $phpProcesses) {
        Write-Host "  Arret du processus PHP (PID: $($proc.Id))..." -ForegroundColor Gray
        Stop-Process -Id $proc.Id -Force
    }

    Write-Host "Tous les serveurs PHP ont ete arretes !" -ForegroundColor Green
} else {
    Write-Host "Aucun processus PHP trouve." -ForegroundColor Green
}

Write-Host "`nVerification du port 8000..." -ForegroundColor Yellow
$port8000 = netstat -ano | Select-String ":8000.*LISTENING"

if ($port8000) {
    Write-Host "Processus ecoutant sur le port 8000 :" -ForegroundColor Cyan
    $port8000 | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
} else {
    Write-Host "Aucun processus n'ecoute sur le port 8000." -ForegroundColor Green
}

Write-Host "`nMaintenant, lancez Docker avec :" -ForegroundColor Yellow
Write-Host "  docker-compose up" -ForegroundColor Cyan
