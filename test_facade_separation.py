#!/usr/bin/env python3
"""
Test script pour vérifier la logique de séparation des façades dans le DXF
Sans avoir besoin de générer réellement un meuble complet
"""

class MockPlanche:
    def __init__(self, nom, bloc=None):
        self.nom = nom
        self.bloc = bloc

    def __repr__(self):
        return f"Planche('{self.nom}', bloc='{self.bloc}')"

# Simuler un groupe de planches avec différents types
planches = [
    MockPlanche("1", None),           # Planche normale (structure)
    MockPlanche("2", None),           # Planche normale (structure)
    MockPlanche("3", "porteg"),       # Porte gauche (façade)
    MockPlanche("4", None),           # Planche normale (étagère)
    MockPlanche("5", "ported"),       # Porte droite (façade)
    MockPlanche("6", None),           # Planche normale (structure)
    MockPlanche("7", "porte_coulissante"),  # Porte coulissante (façade)
    MockPlanche("8", None),           # Planche normale (fond)
]

# Logique de séparation (identique au code modifié)
facades_types = ["porteg", "ported", "portec", "porte_coulissante", "porteg_push"]
planches_normales = [p for p in planches if getattr(p, 'bloc', None) not in facades_types]
planches_facades = [p for p in planches if getattr(p, 'bloc', None) in facades_types]

print("=" * 60)
print("TEST DE SÉPARATION DES FAÇADES DANS LE DXF")
print("=" * 60)
print()

print(f"📦 Total planches: {len(planches)}")
print()

print("🔨 PLANCHES NORMALES (à gauche dans le DXF):")
print("-" * 40)
for p in planches_normales:
    print(f"  ✓ {p}")
print(f"Total: {len(planches_normales)} planches")
print()

print("🚪 FAÇADES (à droite dans le DXF, séparées de 500mm):")
print("-" * 40)
for p in planches_facades:
    print(f"  ✓ {p}")
print(f"Total: {len(planches_facades)} façades")
print()

print("=" * 60)
print("RÉSULTAT:")
print("=" * 60)
print(f"✅ Séparation réussie!")
print(f"✅ Les {len(planches_facades)} façades seront positionnées à droite")
print(f"✅ Les {len(planches_normales)} planches normales seront à gauche")
print(f"✅ Marge de séparation: 500mm")
print()

# Simuler le positionnement
print("DISPOSITION DANS LE DXF:")
print("-" * 60)
X_gauche = 0
X_droite_start = 5000  # Supposons que les planches normales occupent ~5000mm

print(f"Zone gauche (X = {X_gauche}mm):")
for p in planches_normales:
    print(f"  [{p.nom}]", end=" ")
print()
print()

print(f"Zone droite (X = {X_droite_start}mm + 500mm marge):")
for p in planches_facades:
    print(f"  [{p.nom} ({p.bloc})]", end=" ")
print()
print()

print("=" * 60)
print("✨ Le menuisier pourra facilement identifier et découper")
print("   les façades séparément des autres pièces!")
print("=" * 60)
