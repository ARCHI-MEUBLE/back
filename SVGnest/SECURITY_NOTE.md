# Security Note - SVGnest

## ⚠️ Known Security Issue

Le fichier `util/eval.js` utilise `new Function()` pour exécuter du code dynamique dans un web worker.

### Risque
- **Injection de code** si les données proviennent d'une source non fiable
- Classé comme vulnérabilité **CRITIQUE** par CodeQL

### Mitigation actuelle
1. SVGnest est exclu du code scanning (`.github/codeql/codeql-config.yml`)
2. Remplacé `eval()` par `new Function()` (légèrement plus sûr)
3. SVGnest utilisé uniquement en interne pour le nesting de découpe

### Recommandations futures
- [ ] Vérifier si une version plus récente de SVGnest existe
- [ ] Considérer une bibliothèque alternative sans eval/Function
- [ ] Isoler SVGnest dans un sandbox si possible

### Utilisation actuelle
SVGnest est appelé depuis `menuisier.php` pour optimiser la découpe de pièces.

**Date**: 2025-10-27
**Status**: Documenté, risque accepté pour usage interne
