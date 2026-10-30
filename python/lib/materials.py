"""Catalogue des matériaux (panneaux) utilisés pour fabriquer un meuble.

L'objet Texture permet d'avoir toutes les informations concernant les
matériaux utilisés : nom, référence fournisseur, épaisseur, largeur,
longueur, et prix.
"""

import json
import os


class Texture:
    def __init__(self, nom, ref, epaisseur, longueur, largeur, prix_m2_ht, surface_panneau, prix_panneau_ht):
        self.nom = nom
        self.ref = ref
        self.epaisseur = epaisseur
        self.longueur = longueur
        self.largeur = largeur
        self.prix_m2_ht = prix_m2_ht
        self.surface_panneau = surface_panneau
        self.prix_panneau_ht = prix_panneau_ht

    def __repr__(self):
        return f"Texture({self.nom}, {self.ref}, {self.epaisseur}mm, {self.longueur}x{self.largeur}, {self.prix_m2_ht}/m2)"


def load_textures_dict(python_dir):
    """Charge <python_dir>/textures/panneau.json et retourne un dict nom -> Texture."""
    json_file = os.path.join(python_dir, "textures", "panneau.json")

    try:
        with open(json_file, "r", encoding="utf-8") as file:
            json_data = json.load(file)
            data = json_data.get("panneaux", [])
    except FileNotFoundError:
        print(f"Erreur : le fichier {json_file} est introuvable.")
        data = []
    except json.JSONDecodeError as e:
        print(f"Erreur de décodage JSON : {e}")
        data = []
    except Exception as e:
        print(f"Autre erreur : {e}")
        data = []

    # Créer un dictionnaire pour stocker les textures avec des variables dynamiques
    textures_dict = {}
    for item in data:
        var_name = item["nom"]
        textures_dict[var_name] = Texture(**item)

    # Afficher les textures chargées
    for name, texture in textures_dict.items():
        print(f"{name} = {texture}")

    return textures_dict


def sectionner_par_texture(planches, textures_dict):
    """
    Groupe les planches par texture pour la génération du DXF.

    Parameters:
    planches (list): Liste des objets Planche
    textures_dict (dict): Catalogue nom -> Texture (voir load_textures_dict)

    Returns:
    list: Liste de listes de planches groupées par texture
    """
    groupes = {}

    for planche in planches:
        if not getattr(planche, 'planche', False) or not getattr(planche, 'texture', None):
            continue

        texture_obj = planche.texture
        if isinstance(texture_obj, str):
            texture_obj = textures_dict.get(texture_obj, textures_dict.get("Blanc Premium"))

        texture_name = texture_obj.nom if texture_obj else "Inconnu"

        if texture_name not in groupes:
            groupes[texture_name] = []  # Créer une nouvelle liste si la texture n'existe pas encore
        groupes[texture_name].append(planche)  # Ajouter la planche à la liste correspondante

    return list(groupes.values())
