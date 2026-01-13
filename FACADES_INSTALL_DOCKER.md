# 🚀 Installation Rapide du Module Façades (Docker)

## 📌 Pourquoi le script .ps1 ne fonctionne pas ?

Windows associe les fichiers `.ps1` comme fichiers texte et les ouvre dans Notepad au lieu de les exécuter.

**Solution** : Utilisez les fichiers `.bat` qui fonctionnent directement !

---

## ✅ Installation en 2 Étapes

### 1️⃣ Tester l'installation
```cmd
cd back
test_facades.bat
```

### 2️⃣ Appliquer la migration
```cmd
migrate_facades.bat
```

C'est tout ! 🎉

---

## 🌐 Accès aux Pages

Une fois la migration terminée :

- **Page Utilisateur** : http://localhost:3000/facades
- **Dashboard Admin** : http://localhost:3000/admin/facades
- **API** : http://localhost:8000/backend/api/facades.php

---

## 🔧 Si Docker n'est pas démarré

1. Lancez Docker Desktop
2. Démarrez votre backend :
   ```cmd
   docker-compose up -d
   ```
3. Relancez `migrate_facades.bat`

---

## 📱 Navigation

Le lien **"Façades"** a été ajouté au menu de navigation du site entre "Nos modèles" et "Catalogue".

---

## 🐛 Résolution de Problèmes

### Le script .bat ne s'exécute pas
- Clic droit sur `migrate_facades.bat` → **"Exécuter en tant qu'administrateur"**

### Docker n'est pas accessible
```cmd
docker ps
```
Si erreur : Démarrez Docker Desktop

### Le conteneur backend n'existe pas
```cmd
cd back
docker-compose up -d
```

### La migration échoue
Vérifiez que le fichier existe :
```cmd
dir backend\migrations\010_create_facades.sql
```

---

## 📚 Documentation Complète

- **[FACADES_README.md](FACADES_README.md)** - Guide complet
- **[FACADES_ROADMAP.md](../FACADES_ROADMAP.md)** - Évolutions futures
- **[FACADES_SUMMARY.md](../FACADES_SUMMARY.md)** - Architecture visuelle

---

## 🎯 Prochaines Étapes

1. ✅ Installer avec `migrate_facades.bat`
2. 🌐 Tester sur http://localhost:3000/facades
3. 🎨 Créer votre première façade
4. 🔧 Explorer le dashboard admin

---

## 💡 Astuce

Pour exécuter les scripts PowerShell à l'avenir :
```powershell
# Dans PowerShell (pas CMD)
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
.\migrate_facades.ps1
```

Mais les fichiers `.bat` sont plus simples et fonctionnent toujours ! 👍
