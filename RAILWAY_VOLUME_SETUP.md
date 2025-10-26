# Configuration du Volume Persistant Railway

## Problème
À chaque redéploiement, Railway recrée le conteneur Docker et la base de données SQLite est perdue car elle n'est pas stockée dans un volume persistant.

## Solution : Configurer un Volume Railway

### Étape 1 : Accéder aux Settings du Service
1. Allez sur [Railway Dashboard](https://railway.app)
2. Sélectionnez votre projet **ArchiMeuble**
3. Cliquez sur le service **back** (backend)
4. Allez dans l'onglet **Settings**

### Étape 2 : Créer un Volume
1. Scroll jusqu'à la section **Volumes**
2. Cliquez sur **+ New Volume**
3. Remplissez les champs :
   - **Mount Path**: `/app/database`
   - **Name**: `archimeuble-database` (ou un nom de votre choix)
4. Cliquez sur **Add**

### Étape 3 : Redéployer
1. Railway va automatiquement redéployer le service
2. Cette fois, le dossier `/app/database` sera persistant
3. Les données ne seront plus perdues lors des redéploiements

## Vérification

Après configuration du volume :
1. Créez un admin et quelques modèles
2. Poussez un nouveau commit (n'importe quel changement mineur)
3. Attendez le redéploiement
4. Vérifiez que vos données sont toujours présentes

## Résultat

✅ **Les admins, users et modèles persisteront entre les redéploiements**
✅ **La base de données SQLite sera sauvegardée dans le volume Railway**
✅ **Vous ne perdrez plus vos données**

## Note Importante

Le volume Railway est facturé selon l'espace utilisé. Une base de données SQLite prend généralement très peu d'espace (quelques Mo).
