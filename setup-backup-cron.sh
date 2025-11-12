#!/bin/bash
# Script d'installation du cron pour backups automatiques
# À exécuter UNE SEULE FOIS sur Railway

echo "Installing backup cron job..."

# Rendre le script de backup exécutable
chmod +x /usr/local/bin/backup-database.sh

# Installer le cron job (tous les jours à 3h du matin)
echo "0 3 * * * /usr/local/bin/backup-database.sh >> /data/backup-cron.log 2>&1" | crontab -

# Vérifier l'installation
echo "Cron job installed:"
crontab -l

echo ""
echo "Backup will run every day at 3:00 AM"
echo "Logs will be saved to /data/backup-cron.log"
