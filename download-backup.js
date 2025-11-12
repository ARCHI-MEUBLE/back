#!/usr/bin/env node
/**
 * Script pour télécharger les backups depuis Railway vers ton PC
 *
 * Usage:
 *   node download-backup.js
 *   npm run backup:download
 *
 * Configuration:
 *   Créer un fichier .backup-config.json avec:
 *   {
 *     "apiUrl": "https://ton-site.railway.app",
 *     "apiKey": "ta-cle-secrete-ici"
 *   }
 */

const fs = require('fs');
const path = require('path');
const https = require('https');

// Configuration
const CONFIG_FILE = '.backup-config.json';
const LOCAL_BACKUP_DIR = './local-backups';

// Charger la configuration
function loadConfig() {
  if (!fs.existsSync(CONFIG_FILE)) {
    console.error(`❌ Fichier de configuration introuvable: ${CONFIG_FILE}`);
    console.log(`
Créez le fichier ${CONFIG_FILE} avec:
{
  "apiUrl": "https://ton-site.railway.app",
  "apiKey": "votre-cle-api-secrete"
}
`);
    process.exit(1);
  }

  try {
    const config = JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8'));

    if (!config.apiUrl || !config.apiKey) {
      throw new Error('apiUrl and apiKey are required');
    }

    return config;
  } catch (error) {
    console.error(`❌ Erreur lors de la lecture de ${CONFIG_FILE}:`, error.message);
    process.exit(1);
  }
}

// Créer le dossier local-backups si nécessaire
function ensureLocalBackupDir() {
  if (!fs.existsSync(LOCAL_BACKUP_DIR)) {
    fs.mkdirSync(LOCAL_BACKUP_DIR, { recursive: true });
    console.log(`✅ Dossier créé: ${LOCAL_BACKUP_DIR}`);
  }
}

// Faire une requête HTTPS
function httpsRequest(url, method = 'GET') {
  return new Promise((resolve, reject) => {
    const req = https.request(url, { method }, (res) => {
      let data = '';

      res.on('data', (chunk) => {
        data += chunk;
      });

      res.on('end', () => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          try {
            resolve(JSON.parse(data));
          } catch {
            resolve(data);
          }
        } else {
          reject(new Error(`HTTP ${res.statusCode}: ${data}`));
        }
      });
    });

    req.on('error', reject);
    req.end();
  });
}

// Télécharger un fichier
function downloadFile(url, destPath) {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(destPath);

    https.get(url, (response) => {
      if (response.statusCode !== 200) {
        reject(new Error(`HTTP ${response.statusCode}`));
        return;
      }

      const totalSize = parseInt(response.headers['content-length'], 10);
      let downloadedSize = 0;

      response.on('data', (chunk) => {
        downloadedSize += chunk.length;
        const progress = ((downloadedSize / totalSize) * 100).toFixed(2);
        process.stdout.write(`\r📥 Téléchargement: ${progress}% (${(downloadedSize / 1024 / 1024).toFixed(2)} MB)`);
      });

      response.pipe(file);

      file.on('finish', () => {
        file.close();
        console.log('\n✅ Téléchargement terminé');
        resolve();
      });
    }).on('error', (err) => {
      fs.unlink(destPath, () => {});
      reject(err);
    });
  });
}

// Lister les backups disponibles
async function listBackups(config) {
  console.log('📋 Récupération de la liste des backups...\n');

  const url = `${config.apiUrl}/backend/api/system/db-maintenance?key=${config.apiKey}`;

  try {
    const response = await httpsRequest(url);

    if (!response.success || !response.backups) {
      throw new Error('Invalid response format');
    }

    return response.backups;
  } catch (error) {
    console.error('❌ Erreur lors de la récupération des backups:', error.message);
    throw error;
  }
}

// Télécharger le dernier backup
async function downloadLatestBackup(config) {
  ensureLocalBackupDir();

  const backups = await listBackups(config);

  if (backups.length === 0) {
    console.log('⚠️  Aucun backup disponible');
    return;
  }

  const latest = backups[0];

  console.log(`🎯 Dernier backup trouvé:`);
  console.log(`   Fichier: ${latest.filename}`);
  console.log(`   Taille:  ${latest.size}`);
  console.log(`   Date:    ${latest.date}`);
  console.log('');

  const downloadUrl = `${config.apiUrl}/backend/api/system/db-maintenance/download/${latest.filename}?key=${config.apiKey}`;
  const destPath = path.join(LOCAL_BACKUP_DIR, latest.filename);

  await downloadFile(downloadUrl, destPath);

  console.log(`💾 Sauvegardé dans: ${destPath}`);
}

// Fonction principale
async function main() {
  console.log('🔐 ArchiMeuble - Téléchargement de backup\n');

  try {
    const config = loadConfig();
    await downloadLatestBackup(config);

    console.log('\n✨ Terminé avec succès !');
  } catch (error) {
    console.error('\n❌ Échec:', error.message);
    process.exit(1);
  }
}

// Exécution
if (require.main === module) {
  main();
}
