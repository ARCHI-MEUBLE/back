import sys
import trimesh
import numpy as np
from PIL import Image

def usage():
    print("Usage:")
    print("  Pour un cercle (cylindre) :")
    print("    python3 generate.py cercle epaisseur rayon texturePath outputFile")
    print("  Pour un rectangle ou carré :")
    print("    python3 generate.py rectangle epaisseur longueur largeur texturePath outputFile")
    sys.exit(1)

# Vérification et récupération des arguments
if len(sys.argv) < 2:
    usage()

shape = sys.argv[1]

if shape == "cercle":
    # On attend : shape, epaisseur, rayon, texturePath, outputFile
    if len(sys.argv) != 6:
        usage()
    try:
        epaisseur = float(sys.argv[2])
        rayon = float(sys.argv[3])
    except ValueError:
        print("Les valeurs de epaisseur et rayon doivent être numériques.")
        sys.exit(1)
    texturePath = sys.argv[4]
    outputFile = sys.argv[5]
elif shape in ["rectangle", "carré"]:
    # On attend : shape, epaisseur, longueur, largeur, texturePath, outputFile
    if len(sys.argv) != 7:
        usage()
    try:
        epaisseur = float(sys.argv[2])
        longueur = float(sys.argv[3])
        largeur = float(sys.argv[4])
    except ValueError:
        print("Les valeurs de epaisseur, longueur et largeur doivent être numériques.")
        sys.exit(1)
    texturePath = sys.argv[5]
    outputFile = sys.argv[6]
else:
    print("Forme non prise en charge.")
    usage()

# Chargement de l'image de texture
try:
    texture_image = Image.open(texturePath)
except Exception as e:
    print(f"Erreur lors de l'ouverture de l'image de texture : {e}")
    sys.exit(1)

# Création du mesh et calcul des coordonnées UV
if shape == "cercle":
    mesh = trimesh.creation.cylinder(radius=rayon, height=epaisseur, sections=32)
    # Calcul UV pour un mapping cylindrique
    theta = np.arctan2(mesh.vertices[:, 1], mesh.vertices[:, 0])
    uv_u = (theta + np.pi) / (2 * np.pi)
    uv_v = (mesh.vertices[:, 2] - mesh.vertices[:, 2].min()) / (mesh.vertices[:, 2].ptp())
    uv = np.column_stack((uv_u, uv_v))
elif shape in ["rectangle", "carré"]:
    # Pour un carré, on affecte largeur = longueur
    if shape == "carré":
        largeur = longueur
    mesh = trimesh.creation.box(extents=[longueur, largeur, epaisseur])
    # Projection plane (X, Y) pour les UV
    uv = mesh.vertices[:, :2]
    uv_min = uv.min(axis=0)
    uv_max = uv.max(axis=0)
    uv = (uv - uv_min) / (uv_max - uv_min)
else:
    sys.exit("Forme non prise en charge.")

# Affectation de la texture au mesh via TextureVisuals
mesh.visual = trimesh.visual.texture.TextureVisuals(uv=uv, image=texture_image)

# Exportation du modèle 3D texturé au format GLB
mesh.export("objet.glb", file_type="glb")
print(f"Modèle 3D {shape} généré avec succès dans {outputFile}")
