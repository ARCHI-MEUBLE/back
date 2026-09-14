"""Fonctions géométriques pures, sans état, extraites de procedure_real.py.

Aucune de ces fonctions ne dépend d'un état global du script : elles ne
font que des calculs à partir de leurs arguments.
"""

import numpy as np
import trimesh
from PIL import Image, ImageDraw, ImageFont


def slice(points, plane):  # ENTREE  des points 3D et un plan (respresentation cartésienne) SORTIE un vecteur booleen qui indique de quel coté du plan sont les points
    """
    Détermine si des points 3D sont du côté positif ou négatif d'un plan cartésien.

    Parameters:
    points (numpy.ndarray): Un tableau Nx3 représentant des points 3D.
    plane (tuple): Les coefficients (a, b, c, d) du plan cartésien : ax + by + cz + d = 0.

    Returns:
    numpy.ndarray: Un tableau booléen de taille N avec True si le point est du côté positif,
                   et False si du côté négatif ou sur le plan.
    """
    # Décompose les coefficients du plan
    a, b, c, d = plane

    # Calculer la valeur du plan pour chaque point
    distances = a * points[:, 0] + b * points[:, 1] + c * points[:, 2] + d

    # Retourne True pour les points du côté positif, False pour les autres
    return distances > 0


def plane_equation(P1, P2, P3):  # ENTREE 3 points SORTIE une equation cartésienne de plan passant par les 3 points
    """
    Compute the equation of a plane given three points in 3D space.
    The normal vector is normalized to have a 1-norm.

    Parameters:
    P1, P2, P3 (numpy.ndarray): Three points defining the plane, each as a 3D vector.

    Returns:
    numpy.ndarray: The coefficients [a, b, c, d] of the plane equation ax + by + cz = d.
    """

    # Compute two vectors in the plane
    v1 = P2 - P1
    v2 = P3 - P1

    # Compute the normal vector (cross product of v1 and v2)
    normal = np.cross(v1, v2)

    # Normalize the normal vector to 1-norm
    norm = np.linalg.norm(normal)
    if norm == 0:
        raise ValueError("The three points are collinear and do not define a plane.")
    normal = normal / norm

    # Extract components of the normalized normal vector (a, b, c)
    a, b, c = normal

    # Compute the value d using the point P1
    d = -np.dot(normal, P1)

    # Return the equation of the plane
    return np.array([a, b, c, d])


def line_plane_intersection(P1, P2, plane):  # ENTREE deux points (definisant une droite) et un plan SORTIE point d'intersection droite/plan
    """
    Calculate the intersection point between a line and a plane.

    Parameters:
    - P1 (np.ndarray): A point on the line (3D coordinates).
    - P2 (np.ndarray): Another point on the line (3D coordinates).
    - plane (np.ndarray): The plane equation [a, b, c, d].

    Returns:
    - np.ndarray: The intersection point (3D coordinates), or None if the line is parallel to the plane.
    """
    # Extract plane parameters
    a, b, c, d = plane

    # Direction vector of the line
    direction = P2 - P1

    # Calculate the denominator (dot product of normal vector and line direction)
    denominator = a * direction[0] + b * direction[1] + c * direction[2]

    if np.isclose(denominator, 0):  # The line is parallel to the plane
        return None

    # Calculate the parameter t
    numerator = -(a * P1[0] + b * P1[1] + c * P1[2] + d)
    t = numerator / denominator

    # Intersection point
    intersection = P1 + t * direction
    return intersection


def reconstruct_contour(segments):  # ENTREE liste de couples d'indices SORTIE contour formée par les segments en fermant le contour

    # Étape 1 : Normaliser les segments (trier les sommets dans chaque segment)
    mask = ~(segments == [-1, -1]).all(axis=1)
    segments = segments[mask]
    normalized_segments = np.sort(segments, axis=1)

    # Étape 2 : Construire un graphe de connexions
    connections = {}
    for seg in normalized_segments:
        a, b = seg
        if a not in connections:
            connections[a] = []
        if b not in connections:
            connections[b] = []
        connections[a].append(b)
        connections[b].append(a)

    # Étape 3 : Identifier les sommets de degré impair
    odd_vertices = [v for v, neighbors in connections.items() if len(neighbors) % 2 == 1]

    # Si deux sommets de degré impair existent, ajouter une arête pour les connecter
    if len(odd_vertices) == 2:
        a, b = odd_vertices
        connections[a].append(b)
        connections[b].append(a)

    # Étape 4 : Reconstituer le contour
    contour = []
    start = list(connections.keys())[0]  # Démarrer avec un sommet arbitraire
    current = start
    visited = set()

    while True:
        contour.append(current)
        visited.add(current)

        # Trouver le prochain sommet connecté qui n'est pas encore visité
        next_vertex = [v for v in connections[current] if v not in visited]
        if not next_vertex:  # Si aucune connexion disponible, le contour est terminé
            break
        current = next_vertex[0]

    return np.array(contour)


