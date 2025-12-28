const bcrypt = require('bcryptjs');
const Database = require('better-sqlite3');
const path = require('path');

const email = 'b@gmail.com';
const newPassword = '12345678';
const dbPath = path.join(__dirname, '..', 'database', 'archimeuble.db');

console.log('=== Réinitialisation du mot de passe admin ===');
console.log('Email:', email);
console.log('Nouveau mot de passe:', newPassword);
console.log('Base de données:', dbPath);
console.log('');

try {
  // Ouvrir la base de données
  const db = new Database(dbPath);

  // Hash le mot de passe
  const passwordHash = bcrypt.hashSync(newPassword, 10);
  console.log('Hash généré:', passwordHash.substring(0, 30) + '...');
  console.log('');

  // Vérifier si l'admin existe
  const existing = db.prepare('SELECT * FROM admins WHERE email = ?').get(email);

  if (!existing) {
    console.log('❌ Admin non trouvé, création...');

    db.prepare(`
      INSERT INTO admins (username, email, password, created_at)
      VALUES (?, ?, ?, datetime('now'))
    `).run('b', email, passwordHash);

    console.log('✅ Admin créé avec succès !');
  } else {
    console.log('✓ Admin trouvé (ID:', existing.id + ')');
    console.log('Mise à jour du mot de passe...');

    // Tenter de mettre à jour password_hash d'abord, puis password si ça échoue
    try {
      db.prepare('UPDATE admins SET password_hash = ? WHERE email = ?').run(passwordHash, email);
      console.log('✅ Mot de passe mis à jour (password_hash) !');
    } catch (e) {
      db.prepare('UPDATE admins SET password = ? WHERE email = ?').run(passwordHash, email);
      console.log('✅ Mot de passe mis à jour (password) !');
    }
  }

  console.log('');
  console.log('=== Vérification ===');

  // Récupérer l'admin mis à jour
  const admin = db.prepare('SELECT * FROM admins WHERE email = ?').get(email);
  const storedHash = admin.password_hash || admin.password;

  if (bcrypt.compareSync(newPassword, storedHash)) {
    console.log('✅ Connexion testée avec succès !');
    console.log('');
    console.log('Vous pouvez maintenant vous connecter avec:');
    console.log('  Email:', email);
    console.log('  Mot de passe:', newPassword);
  } else {
    console.log('❌ Échec de la vérification');
  }

  db.close();
  console.log('');
  console.log('✅ Terminé !');
} catch (error) {
  console.error('❌ Erreur:', error.message);
  process.exit(1);
}
