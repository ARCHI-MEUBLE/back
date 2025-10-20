# ArchiMeuble — Frontend

Page d'accueil moderne inspirée de Tylko pour la marque artisanale ArchiMeuble. Le projet utilise **Next.js**, **TypeScript** et **Tailwind CSS**.

## Prérequis

- Node.js 18 ou plus récent
- npm (ou un équivalent compatible comme pnpm/yarn)

## Installation

```bash
npm install
```

## Développement

```bash
npm run dev
```

- L'application est accessible sur [http://localhost:3000](http://localhost:3000)
- Les modèles de meubles sont chargés via `GET /api/templates`

## Build de production

```bash
npm run build
npm run start
```

Le serveur Next.js s'exécute alors en mode production sur le port 3000.

## Structure principale

```
src/
 ├─ components/
 │   ├─ Header.tsx
 │   ├─ Hero.tsx
 │   ├─ FiltersBar.tsx
 │   ├─ ProductGrid.tsx
 │   ├─ ProductCard.tsx
 │   └─ Footer.tsx
 ├─ pages/
 │   └─ index.tsx
 └─ lib/
     └─ api.ts
```

- `fetchTemplates` (dans `src/lib/api.ts`) interroge `/api/templates` et renvoie les modèles.
- `ProductGrid` gère les états de chargement, d'erreur et de liste vide.

## Tests & lint

Aucun outil de test ou lint n'est configuré pour le moment. Ajoutez vos préférés selon les besoins du projet.