def project_points_on_plane(points, origin, u, v):
    """
    Projette un ensemble de points 3D sur un plan défini par deux vecteurs de base (u, v).

    Args:
        points (ndarray): Tableau de forme (N, 3) contenant les coordonnées des points 3D.
        origin (ndarray): Point d'origine du plan en 3D.
        u (ndarray): Vecteur directeur du premier axe du plan.
        v (ndarray): Vecteur directeur du second axe du plan.

    Returns:
        ndarray: Tableau de forme (N, 2) contenant les coordonnées (x, y) dans le plan.
    """
    # Vérifier que u et v sont orthogonaux
    if not np.isclose(np.dot(u, v), 0):
        raise ValueError("Les vecteurs u et v doivent être orthogonaux.")

    # Normaliser u et v
    u = u / np.linalg.norm(u)
    v = v / np.linalg.norm(v)

    # Centrer les points par rapport à l'origine du plan
    relative_points = points - origin

    # Projeter les points sur la base (u, v)
    xy_coordinates = np.column_stack((np.dot(relative_points, u), np.dot(relative_points, v)))

    return xy_coordinates


def perpendicular_unit_vectors(normal):  # ENTREE un vecteur 3D SORTIE deux vecteurs tq les 3 vecteur forment une base orthonormée

    # Normalize the input vector
    normal = np.array(normal, dtype=float)
    normal /= np.linalg.norm(normal)

    # Choose an arbitrary vector not parallel to the normal
    if abs(np.dot(normal, np.array([1, 0, 0]))) > 0.9:  # If normal is aligned with X-axis
        v = np.array([0, 1, 0])  # Use Y-axis as reference
    else:
        v = np.array([1, 0, 0])  # Otherwise, use X-axis as reference

    # Compute the first perpendicular vector
    u1 = np.cross(normal, v)

    u1 = u1 / np.linalg.norm(u1)  # Normalize

    # Compute the second perpendicular vector
    u2 = np.cross(normal, u1)
    u2 /= np.linalg.norm(u2)  # Normalize

    return u1, u2


def create_number_image(number, image_size=(500, 500), font_size=100,
                         background_color=(255, 255, 255), text_color=(0, 0, 0)):  # creer une image avec du texte
    """
    Crée une image avec un nombre centré.

    :param number: Le nombre à afficher.
    :param image_size: Taille de l'image (largeur, hauteur).
    :param font_size: Taille de la police pour le texte.
    :param background_color: Couleur de fond (R, G, B).
    :param text_color: Couleur du texte (R, G, B).
    :return: Une image PIL contenant le nombre.
    """
    # Créer une image avec la couleur de fond
    image = Image.new("RGB", image_size, background_color)
    draw = ImageDraw.Draw(image)

    # Charger une police (ou utiliser la police par défaut si indisponible)
    try:
        font = ImageFont.truetype("arial.ttf", font_size)
    except IOError:
        font = ImageFont.load_default()

    # Texte à afficher
    text = str(number)

    # Calculer les dimensions du texte
    text_bbox = draw.textbbox((0, 0), text, font=font)
    text_width = text_bbox[2] - text_bbox[0]
    text_height = text_bbox[3] - text_bbox[1]

    # Calculer la position centrée
    text_x = (image_size[0] - text_width) // 2
    text_y = (image_size[1] - text_height) // 2

    # Dessiner le texte sur l'image
    draw.text((text_x, text_y), text, fill=text_color, font=font)

    return image


def create_cylinder(d, l, P, normalv):  # ENTREE : parametres géometriques # SORTIE objet trimesh cylindirique
    # Créer un cylindre standard centré sur l'origine avec une orientation verticale
    # Diamètre d, Longueur l
    cylinder = trimesh.creation.cylinder(radius=d / 2, height=l)

    # Calculer une matrice de transformation pour l'orientation et la translation
    # Normaliser le vecteur normal
    normalv = normalv / np.linalg.norm(normalv)

    # Trouver un vecteur perpendiculaire au vecteur normal (pour définir l'orientation)
    if np.abs(normalv[0]) < 1e-6:
        perpendicular = np.array([1, 0, 0])
    else:
        perpendicular = np.array([0, 0, 1])

    # Calculer le quaternion de rotation pour aligner le cylindre avec le vecteur normal
    axis = np.cross(perpendicular, normalv)
    angle = np.arccos(np.dot(perpendicular, normalv))

    if np.linalg.norm(axis) > 1e-6:
        axis = axis / np.linalg.norm(axis)
        rotation = trimesh.transformations.rotation_matrix(angle, axis)
    else:
        rotation = np.eye(4)  # Pas de rotation nécessaire si déjà aligné

    # Appliquer la rotation et la translation
    cylinder.apply_transform(rotation)

    # Translater le cylindre pour qu'il soit centré sur P
    translation = np.eye(4)
    translation[:3, 3] = P

    cylinder.apply_transform(translation)

    return cylinder
