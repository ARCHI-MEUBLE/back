# %%
#imports 
import numpy as np # numpy pour le calcul vectoriel 
import pyvista as pv # pyvista bibliothèque intermédiaire pour la 3D
from copy import deepcopy,copy # copy pour gerer les copy profonde des objets mutables
import trimesh # trimesh : librairy 3D principale
import sys # system
import ezdxf # edition de fichiers dxf 
from PIL import Image, ImageDraw, ImageFont # gestion des images (textures)
import svgwrite # édition de fichiers svg
import json # json : gestion de données 




# %%
# fonction utilitaires
def slice(points, plane): # ENTREE  des points 3D et un plan (respresentation cartésienne) SORTIE un vecteur booleen qui indique de quel coté du plan sont les points 
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
    return distances>0

def plane_equation(P1, P2, P3): # ENTREE 3 points SORTIE une equation cartésienne de plan passant par les 3 points 
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
    d =-np.dot(normal, P1)

    # Return the equation of the plane
    return np.array([a, b, c, d])

def line_plane_intersection(P1, P2, plane): #ENTREE deux points (definisant une droite) et un plan SORTIE point d'intersection droite/plan
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

def reconstruct_contour(segments): #ENTREE liste de couples d'indices SORTIE contour formée par les segments en fermant le contour

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

def perpendicular_unit_vectors(normal): #ENTREE un vecteur 3D SORTIE deux vecteurs tq les 3 vecteur forment une base orthonormée 

    # Normalize the input vector
    normal = np.array(normal, dtype=float)
    normal /= np.linalg.norm(normal)
    
    # Choose an arbitrary vector not parallel to the normal
    if abs(np.dot(normal,np.array([1, 0, 0])))>0.9:  # If normal is aligned with X-axis
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
                        
            background_color=(255, 255, 255), text_color=(0, 0, 0)): #creer une image avec du texte
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

def sectionner_par_texture(planches):
    """
    Groupe les planches par texture pour la génération du DXF.

    Parameters:
    planches (list): Liste des objets Planche

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

def create_cylinder(d, l, P, normalv): #ENTREE : parametres géometriques # SORTIE objet trimesh cylindirique 
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

# %%
#chargement des textures
# L'objet texture permet d'avoir toutes les informations concernant les matériaux utilisés : nom, reference founisseur, epaisseur, largeur, longueur, et prix

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

# Charger les données JSON depuis un fichier local
import os
script_dir = os.path.dirname(os.path.abspath(__file__))
json_file = os.path.join(script_dir, "textures", "panneau.json")

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
    #var_name = item["nom"].lower().replace(" ", "_")  # Normaliser le nom pour en faire une variable
    var_name = item["nom"]
    textures_dict[var_name] = Texture(**item)

# Afficher les textures chargées
for name, texture in textures_dict.items():
    print(f"{name} = {texture}")




# %%
#Definitions des classes 
# Les classes de cette partie permettent la represenation des objets utilisé dans un meuble 

class Alesage: # l'objet alésage défini en totalité les caractéistique d'un alésage dans une planche 
    def __init__(
    self,
    positionsnu=np.array([0,0,0]), #position dans le repère s(segment qui coupe le chant en 2) n (normale au chant) u (normale à la face usinage)
    positionxyz=np.array([0,0,0]), # position réelle dans l'espace du centre de l'alésage 
    type="cylindre", # type d'alésage 
    rayon=6, # rayon 
    profondeur = 15, # profondeur d'usinage 
    distance_au_coin = 50, # distance entre le coin de la planche et l'origine de l'alésage (le long du chant)
    face_usinage="chant", # "chant" ou "plat" type de surface sur laquelle faire l'alésage 
    couleur = "red" ): # couleur de la représentation en svg 
            
        self.positionxyz=positionxyz
        self.type = type
        self.rayon=rayon    
        self.profondeur=profondeur
        self.positionsnu= positionsnu
        self.face_usinage=face_usinage
        self.distance_au_coin = distance_au_coin
        self.couleur= couleur 
    def print(self) :
        print("positionxyz",self.positionxyz)
        print("positionsnu",self.positionsnu)
        print("rayon", self.rayon)
        

class Face: #l'objet face défini une face plane polygonale appartenant à une zone 
    def __init__(
        self,
        label: str, # le label d'une face donne sa position dans l'espace du meuble 
        equation: np.ndarray, #equation carésienne de la face 
        contour: np.ndarray, #polygone représenté par les index des points de la face dans l'ordre 
        alesages = None, # alésages sur la face 
        faceoppose=None, # face au dos de laquelle se situe la face 
        zone= None, # zone à laquelle appartient la face 
        facesupport=None, # face "mére" de laquelle herite cette face en cas de découpage 
        chant = False # True si la face est un chant False si c'est un plat 

    ):
        """Représente une face 3D.

        Args:
            label (str): Nom ou étiquette de la face.
            type_ (str): Type de la face (e.g., "plan", "courbe").
            equation (np.ndarray): Equation du plan [a, b, c, d].
            contour (np.ndarray): Points définissant le contour [liste des indices].
            alesages (List[Alesage], optional): Liste des alésages présents sur la face. Par défaut, aucune.
        """
        self.label = label
        self.equation = equation
        self.contour = contour
        self.alesages = alesages if alesages is not None else []
        self.faceoppose=faceoppose
        self.zone=zone
        self.facesupport=facesupport
        self.chant=chant
        
    def segments(self): # donne une représenation du contour par les segements qui le copmpose 
        face_indices=self.contour
        segments = np.column_stack((face_indices, np.roll(face_indices, -1)))
        return segments
    def print(self):
        """Affiche les détails de la face et de ses alésages."""
        print(f"Label: {self.label}")
        print(f"Type: {self.type}")
        print(f"Equation du plan: {self.equation.tolist()}")
        print(f"Contour: {self.contour.tolist()}")
        print(f"Alésages: {self.alesages}")
        print(f"faceoppose: {self.faceoppose}")
        print(f"facesupport: {self.facesupport}")
        print(f"zone: {self.zone}")
    def remonter_facesupport(self): #remonte la filiation des faces support ( mère, grand mère, etc) jusqu'a la face d'origine 
        # Condition d'arrêt : Si facesupport est None, on renvoie l'objet actuel
        if self.facesupport is None :   
            return self 
        else:
            return self.facesupport.remonter_facesupport()

class Zone: # l'objet zone défini un volume et des caractéristiques supplémentaires dans le cas où la zone est une planche 
    def __init__(
        self,
        listface, # liste d'objet face L'ensemble des faces forme une totoplogie fermée 
        points, # Array de points 3D 
        normalh, # vecteur perpendicualaire au plan horizontal du meuble
        normalv, # vecteur perpendicualaire au plan  verticale du meuble 
        normala, # vecteur permendiaculaire au plan avant (facade) du meuble 
        type = "zone", # type de zone : exemples "zone" "enveloppe_a" "enveloppe_d" "enveloppe_g" "cloisonnement_h"
        planche=False, # la zone est elle un planche ? 
        plan=None, # si oui il faut le plan de la planche 
        epaisseur = None, # une epaisseur de planche 
        face_usine = None, # une face d'usinage (on usine toujours une seule face à la CNC)
        bloc = None, # à quel bloc fonctionnel appartient l'objet ? "tiroir" "porte" None si corps de meuble 
        mesh = None, # objet Trimesh 3D qui est peut être crée grace à la methode trimesh pour les planches
        sens_fibres = None , # vecteur sens des fibres du bois 
        texture = None, # texture de la planche 
        biseau=False, # la planche comporte t elle un coupe en biseau ? 
        nom="meuble",
        handle_type=None
    ):
        """Représente une face 3D.

        Args:
            label (str): Nom ou étiquette de la face.
            type_ (str): Type de la face (e.g., "plan", "courbe").
            equation (np.ndarray): Equation du plan [a, b, c, d].
            contour (np.ndarray): Points définissant le contour [liste des indices].
            alesages (List[Alesage], optional): Liste des alésages présents sur la face. Par défaut, aucune.

        """
        self.points=points
        self.listface=listface
        self.normalh=normalh
        self.normalv=normalv
        self.normala=normala
        self.planche=planche
        self.type=type
        self.plan=plan
        self.epaisseur=epaisseur
        self.face_usine=face_usine
        self.bloc=bloc
        self.mesh = mesh
        self.sens_fibres=sens_fibres
        self.texture = texture
        self.biseau=biseau
        self.nom=nom
        self.handle_type=handle_type
    def clip(self,plan,label="l",mode="general"): #cette methode permet de produire de deux sous zone en découpant une zone en deux selon un plan 
        zoneplus=deepcopy(self)
        zonemoins=deepcopy(self)
        zoneplus.listface=[]
        zonemoins.listface=[]
        faces=self.listface
        points=self.points
        boolean=slice(points,plan)

        # contruction des segments 
        segments=[face.segments() for face in faces]
        segments=np.vstack(segments)
        segments=np.sort(segments,axis=1)
        segments=np.unique(segments,axis=0)
        segment_plus=deepcopy(segments)
        segment_moins=deepcopy(segments)
        for i,segment in enumerate(segments) :
            if np.all(boolean[segment])  :
                segment_moins[i]=np.array([-1,-1])
            elif  np.all(~boolean[segment]) :
                segment_plus[i]=np.array([-1,-1])
                1==1
            else :
                newpoint=line_plane_intersection(points[segment[0]],points[segment[1]],plan)
                zonemoins.points=np.vstack([zonemoins.points,newpoint])
                zoneplus.points=np.vstack([zoneplus.points,newpoint])
                if boolean[segment[0]] :
                    segment_plus[i]=np.array([segment[0],len(zoneplus.points)-1])
                    segment_moins[i]=np.array([segment[1],len(zoneplus.points)-1])
                else :
                    segment_plus[i]=np.array([segment[1],len(zoneplus.points)-1])
                    segment_moins[i]=np.array([segment[0],len(zoneplus.points)-1])


        #reconstitution des faces 
        for i,face in enumerate(faces) :
            if np.all(boolean[face.contour]) :

                faceplus = Face(label=face.label,equation=face.equation,contour=face.contour)
                faceplus.chant=False
                
                faceplus.facesupport=face
                zoneplus.listface.append(faceplus)
                #ajouter à zone plus
            elif np.all(~boolean[face.contour]) :
               
                
                facemoins = Face(label=face.label,equation=face.equation,contour=face.contour)
                facemoins.chant=False
                facemoins.facesupport=face
                zonemoins.listface.append(facemoins)
                #ajouter la face à zone -
            else : 
                segments_face = face.segments()
                segments_face = np.sort(segments_face,axis=1)
                segments_face = np.unique(segments_face,axis=0)

                mask = np.any((segments[:, None, :] == segments_face).all(axis=2), axis=1)
                segments_face_plus=segment_plus[mask]
                segments_face_moins=segment_moins[mask]

                face_plus=Face(label=face.label,equation=face.equation,contour=face.contour)
                face_moins=Face(label=face.label,equation=face.equation,contour=face.contour)


                face_plus.facesupport=face
                face_moins.facesupport=face

                face_plus.contour=reconstruct_contour(segments_face_plus)
                face_plus.chant=True
                zoneplus.listface.append(face_plus)
                
                face_moins.contour=reconstruct_contour(segments_face_moins)
                face_moins.chant=True
                zonemoins.listface.append(face_moins)
        
        # Reconstitution de la face de clippage et ajout à la zone 
        segments=[face.segments() for face in zoneplus.listface]
        segments=np.vstack(segments)
        segments=np.sort(segments,axis=1)
        unique, counts = np.unique(segments, axis=0, return_counts=True)
        single_occurrence_segments = unique[counts == 1]
        contour=reconstruct_contour(single_occurrence_segments)
        if label=="verticale":
            labelplus="d"
            labelmoins="g"
        elif label=="horizontale":
            labelplus="h"
            labelmoins="b"          

        elif label=="avant":
            labelplus="a"
            labelmoins="f"

        elif label =="d" :
            labelplus="g"
            labelmoins="d"         
        elif label =="g" :
            labelplus="d"
            labelmoins="g"  

        elif label =="b" :
            labelplus="h"
            labelmoins="b"    
                 
        elif label =="h" :
            labelplus="b"
            labelmoins="h" 
            
        elif label =="a" :
            labelplus="f"
            labelmoins="a"         
        elif label =="f" :
            labelplus="a"
            labelmoins="f"              
        else : 
            labelplus=label
            labelmoins=label

        newfaceplus=Face(label=labelplus,equation=-plan,contour=contour)
        newfacemoins=Face(label=labelmoins,equation=plan,contour=contour)
        

        if mode=="couper":
            newfacemoins.facesupport=None
            newfaceplus.facesupport=None
        else :
            newfacemoins.faceoppose=newfaceplus
            newfaceplus.faceoppose=newfacemoins
                
        zoneplus.listface.append(newfaceplus)
        zonemoins.listface.append(newfacemoins)

        for face in zoneplus.listface :
            face.zone=zoneplus
        for face in zonemoins.listface :
            face.zone=zonemoins     
   
        zoneplus.clear()
        zonemoins.clear()
 

        # ajouter un zone.clear 
        return zoneplus,zonemoins #zoneplus est la planche en mode enveloppe 
    def trimesh(self): # creer l'objet trimesh pour les planches 
        if not self.planche : 
            pass
        else :
            faces=self.listface
            points=self.points 
            faces_pv=[]     
            for face in faces :
                n=np.array([len(face.contour)])
                face_pv = np.concatenate((n,face.contour))
                
                faces_pv.append(face_pv)
            faces=np.concatenate(faces_pv)
            mesh_pv=pv.PolyData(points,faces)
            mesh_pv = mesh_pv.triangulate()
            vertices= mesh_pv.points
            faces = mesh_pv.faces.reshape(-1, 4)[:, 1:]  # Supprimer le premier élément (nombre de sommets par face)
            # Créer un objet trimesh
            mesh_trimesh = trimesh.Trimesh(vertices=vertices, faces=faces)
            mesh_trimesh.fix_normals()
            self.mesh = mesh_trimesh
            
        return self.mesh
    def clear(self): # supprime les points inutilisé dans les faces de la zone 
        index_utilises=np.unique(np.hstack([face.contour for face in self.listface]))

        new_indices = {old: new for new, old in enumerate(index_utilises)}

        for face in self.listface :
            face.contour=np.array([new_indices[idx] for idx in face.contour])

        self.points=self.points[index_utilises]
    def print(self): # affichage 
        print("point" , self.points)
        print("faces " , self.listface)
        print("normala",self.normala)
        print("normalv",self.normalv)
        print("normalh",self.normalh)

    def envelopper(self,label,epaisseur=19,texture="blanc") : # cree une (ou plusieurs) planche sur la bordure interne de la zone 

        facesconcerne=[face for face in self.listface if face.label==label]
        
        z2=self
        Listeplanches=[]
        for face in facesconcerne :
            
            plan=face.equation+np.array([0,0,0,epaisseur])
            z1,z2=z2.clip(plan,label)
            z1.planche=True
            z1.plan=face.equation+np.array([0,0,0,epaisseur/2])
            normale= z1.plan[:3]
            if abs(np.dot(normale,z1.normala))<1:  # Vérifie que n n'est pas parallèle à (0,1,0)
                v = z1.normala  # Un vecteur perpendiculaire
            else:  # Sinon, essaye avec (1,0,0)
                v = z1.normalv
            sens = np.cross(normale,v)  
            sens = sens /np.linalg.norm(sens)
            z1.sens_fibres= sens
            z1.epaisseur=epaisseur
            z1.type="enveloppe_"+label
            z1.face_usine=z1.listface[-1]
            z1.texture = texture
            Listeplanches.append(z1)
            
        return Listeplanches,z2 #z1 est la planche
    def couper(self,mode="proportions",prop=np.array([1,1]),longueurs=np.array([50,50,50]),dir="verticale"): # coupe une zone en n partie sans ajouter de planche
        if dir=="verticale":
            plan=self.normalv
        elif dir=="horizontale":
            plan=self.normalh
        elif dir=="avant":
            plan=self.normala
        
        points=self.points[np.hstack([face.contour for face in self.listface]).flatten()]
        scalars= points@plan
        max=np.max(scalars)
        min=np.min(scalars)
        longeur_totale= max-min 

        if mode=="longueurs":
            longueurs_sum = np.sum(longueurs)
            longeur_restante= longeur_totale - longueurs_sum
            if  longeur_restante<0:
                print("ERREUR : LE LONGEURS CUMULEES EXEDENT LA TAILLE DE LA ZONE")
            longueurs= np.append(longueurs, longeur_restante)
            prop = longueurs

        prop=prop/np.sum(prop)
        prop=np.cumsum(prop)
        
        reste=self
        zones=[]
   
        for i in range(len(prop)-1):
            d=min*(1-prop[i])+max*prop[i]
            plan_cut=np.append(plan,-d)
            # clip renvoie (plus, moins). Avec normale UP/RIGHT, plus=Haut/Droite, moins=Bas/Gauche
            z_plus, z_moins = reste.clip(plan_cut, label=dir, mode="couper")
            zones.append(z_moins)
            reste = z_plus

        zones.append(reste)
        return zones

    def cloisonner(self,mode="proportions",prop=np.array([1,1]),longueurs=np.array([50,50,50]),dir="verticale",epaisseur=19,texture="blanc"): # coupe une zone en n partie en ajoutant des planches de séparations 
        if dir=="verticale":
            plan=self.normalv
            label_usinage = "g"
        elif dir=="horizontale":
            plan=self.normalh
            label_usinage = "b"
        elif dir=="avant":
            plan=self.normala
            label_usinage = "a"
        
        points=self.points[np.hstack([face.contour for face in self.listface]).flatten()]
        scalars= points@plan
        max=np.max(scalars)
        min=np.min(scalars)
        longeur_totale= max-min 

        if mode=="longueurs":
            longueurs_sum = np.sum(longueurs)
            longeur_restante= longeur_totale - longueurs_sum
            if  longeur_restante<0:
                print("ERREUR : LE LONGEURS CUMULEES EXEDENT LA TAILLE DE LA ZONE")
            longueurs= np.append(longueurs, longeur_restante)
            prop = longueurs
        
        # Calculer les positions des séparations en tenant compte de l'épaisseur des planches
        n = len(prop)
        prop = prop / np.sum(prop)
        espace_utile = longeur_totale - epaisseur * (n - 1)
        
        # Positions relatives cumulées
        pos_rel = np.zeros(n + 1)
        for i in range(n):
            pos_rel[i+1] = pos_rel[i] + prop[i] * espace_utile + (epaisseur if i < n-1 else 0)

        reste = self
        zones = []
        planches = []
        
        for i in range(n - 1):
            # d_bas est la fin de la zone précédente (début de la planche)
            d_bas = min + pos_rel[i+1] - epaisseur
            # d_haut est le début de la zone suivante (fin de la planche)
            d_haut = min + pos_rel[i+1]
            
            plan_bas = np.append(plan, -d_bas)
            plan_haut = np.append(plan, -d_haut)
            plan_median = np.append(plan, -(d_bas + d_haut)/2)
            
            # On coupe pour extraire la zone du bas/gauche
            z_reste, z_zone = reste.clip(plan_bas, label=dir)
            zones.append(z_zone)
            
            # On coupe le reste pour extraire la planche
            z_reste, z_planche = z_reste.clip(plan_haut, label=dir)
            
            z_planche.planche = True
            z_planche.plan = plan_median
            z_planche.epaisseur = epaisseur
            z_planche.type = "cloisonnement_" + dir
            z_planche.texture = texture
            
            # Orientation des fibres
            normale = z_planche.plan[:3]
            v_ref = z_planche.normala if abs(np.dot(normale, z_planche.normala)) < 0.99 else z_planche.normalv
            sens = np.cross(normale, v_ref)
            z_planche.sens_fibres = sens / np.linalg.norm(sens)
            
            # Face d'usinage
            try:
                z_planche.face_usine = [face for face in z_planche.listface if face.label == label_usinage][0]
            except:
                z_planche.face_usine = z_planche.listface[0]
                
            planches.append(z_planche)
            reste = z_reste

        zones.append(reste)
        return zones, planches
    def rotation(self) : # permet de faire tourner les label d'une zone d'un quart de tour 
        for face in self.listface :
            if face.label == "a":
                face.label = "d"
            elif face.label =="f":
                face.label = "g"
            elif face.label == "g" :
                face.label = "a"
            elif face.label == "d":
                face.label = "f"
    
    def numeroter(self, number=11): #texture le mesh avec une image du number 
        if not self.planche :
            pass 
        else :
            v = self.mesh.vertices

            normale = self.plan[:3]

            tangent, bitangent = perpendicular_unit_vectors(normale)
            

            # Calculer les coordonnées UV en projetant les sommets sur le plan défini par tangent et bitangent
            uvs = np.dot(v, np.vstack([tangent, bitangent]).T)

            uvs[:, 0] = (uvs[:, 0] - np.min(uvs[:, 0]))
            uvs[:, 0] = uvs[:, 0] / np.max(uvs[:, 0])
            uvs[:, 1] = (uvs[:, 1] - np.min(uvs[:, 1]))
            uvs[:, 1] = uvs[:, 1] / np.max(uvs[:, 1])
            
            imagenumber=create_number_image(number)
        
            self.mesh.visual = trimesh.visual.texture.TextureVisuals(uv=uvs, image=imagenumber)      
    def texturer(self): # ajoute la texture au mesh trimesh 
        if not self.planche : 
            pass
        else :
            texture_obj = self.texture
            if isinstance(texture_obj, str):
                texture_obj = textures_dict.get(texture_obj, textures_dict.get("Blanc Premium"))
            
            if not texture_obj:
                return

            v=self.mesh.vertices
            
            normale=self.plan[:3]

            tangent=self.sens_fibres

            bitangent = np.cross(normale, tangent)
            
            # Calculer les coordonnées UV en projetant les sommets sur le plan défini par tangent et bitangent
            uvs = np.dot(v, np.vstack([tangent, bitangent]).T)
            
            x=np.max(uvs[:,0])-np.min(uvs[:,0])
            y=np.max(uvs[:,1])-np.min(uvs[:,1])

            uvs[:,0]=(uvs[:,0]-np.min(uvs[:,0]))
            uvs[:,0]=uvs[:,0]/np.max(uvs[:,0])
            uvs[:,1]=(uvs[:,1]-np.min(uvs[:,1]))
            uvs[:,1]=uvs[:,1]/np.max(uvs[:,1])

            x0=texture_obj.longueur
            y0=texture_obj.largeur

            texture_path = os.path.join(script_dir, "textures", texture_obj.nom + ".png")
            texture_image = Image.open(texture_path)
            width, height = texture_image.size
            xcrop=width*x/x0
            ycrop=height*y/y0
            # Adjust (left, upper, right, lower) to match the desired crop area

            cropped_texture_image = texture_image.crop((0, 0, xcrop, ycrop))
            self.mesh.visual = trimesh.visual.texture.TextureVisuals(uv=uvs,image = cropped_texture_image)
    def prix(self) : # calcul le cout matiere de la planche 
        texture_obj = self.texture
        if isinstance(texture_obj, str):
            texture_obj = textures_dict.get(texture_obj, textures_dict.get("Blanc Premium"))
            
        if not texture_obj:
            return 0

        surface = self.mesh.volume/texture_obj.epaisseur/1000000
        prix_m2 = texture_obj.prix_m2_ht
        return surface*prix_m2
    def perimetre(self): # calcule le permietre de la planche 
        chemin = self.points[self.face_usine.contour]
        distances = np.linalg.norm(np.diff(chemin, axis=0), axis=1)
        return np.sum(distances) + np.linalg.norm(chemin[-1] - chemin[0])


# %%
# definition du proces et parsing
def retirer_espaces(chaine):
    """
    Retire tous les espaces d'une chaîne de caractères.
    """
    return chaine.replace(" ", "")

def M0(a,b,c,d,e,f): # initialise la zone 

    points=np.array([
        [0,0,0],
        [a,0,0],
        [a,0,b],
        [0,0,b],
        [a,c,0],
        [0,e,0],
        [a,d,b],
        [0,f,b]
    ])
    
    points = np.array(points, dtype=np.float64)
    label='b'
    type='v'
    contour=np.array([0,1,2,3])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face1=Face(label,equation,contour,alesages)

    label='d'
    type='v'
    contour=np.array([1,4,6,2])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face2=Face(label,equation,contour,alesages)

    label='g'
    type='v'
    contour=np.array([0,3,7,5])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face3=Face(label,equation,contour,alesages)

    label='f'
    type='v'
    contour=np.array([0,5,4,1])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face4=Face(label,equation,contour,alesages)

    label='a'
    type='v'
    contour=np.array([3,2,6,7])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face5=Face(label,equation,contour,alesages)

    label='h'
    type='v'
    contour=np.array([4,5,7,6])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face6=Face(label,equation,contour,alesages)


    normala=-face5.equation[:3]
    normalh=face1.equation[:3]
    normalv=np.cross(normala,normalh)
    normalv=normalv/np.linalg.norm(normalv)
    faces=[face1,face2,face3,face4,face5,face6]

    zone=Zone(faces,points,normalh,normalv,normala)
    for face in zone.listface :
        face.zone=zone
    return zone

def M3(a,b,c,d):# initialise la zone
    meuble=M2(a,b,c,d)
    meuble.labels=np.array(["b","a","f","d","h","g"])

    return meuble

def M1(a, b, c):# initialise la zone
    return M2(a,b,c,c)

def M2(a, b, c,d):# initialise la zone
    return M0(a,b,c,d,c,d)

def M4(a,b,c,d) :# initialise la zone
    a,b,c,d = float(a),float(b),float(c), float(d)

    points=np.array([
        [0,0,c-b],
        [a,0,0],
        [a,0,c],
        [0,0,c],
        [a,d,0],
        [0,d,c-b],
        [a,d,c],
        [0,d,c]
    ])
    
    points = np.array(points, dtype=np.float64)
    label='b'
    type='v'
    contour=np.array([0,1,2,3])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face1=Face(label,equation,contour,alesages)

    label='d'
    type='v'
    contour=np.array([1,4,6,2])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face2=Face(label,equation,contour,alesages)

    label='g'
    type='v'
    contour=np.array([0,3,7,5])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face3=Face(label,equation,contour,alesages)

    label='f'
    type='v'
    contour=np.array([0,5,4,1])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face4=Face(label,equation,contour,alesages)

    label='a'
    type='v'
    contour=np.array([3,2,6,7])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face5=Face(label,equation,contour,alesages)

    label='h'
    type='v'
    contour=np.array([4,5,7,6])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face6=Face(label,equation,contour,alesages)


    normala=-face5.equation[:3]
    normalh=face1.equation[:3]
    normalv=np.cross(normala,normalh)
    normalv=normalv/np.linalg.norm(normalv)
    faces=[face1,face2,face3,face4,face5,face6]

    zone=Zone(faces,points,normalh,normalv,normala)
    for face in zone.listface :
        face.zone=zone
    return zone

def M5(a,b,c):# initialise la zone
    points=np.array([
        [0,0,0],
        [a,0,0],
        [0,0,b],
        [0,c,0],
        [a,c,0],
        [0,c,b]
    ])
    
    points = np.array(points, dtype=np.float64)
    label='b'
    type='v'
    contour=np.array([0,1,2])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face1=Face(label,equation,contour,alesages)

    label='d'
    type='v'
    contour=np.array([0,3,4,1])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face2=Face(label,equation,contour,alesages)

    label='g'
    type='v'
    contour=np.array([0,2,5,3])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face3=Face(label,equation,contour,alesages)



    label='a'
    type='v'
    contour=np.array([1,4,5,2])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face5=Face(label,equation,contour,alesages)

    label='h'
    type='v'
    contour=np.array([4,3,5])
    equation=plane_equation(points[contour[0]],points[contour[1]],points[contour[2]])
    alesages=[]
    face6=Face(label,equation,contour,alesages)

    
    normala=-(face5.equation[:3])
    normalh=face1.equation[:3]
    normalv=np.cross(normala,normalh)
    normalv=normalv/np.linalg.norm(normalv)
    faces=[face1,face2,face3,face5,face6]

    zone=Zone(faces,points,normalh,normalv,normala)
    for face in zone.listface :
        face.zone=zone
    return zone

def subsequence(sequence):# touve les sous sequences entre parentheses
    i=0
    depth=0
    virgules=[0]
    while i<len(sequence):
        char=sequence[i]
        if char=="(" or char=="[":
            depth=depth+1
        if char==")" or char=="]":
            depth=depth-1
        if char==",":
            if depth==1:
                virgules.append(i)
        if depth==0:
            virgules.append(i)
            subs=[]
            for j in range (len(virgules)-1):
                subs.append(sequence[virgules[j]+1:virgules[j+1]])
            return subs
        i=i+1

textures={"exterieur": textures_dict["Blanc Premium"],"interieur": textures_dict["Blanc Premium"],"porte": textures_dict["Chêne Brun"],"tiroir": textures_dict["Chêne Brun"]}

print(textures_dict["Chêne Brun"].epaisseur)

def process(sequence,zone,textures=textures) : # cette fonction sert à parser un sequence de caractère pour modeliser un meuble 
    #l'objet zone est ammenée à évoluer suite aux opération faites dessus cela repésente une zone d'espace 
    # l'objet Listplanches permet d'accumuler les planches qui résultent des différentes opérations
    
    #Cette fonction décode la sequence et exécute des fonctions plus bas niveaux qui vont crééer des planche.
    # La methode "envelopper" permet de creer des planches prise sur le bord de la zone, le long d'un label donné. Le label peut etre h (haut), b (bas), g(gauche), d (droite), f (fond), a (avant)
    # La méthode "cloisonner" permet de séparer une zone en sous-zones et de crééer des planches qui vont les séparer. Cette  séparation se fait selon 3 axes : "avant" "horizontal" et "verticale"
    # La methode "couper" permet de séparer une zone en sous-zone sans creer de nouvelle planche
    sequence=sequence+"   "
    Listplanches=[]
    i=0
    while i<len(sequence) :  #boucle de lecrture des caractères
        char=sequence[i]
        
        # Helper pour extraire le type de poignée (chiffre 1-4)
        def check_handle(idx):
            if idx + 1 < len(sequence) and sequence[idx+1].isdigit():
                return int(sequence[idx+1]), idx + 1
            return None, idx

        if char=="M": # "M"+"chiffre" permet la création d'une zone. Il faut obligatoirement initialiser le meuble par une création de zone 
            print("M")
            i=i+1
            char=sequence[i]
            if char=="0" : #ne jamais utiliser car très dangereux
                i=i+1
                seq1,seq2,seq3,seq4,seq5,seq6=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+len(seq4)+len(seq5)+len(seq6)+6
                zone=M0(seq1,seq2,seq3,seq4,seq5,seq6)
            if char=="1":# permet de faire un meuble rectangulaire avec 3 valeur en mm (largeur, profondeur,haueteur) Exemple : M1(1500,344,2000)
                i=i+1
                seq1,seq2,seq3=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+3
                zone=M0(seq1,seq2,seq3,seq3,seq3,seq3)
            if char=="2": # permet de faire un meuble sous mansarde avec 4 valeur en mm (largeur, profondeur,petite hauteur, grande hauteur) Exemple : M2(1500,344,1200,2000)
                i=i+1
                seq1,seq2,seq3,seq4=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+len(seq4)+4
                zone=M0(seq1,seq2,seq3,seq4,seq3,seq4)
            if char=="3": # permet de faire un meuble sous escalier avec 4 valeur en mm (largeur, profondeur,petite hauteur, grande hauteur) Exemple : M3(1500,344,1200,2000)
                i=i+1
                seq1,seq2,seq3,seq4=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+len(seq4)+4
                zone=M0(seq1,seq2,seq3,seq3,seq4,seq4)
            if char=="4": #ne pas utiliser car trop dangereux 
                i=i+1
                seq1,seq2,seq3,seq4=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+len(seq4)+4
                zone=M4(seq1,seq2,seq3,seq4)
            if char=="5": #permet de faire un meuble d'angle avec 3 valeur en mm (longeur1, longeur2,hauteur). Exemple M5(500,400,600)
                i=i+1
                seq1,seq2,seq3=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+3
                zone=M5(seq1,seq2,seq3)
        elif char=="E" : # E comme enveloppe : créer une enveloppe externe au meuble 
            print("E")
            planches,zone=zone.envelopper(label="d",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches
            planches,zone=zone.envelopper(label="g",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches
            planches,zone=zone.envelopper(label="h",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches
        elif char=="F" : # F permet d'ajouter une planche de fond
            print("F")
            planches,zone=zone.envelopper(label="f",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches  
        elif char=="V": # V permet de faire une séparation verticale
            print("V")
            i=i+1
            char=sequence[i]
            if char=="I": #si on ajoute I la séparartion devient invisible : il n'y a pas création de planche 
                print("I")
                i=i+1
                char=sequence[i]

                if char=="L": # si on ajoute L on passe en mode longueur. Le mode par défaut reste le mode proportions 
                    
                    mode="longueurs"
                    i=i+1
                    char=sequence[i]
                else : 
                    mode="proportions"
                

                if char=="(": #si on a des parenthèses directes, on divise juste en 2
                    
                    zones=zone.couper(dir="verticale")
                    Lmeubles=zones
                    seq1,seq2=subsequence(sequence[i:])
                    Lseq=[seq1,seq2]
                    # i sera incrémenté plus bas
                elif char=="[": #si on a des crochets, on indique des proportion ou des longeurs (si on est en mode longeur )
                    Lseq_prop=subsequence(sequence[i:])
                    i=i+1
                    sommelen=sum([len(seq) for seq in Lseq_prop])
                    i=i+sommelen+len(Lseq_prop)
                    Lseq_prop=[int(seq) for seq in Lseq_prop]
                    zones=zone.couper(dir="verticale",mode=mode , longueurs=np.array(Lseq_prop) , prop=np.array(Lseq_prop))
                    Lmeubles=zones
                    Lseq=[] # sera rempli par le ( ) suivant

                elif char.isdigit(): # si c'est un chriffre n on divise en n part égales
                    k=int(char)
                    prop=np.ones(k)
                    zones=zone.couper(dir="verticale",prop=prop)
                    Lmeubles=zones
                    Lseq=[]
                    i=i+1
                
                if i < len(sequence) and sequence[i]=="(": # si on descide de préciser ce qu'on veut dans chaque sous zone
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                
                if Lseq:
                    for j, seq in enumerate(Lseq)  : # on execute les sous séquence sur les sous meuble de gauche à droite 
                        if j < len(Lmeubles):
                            i=i+len(seq)+1
                            Listplanches=Listplanches+process(seq,Lmeubles[j],textures)



            else :  # meme chose qu'en haut mais on va crééer des planche 
                epaisseur=textures["interieur"].epaisseur
                if char=="L":
                    
                    mode="longueurs"
                    i=i+1
                    char=sequence[i]
                else : 
                    mode="proportions"
                

                if char=="(":
                    
                    zones,planches=zone.cloisonner(dir="verticale",epaisseur=epaisseur,texture=textures["interieur"])
                    Lmeubles=zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    seq1,seq2=subsequence(sequence[i:])
                    Lseq=[seq1,seq2]
                elif char=="[":
                    Lseq_prop=subsequence(sequence[i:])
                    i=i+1
                    sommelen=sum([len(seq) for seq in Lseq_prop])
                    i=i+sommelen+len(Lseq_prop)
                    Lseq_prop=[int(seq) for seq in Lseq_prop]
                    zones,planches=zone.cloisonner(dir="verticale",mode=mode , longueurs=np.array(Lseq_prop) , prop=np.array(Lseq_prop),epaisseur=epaisseur,texture=textures["interieur"])
                    Lmeubles=zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    Lseq=[]
                elif char.isdigit():
                    k=int(char)
                    prop=np.ones(k)
                    zones,planches=zone.cloisonner(dir="verticale",prop=prop,epaisseur=epaisseur,texture=textures["interieur"])
                    Lmeubles=zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    Lseq=[]
                    i=i+1
                
                if i < len(sequence) and sequence[i]=="(":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                
                if Lseq:
                    for j, seq in enumerate(Lseq)  :
                        if j < len(Lmeubles):
                            i=i+len(seq)+1
                            Listplanches=Listplanches+process(seq,Lmeubles[j],textures)
        elif char=="H":  # H permet de faire des séparation horizontale. En ajoutant "I" à la suite du H, on fait des séparations invisibles. Si on rajoute un "L" on passen en mode longueur, le mode par defaut étant la proportion 
            print("H")
            i=i+1
            char=sequence[i]
            if char=="I":
                print("I")
                i=i+1
                char=sequence[i]

                if char=="L":
                    
                    mode="longueurs"
                    i=i+1
                    char=sequence[i]
                else : 
                    mode="proportions"
                

                if char=="(":
                    
                    zones=zone.couper(dir="horizontale")
                    Lmeubles=zones
                    seq1,seq2=subsequence(sequence[i:])
                    Lseq=[seq1,seq2]
                elif char=="[":
                    Lseq_prop=subsequence(sequence[i:])
                    i=i+1
                    sommelen=sum([len(seq) for seq in Lseq_prop])
                    i=i+sommelen+len(Lseq_prop)
                    Lseq_prop=[int(seq) for seq in Lseq_prop]
                    zones=zone.couper(dir="horizontale",mode=mode , longueurs=np.array(Lseq_prop) , prop=np.array(Lseq_prop))
                    Lmeubles=zones
                    Lseq=[]

                elif char.isdigit():
                    k=int(char)
                    prop=np.ones(k)
                    zones=zone.couper(dir="horizontale",prop=prop)
                    Lmeubles=zones
                    Lseq=[]
                    i=i+1
                
                if i < len(sequence) and sequence[i]=="(":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                
                if Lseq:
                    for j, seq in enumerate(Lseq)  :  # On exécute les sous meubles sur les sous zones de bas en Haut 
                        if j < len(Lmeubles):
                            i=i+len(seq)+1
                            Listplanches=Listplanches+process(seq,Lmeubles[j],textures)



            else : 
                epaisseur=textures["interieur"].epaisseur
                if char=="L":
                    
                    mode="longueurs"
                    i=i+1
                    char=sequence[i]
                else : 
                    mode="proportions"
                

                if char=="(":
                    
                    zones,planches=zone.cloisonner(dir="horizontale",epaisseur=epaisseur,texture=textures["interieur"])
                    Lmeubles=zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    seq1,seq2=subsequence(sequence[i:])
                    Lseq=[seq1,seq2]
                elif char=="[":
                    Lseq_prop=subsequence(sequence[i:])
                    i=i+1
                    sommelen=sum([len(seq) for seq in Lseq_prop])
                    i=i+sommelen+len(Lseq_prop)
                    Lseq_prop=[int(seq) for seq in Lseq_prop]
                    zones,planches=zone.cloisonner(dir="horizontale",mode=mode , longueurs=np.array(Lseq_prop) , prop=np.array(Lseq_prop),epaisseur=epaisseur,texture=textures["interieur"])
                    Lmeubles=zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    Lseq=[]
                elif char.isdigit():
                    k=int(char)
                    prop=np.ones(k)
                    zones,planches=zone.cloisonner(dir="horizontale",prop=prop,epaisseur=epaisseur,texture=textures["interieur"])
                    Lmeubles=zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    Lseq=[]
                    i=i+1
                
                if i < len(sequence) and sequence[i]=="(":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                
                if Lseq:
                    for j, seq in enumerate(Lseq)  : 
                        if j < len(Lmeubles):
                            i=i+len(seq)+1
                            Listplanches=Listplanches+process(seq,Lmeubles[j],textures)
        elif char=="P": # P permet d'ajouter une porte elle peut être encastre si on la fait après les planches adjacentes (exemple EP) ou bien en applique si on la fait avant (exemple PE)
            print("P")
            i=i+1
            char=sequence[i]
            if char=="2" :
                handle_type, i = check_handle(i)
                planches,zone=zone.envelopper(label="a",epaisseur=textures["porte"].epaisseur+0.1,texture=textures["porte"])
                planche=planches[0]
                _,planche=planche.envelopper(label="g",epaisseur=3)
                _,planche=planche.envelopper(label="d",epaisseur=3)
                _,planche=planche.envelopper(label="h",epaisseur=3)
                _,planche=planche.envelopper(label="b",epaisseur=7)
                


                zones,planches=planche.cloisonner(dir="verticale",epaisseur=3,texture=textures["porte"])
                planchegauche = zones[0]
                planchedroite = zones[1]

                _,planchegauche=planchegauche.envelopper(label="a",epaisseur=1)
                _,planchedroite=planchedroite.envelopper(label="a",epaisseur=1)

                planchedroite.bloc="ported"
                planchegauche.bloc="porteg"
                
                planchegauche.handle_type = handle_type
                planchedroite.handle_type = handle_type

                planchedroite.face_usine=[face for face in planchedroite.listface if face.label=="f"][0]
                planchegauche.face_usine=[face for face in planchegauche.listface if face.label=="f"][0]
                Listplanches.append(planchegauche)
                Listplanches.append(planchedroite)
            elif char=="g" or char=="d":
                handle_type, i = check_handle(i)
                planches,zone=zone.envelopper(label="a",epaisseur=textures["porte"].epaisseur+0.1,texture=textures["porte"])
                planche=planches[0]
                _,planche=planche.envelopper(label="g",epaisseur=3)
                _,planche=planche.envelopper(label="d",epaisseur=3)
                _,planche=planche.envelopper(label="h",epaisseur=3)
                _,planche=planche.envelopper(label="b",epaisseur=7)
                _,planche=planche.envelopper(label="a",epaisseur=1)
                if char=="d":
                    planche.bloc="ported"
                elif char=="g" :
                    planche.bloc="porteg"
                
                planche.handle_type = handle_type
                planche.face_usine=[face for face in planche.listface if face.label=="f"][0]
                Listplanches.append(planche)
            elif char=="m": # MIROIR
                handle_type, i = check_handle(i)
                planches,zone=zone.envelopper(label="a",epaisseur=textures["porte"].epaisseur+0.1,texture=textures["porte"])
                planche=planches[0]
                _,planche=planche.envelopper(label="g",epaisseur=3)
                _,planche=planche.envelopper(label="d",epaisseur=3)
                _,planche=planche.envelopper(label="h",epaisseur=3)
                _,planche=planche.envelopper(label="b",epaisseur=7)
                _,planche=planche.envelopper(label="a",epaisseur=1)
                planche.bloc="miroir"
                planche.handle_type = handle_type
                planche.face_usine=[face for face in planche.listface if face.label=="f"][0]
                Listplanches.append(planche)
            elif char=="o": # PUSH TO OPEN
                planches,zone=zone.envelopper(label="a",epaisseur=textures["porte"].epaisseur+0.1,texture=textures["porte"])
                planche=planches[0]
                _,planche=planche.envelopper(label="g",epaisseur=3)
                _,planche=planche.envelopper(label="d",epaisseur=3)
                _,planche=planche.envelopper(label="h",epaisseur=3)
                _,planche=planche.envelopper(label="b",epaisseur=7)
                _,planche=planche.envelopper(label="a",epaisseur=1)
                planche.bloc="porteg" # On peut utiliser porteg pour le moment
                planche.face_usine=[face for face in planche.listface if face.label=="f"][0]
                Listplanches.append(planche)
            elif char=="c":
                planches,zone=zone.envelopper(label="a",epaisseur=62,texture=textures["porte"])
                planche=planches[0]
                for face in planche.listface :
                    if face.label=="f" :
                        hauteurs = np.dot(planche.points[face.contour],planche.normalh)
                        h= np.max(hauteurs)-np.min(hauteurs)
                        print(h)
                
                
                planche,coulisse=planche.couper(dir="horizontale",mode="longueurs", longueurs=np.array([h-43]))
                coulisse.bloc="coulisse"
                coulisse.texture=textures["interieur"]
                
                
                Listplanches.append(coulisse)

                _,planche=planche.envelopper(label="g",epaisseur=1)
                _,planche=planche.envelopper(label="d",epaisseur=1)
                
                _,planche=planche.envelopper(label="b",epaisseur=7)
                
                planche.bloc="porte_coulissante"

                planchedroite,planchegauche=planche.couper(dir="verticale")
                _,planchedroite=planchedroite.envelopper(label="a",epaisseur=10,texture=textures["porte"])
                planchedroite,_=planchedroite.envelopper(label="a",epaisseur=19,texture=textures["porte"])

                _,planchegauche=planchegauche.envelopper(label="f",epaisseur=10,texture=textures["porte"])
                planchegauche,_=planchegauche.envelopper(label="f",epaisseur=19,texture=textures["porte"])
                planchedroite=planchedroite[0]
                planchegauche=planchegauche[0]
                
                #planchedroite.face_usine=[face for face in planche.listface if face.label=="f"][0]
                Listplanches.append(planchedroite)
                Listplanches.append(planchegauche)


            else :

                i=i-1
                char=sequence[i]
                
                planches,zone=zone.envelopper(label="a",epaisseur=19.1,texture=textures["porte"])
                planche=planches[0]
                _,planche=planche.envelopper(label="g",epaisseur=3)
                _,planche=planche.envelopper(label="d",epaisseur=3)
                _,planche=planche.envelopper(label="h",epaisseur=3)
                _,planche=planche.envelopper(label="b",epaisseur=7)
                _,planche=planche.envelopper(label="a",epaisseur=1)

                planche.bloc="porteg"
                
                planche.face_usine=[face for face in planche.listface if face.label=="f"][0]
                Listplanches.append(planche)
        elif char=="A": #A marche sur le même principe que H et V mais il y a peu de cas où c'est pertinant de faire une division selon l'axe d'ouverture du meuble 
            print("A")
            i=i+1
            char=sequence[i]
            if char=="I":
                print("I")
                i=i+1
                char=sequence[i]

                if char=="L":
                    
                    mode="longueurs"
                    i=i+1
                    char=sequence[i]
                else : 
                    mode="proportions"
                

                if char=="(":
                    
                    zones=zone.couper(dir="avant")
                    meublehaut,meublebas=zones[0],zones[1]
                    seq1,seq2=subsequence(sequence[i:])
                    i=i+len(seq1)+len(seq2)+2
                    Listplanches=Listplanches+process(seq1,meublebas,textures)
                    Listplanches=Listplanches+process(seq2,meublehaut,textures)
                elif char=="[":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                    sommelen=sum([len(seq) for seq in Lseq])
                    i=i+sommelen+len(Lseq)
                    Lseq=[int(seq) for seq in Lseq]
                    zones=zone.couper(dir="avant",mode=mode , longueurs=np.array(Lseq) , prop=np.array(Lseq))
                    Lmeubles=zones

                elif char.isdigit():
                    k=int(char)
                    prop=np.ones(k)
                    zones=zone.couper(dir="avant",prop=prop)
                    Lmeubles=zones
                    i=i+1
                
                char=sequence[i]  
                if char=="(":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                    for j, seq in enumerate(Lseq)  :
                        i=i+len(seq)+1
                        Listplanches=Listplanches+process(seq,Lmeubles[j],textures)



            else : 
                epaisseur=textures["interieur"].epaisseur
                if char=="L":
                    
                    mode="longueurs"
                    i=i+1
                    char=sequence[i]
                else : 
                    mode="proportions"
                

                if char=="(":
                    
                    zones,planches=zone.cloisonner(dir="avant",epaisseur=epaisseur,texture=textures["interieur"])
                    meublehaut,meublebas,planche=zones[0],zones[1],planches[0]
                    seq1,seq2=subsequence(sequence[i:])
                    i=i+len(seq1)+len(seq2)+2
                    if epaisseur>1 :
                        Listplanches.append(planche)
                    Listplanches=Listplanches+process(seq1,meublebas,textures)
                    Listplanches=Listplanches+process(seq2,meublehaut,textures)
                elif char=="[":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                    sommelen=sum([len(seq) for seq in Lseq])
                    i=i+sommelen+len(Lseq)
                    Lseq=[int(seq) for seq in Lseq]
                    zones,planches=zone.cloisonner(dir="avant",mode=mode , longueurs=np.array(Lseq) , prop=np.array(Lseq),epaisseur=epaisseur,texture=textures["interieur"])
                    planches,Lmeubles=planches,zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                elif char.isdigit():
                    k=int(char)
                    prop=np.ones(k)
                    zones,planches=zone.cloisonner(dir="avant",prop=prop,epaisseur=epaisseur,texture=textures["interieur"])
                    planches,Lmeubles=planches,zones
                    if epaisseur>1 :
                        Listplanches=Listplanches+planches
                    i=i+1
                
                char=sequence[i]  
                if char=="(":
                    Lseq=subsequence(sequence[i:])
                    i=i+1
                    for j, seq in enumerate(Lseq)  :
                        i=i+len(seq)+1
                        Listplanches=Listplanches+process(seq,Lmeubles[j],textures)
        elif char=="S":  # S permet de faire un socle en bas du meuble il faut toujours faire un socle en bas du meuble 

            print("S")
            zones,planches=zone.cloisonner(dir="horizontale",mode="longueurs" , longueurs=np.array([50]) ,epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            planches[0].bloc="socle"
            Listplanches=Listplanches+planches
            zonebas,zone = zones
            planches,zonebas=zonebas.envelopper(label="a",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            planche=planches[0]
            planche.bloc="socle"
            Listplanches.append(planche)

            i=i+1
            char=sequence[i]
            if char == "2" :
                
                planches,zonebas=zonebas.envelopper(label="f",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
                planche=planches[0]
                planche.bloc="socle"
                Listplanches.append(planche)
            else :
                i=i-1
                char=sequence[i]
        elif char=="R": # simple retrait. Peut donner un coté esthétique. A utiliser avec parcimonie 
            print("R")
            _,zone=zone.envelopper(label="a",epaisseur=19)    
        elif char=="T": # T défini un tiroir dans la zone courante 
            print("T")
            i=i+1
            char_next=sequence[i]
            bloc_name = "tiroir"
            if char_next == "o":
                bloc_name = "tiroir_push"
            else:
                i = i - 1
            
            handle_type, i = check_handle(i)
            
            planches,zone=zone.envelopper(label="g",epaisseur=3)
            planches,zone=zone.envelopper(label="d",epaisseur=3)
            planches,zone=zone.envelopper(label="h",epaisseur=3)
            planches,zone=zone.envelopper(label="b",epaisseur=3)
            planches,zone=zone.envelopper(label="f",epaisseur=19)

            planches,zone=zone.envelopper(label="a",epaisseur=textures["tiroir"].epaisseur,texture=textures["tiroir"])
            planche=planches[0]
            planche.bloc=bloc_name
            planche.handle_type = handle_type
            Listplanches.append(planche)
        

            planches_h,zone=zone.envelopper(label="h",epaisseur=30)
            Listplanches = Listplanches + planches_h
            planches_b,zone=zone.envelopper(label="b",epaisseur=2)
            Listplanches = Listplanches + planches_b
            planches_g,zone=zone.envelopper(label="g",epaisseur=13)
            Listplanches = Listplanches + planches_g
            planches_d,zone=zone.envelopper(label="d",epaisseur=13)
            Listplanches = Listplanches + planches_d

            planches,zone=zone.envelopper(label="g",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            planche=planches[0]
            planche.bloc="tiroir"
            Listplanches.append(planche)
            planches,zone=zone.envelopper(label="d",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            planche=planches[0]
            planche.bloc="tiroir"
            Listplanches.append(planche)
            planches,zone=zone.envelopper(label="f",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            planche=planches[0]
            planche.bloc="tiroir"
            Listplanches.append(planche)
            planches,zone=zone.envelopper(label="b",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            planche=planches[0]
            planche.bloc="tiroir"
            Listplanches.append(planche)
        elif char =="r": # fait une rotation des labels de la zone d'un quart de tour
            zone.rotation()
        elif char=="h": # h planche en haut 
            print("h")
            planches,zone=zone.envelopper(label="h",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            
            Listplanches=Listplanches+planches
        elif char=="d": # d planche à droite 
            print("d")
            planches,zone=zone.envelopper(label="d",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches
        elif char=="g": # g planche à gauche 
            print("g")
            planches,zone=zone.envelopper(label="g",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches
        elif char=="b": # b planche en bas 
            print("b")
            planches,zone=zone.envelopper(label="b",epaisseur=textures["exterieur"].epaisseur,texture=textures["exterieur"])
            Listplanches=Listplanches+planches
        elif char=="a": # a planche avant (attention ne pas utiliser n'importe comment )
            print("a")
            planches,zone=zone.envelopper(label="a",epaisseur=textures["porte"].epaisseur,texture=textures["porte"])
            Listplanches=Listplanches+planches
        elif char=="C": # laisser les couleur tel quel pour le moment 
            print("C")
            i=i+1
            char=sequence[i]
            if char=="(":
                seq1,seq2,seq3,seq4=subsequence(sequence[i:])
                i=i+len(seq1)+len(seq2)+len(seq3)+len(seq4)+4
                textures={"exterieur": textures_dict[seq1],"interieur": textures_dict[seq2],"porte": textures_dict[seq3],"tiroir": textures_dict[seq4]}
        elif char=="c": # c for cable hole
            print("c")
            # Calcul du centre de la zone sur le plan du fond
            z_points = zone.points[np.unique(np.hstack([f.contour for f in zone.listface]))]
            
            # Centre horizontal (normalv)
            v_coords = z_points @ zone.normalv
            v_mid = (np.max(v_coords) + np.min(v_coords)) / 2
            
            # Bas du compartiment + 50mm (normalh)
            h_coords = z_points @ zone.normalh
            h_pos = np.min(h_coords) + 50
            
            # Profondeur (normala) -> on veut le fond (mina)
            a_coords = z_points @ zone.normala
            a_back = np.min(a_coords)
            
            point_trou = v_mid * zone.normalv + h_pos * zone.normalh + a_back * zone.normala
            
            # Rayon du passe-câble (30mm)
            rayon_trou = 30
            
            # 1. Ajouter l'alésage sur le fond pour le DXF
            for p in Listplanches:
                if getattr(p, 'type', '') == 'enveloppe_f':
                    if p.face_usine:
                        origin = p.points[p.face_usine.contour[0]]
                        u_vec = p.sens_fibres
                        normal = p.face_usine.equation[:3]
                        v_vec = np.cross(normal, u_vec)
                        rel = point_trou - origin
                        
                        new_alesage = Alesage(
                            positionxyz=point_trou,
                            positionsnu=np.array([np.dot(rel, u_vec), np.dot(rel, v_vec), 0]),
                            rayon=rayon_trou,
                            profondeur=p.epaisseur + 5,
                            face_usinage="plat",
                            couleur="blue"
                        )
                        p.face_usine.alesages.append(new_alesage)
            
            # 2. Ajouter une représentation visuelle pour le GLB (disque noir)
            disque = create_cylinder(rayon_trou*2, 2, point_trou + 1 * zone.normala, zone.normala)
            trou_visuel = Zone(None, None, None, None, None, type="cable_hole_visual", mesh=disque)
            trou_visuel.bloc = "cable_hole"
            Listplanches.append(trou_visuel)
            
        elif char=="D":
            print("D")

            points=zone.points[np.hstack([face.contour for face in zone.listface]).flatten()]

            planv=zone.normalv
            scalarsv= points@planv
            maxv=np.max(scalarsv)
            minv=np.min(scalarsv)
            
            planh=zone.normalh
            scalarsh= points@planh
            maxh=np.max(scalarsh)
            minh=np.min(scalarsh)

            plana=zone.normala
            scalarsa= points@plana
            maxa=np.max(scalarsa)
            mina=np.min(scalarsa)

            point_central= (maxa+mina)/2*plana + (minh+70)*planh + (maxv+minv)/2*planv



            cylindre = create_cylinder(20,maxv-minv,point_central,zone.normalv)
            dressing=Zone(None,None,None,None,None,type="dressing",mesh=cylindre)

            Listplanches.append(dressing)
        elif char=="v": # v for glass shelf
            print("v")
            # Une étagère en verre de 6mm d'épaisseur
            zones,planches=zone.cloisonner(dir="horizontale",mode="proportions",prop=np.array([1,1]),epaisseur=6,texture=textures["interieur"])
            # zones[0] est la zone du bas, zones[1] est la zone du haut. 
            # cloisonner renvoie zones (liste des sous-zones) et planches (liste des planches de séparation)
            if planches:
                planche=planches[0]
                planche.bloc="verre"
                Listplanches.append(planche)
                zone = zones[1] # On continue avec la zone du haut
        elif char=="p": # p for pegboard
            print("p")
            # Le pegboard est une planche de fond perforée
            planches,zone=zone.envelopper(label="f",epaisseur=textures["interieur"].epaisseur,texture=textures["interieur"])
            if planches:
                planche=planches[0]
                planche.bloc="pegboard"
                Listplanches.append(planche)
        elif char=="m" :
            planches,zone=zone.envelopper(label="d",epaisseur=10)
            planches,zone=zone.envelopper(label="g",epaisseur=10)
            planches,zone=zone.envelopper(label="f",epaisseur=10)
            planches,zone=zone.envelopper(label="h",epaisseur=10)
        elif char=="N" :
            i=i+1
            char=sequence[i]
            if char=="(":
                nom=str(subsequence(sequence[i:])[0])
                print(nom)
                zone.nom=nom
                i=i+len(nom)
                char=char=sequence[i]
        i=i+1


    return Listplanches


# %%
## execution

# Récupérer les arguments : prompt, output_path et --closed
if len(sys.argv) < 2:
    print("[ERROR] Usage: python procedure_real.py <prompt> [output_path] [--closed]", file=sys.stderr)
    sys.exit(1)

chaine = sys.argv[1]  # Le prompt M1(...)
output_path = sys.argv[2] if len(sys.argv) > 2 else "./meuble.glb"  # Chemin de sortie
closed_mode = "--closed" in sys.argv  # Mode fermé (tiroirs et portes fermés)

# Récupérer les couleurs hex si fournies (format JSON pour multi-couleurs)
custom_colors = {}
if "--colors" in sys.argv:
    colors_index = sys.argv.index("--colors")
    if colors_index + 1 < len(sys.argv):
        import json
        try:
            custom_colors = json.loads(sys.argv[colors_index + 1])
            print(f"[INFO] Couleurs personnalisées (multi): {custom_colors}")
        except json.JSONDecodeError as e:
            print(f"[WARNING] Format JSON invalide pour --colors: {e}")
            custom_colors = {}
elif "--color" in sys.argv:
    # Support legacy single color
    color_index = sys.argv.index("--color")
    if color_index + 1 < len(sys.argv):
        custom_colors = {"all": sys.argv[color_index + 1]}
        print(f"[INFO] Couleur unique: {custom_colors['all']}")

print(f"[INFO] Génération du meuble avec prompt: {chaine}")
print(f"[INFO] Fichier de sortie: {output_path}")
print(f"[INFO] Mode fermé: {closed_mode}") 

#chaine=retirer_espaces(chaine)
planches=process(chaine,1,textures) 




# %%
#enregistrement des modele 3D
# enregistre 4 models : avec sans portes et avec textures ou numéroté 

for i, planche in enumerate(planches) :
    planche.trimesh()  


for i, planche in enumerate(planches) :
    planche.texturer()

# Si des couleurs personnalisées sont fournies, remplacer les textures par des couleurs unies
if custom_colors:
    print(f"[INFO] Application des couleurs personnalisées par composant")

    def hex_to_rgba(hex_color):
        """Convertit une couleur hex en RGBA"""
        hex_color = hex_color.lstrip('#')
        if len(hex_color) == 6:
            r = int(hex_color[0:2], 16)
            g = int(hex_color[2:4], 16)
            b = int(hex_color[4:6], 16)
            return [r, g, b, 255]
        return None

    # Mapping des types de composants vers les clés de couleur
    # Types de blocs dans le code: "tiroir", "porteg", "ported", "portec", "porte_coulissante", "socle"
    # Clés frontend: "structure", "drawers", "doors", "base"

    for planche in planches:
        if not hasattr(planche, 'mesh') or planche.mesh is None:
            continue

        # Déterminer quelle couleur appliquer selon le type de bloc ou le type de zone
        color_key = None
        bloc_type = getattr(planche, 'bloc', None)
        zone_type = getattr(planche, 'type', None)

        if bloc_type == "tiroir" or bloc_type == "tiroir_push":
            color_key = "drawers"
        elif bloc_type in ["porteg", "ported", "portec", "porte_coulissante", "porteg_push"]:
            color_key = "doors"
        elif bloc_type == "miroir":
            # Appliquer un aspect miroir (argenté brillant)
            planche.mesh.visual = trimesh.visual.ColorVisuals(
                mesh=planche.mesh,
                vertex_colors=[230, 230, 235, 255]
            )
            continue
        elif bloc_type == "verre":
            # Appliquer un aspect verre (plus visible mais transparent)
            planche.mesh.visual = trimesh.visual.ColorVisuals(
                mesh=planche.mesh,
                vertex_colors=[200, 230, 255, 140] # Alpha 140
            )
            continue
        elif bloc_type == "pegboard":
            # Couleur bois perforé
            planche.mesh.visual = trimesh.visual.ColorVisuals(
                mesh=planche.mesh,
                vertex_colors=[139, 115, 85, 255]
            )
            continue
        elif bloc_type == "socle":
            color_key = "base"
        elif zone_type == "cloisonnement_horizontale":
            color_key = "shelves"
        elif zone_type == "enveloppe_f" or bloc_type == "pegboard":
            color_key = "back"
        elif bloc_type == "cable_hole":
            # Le passe-câble est toujours noir
            planche.mesh.visual = trimesh.visual.ColorVisuals(
                mesh=planche.mesh,
                vertex_colors=[20, 20, 20, 255]
            )
            continue
        else:
            # Structure (planches du corps du meuble)
            color_key = "structure"

        # Récupérer la couleur appropriée (ou "all" si couleur unique)
        hex_color = custom_colors.get(color_key) or custom_colors.get("all")

        if hex_color:
            rgb_color = hex_to_rgba(hex_color)
            if rgb_color:
                planche.mesh.visual = trimesh.visual.ColorVisuals(
                    mesh=planche.mesh,
                    vertex_colors=rgb_color
                )
                print(f"[INFO] Couleur appliquée à {color_key or 'structure'} ({bloc_type or 'planche'}): {hex_color} -> RGB{rgb_color[:3]}")
            else:
                print(f"[WARNING] Format hex invalide pour {color_key}: {hex_color}")


# La génération des fichiers (GLB et DXF) a été déplacée à la fin du script 
# pour inclure les alésages de montage et les numérotations.

# Appliquer l'ouverture des tiroirs seulement si on n'est pas en mode fermé
if not closed_mode:
    for planche in planches :
        if planche.bloc in ["tiroir", "tiroir_push"]:
            # Décalage vers l'avant (-normala car normala pointe vers le fond)
            shift = -300 * planche.normala
            planche.mesh.vertices += shift
            planche.points += shift
        elif planche.bloc in ["porteg", "ported", "porteg_push", "miroir"]:
            # Petite ouverture pour les portes
            shift = -20 * planche.normala
            planche.mesh.vertices += shift
            planche.points += shift
        # On pourrait aussi ajouter l'ouverture des portes ici si souhaité

# Génération des poignées (Désactivé à la demande de l'utilisateur)
handle_meshes = []
# for planche in planches:
#     # Déterminer le type de poignée (priorité au type spécifié, sinon défaut pour portes/tiroirs)
#     h_type = getattr(planche, 'handle_type', None)
#     
#     # Pas de poignée pour les systèmes push-to-open ou si explicitement désactivé (ex: code 0 si on en ajoutait un)
#     is_push = planche.bloc and "push" in str(planche.bloc)
#     if not h_type and not is_push and planche.bloc in ["porteg", "ported", "tiroir"]:
#         h_type = 1 # Barre verticale par défaut
#         
#     if h_type:
#         try:
#             # Trouver la face avant pour positionner la poignée
#             face_front = [f for f in planche.listface if f.label == "a"][0]
#             points_face = planche.points[face_front.contour]
#             normal = face_front.equation[:3]
#             centre_face = np.mean(points_face, axis=0)
#             
#             # Calculer vecteurs pour largeur/hauteur locale
#             # normalv est [-1, 0, 0] (gauche), normalh est [0, -1, 0] (bas)
#             u_horiz = -planche.normalv # Vers la droite
#             u_vert = -planche.normalh  # Vers le haut
#             
#             # Dimensions de la face
#             scalars_h = points_face @ u_horiz
#             scalars_v = points_face @ u_vert
#             w_face = np.max(scalars_h) - np.min(scalars_h)
#             h_face = np.max(scalars_v) - np.min(scalars_v)
#             
#             # Positionnement horizontal
#             offset_x = 0
#             if planche.bloc == "porteg": # Charnières à gauche, poignée à droite
#                 offset_x = w_face/2 - 40
#             elif planche.bloc == "ported": # Charnières à droite, poignée à gauche
#                 offset_x = -(w_face/2 - 40)
#             elif planche.bloc in ["tiroir", "tiroir_push"]:
#                 offset_x = 0
#                 
#             pos_h = centre_face + offset_x * u_horiz + 15 * normal # Devant la face
#             
#             h_mesh = None
#             if h_type == 3: # Knob
#                 h_mesh = trimesh.creation.uv_sphere(radius=15)
#                 h_mesh.apply_translation(pos_h)
#             elif h_type == 2: # Horizontal bar
#                 h_mesh = create_cylinder(16, min(w_face*0.6, 120), pos_h, u_horiz)
#             elif h_type == 4: # Recessed
#                 h_mesh = trimesh.creation.box(extents=[min(w_face*0.5, 80), 20, 5])
#                 h_mesh.apply_translation(pos_h - 12 * normal) # Un peu enfoncé
#                 h_mesh.visual = trimesh.visual.ColorVisuals(mesh=h_mesh, vertex_colors=[40, 40, 40, 255])
#             else: # Default 1: Vertical bar
#                 bar_len = min(h_face * 0.3, 300)
#                 # Orienter verticalement (le long de u_vert)
#                 h_mesh = create_cylinder(16, bar_len, pos_h, u_vert)
#             
#             if h_mesh:
#                 if h_type != 4:
#                     h_mesh.visual = trimesh.visual.ColorVisuals(mesh=h_mesh, vertex_colors=[192, 192, 192, 255])
#                 handle_meshes.append(h_mesh)
#         except Exception as e:
#             print(f"[WARNING] Erreur generation poignée sur {planche.nom}: {e}")

# Concatenation de toutes les mailles pour l'export final (Utiliser une Scene pour préserver les matériaux/transparence)
all_meshes = [planche.mesh for planche in planches if hasattr(planche, 'mesh') and planche.mesh is not None]
all_meshes.extend(handle_meshes)

# Créer une scène au lieu de concaténer pour mieux gérer la transparence et les matériaux
scene = trimesh.Scene()
for i, m in enumerate(all_meshes):
    scene.add_geometry(m, node_name=f"mesh_{i}")

# Conversion mm -> m pour le GLB
scene.apply_scale(0.001)

# Export vers le chemin spécifié par l'API
scene.export(output_path)
print(f"[INFO] Fichier GLB généré: {output_path}")

# Si on n'est pas en mode fermé, on remet les tiroirs à leur place pour la suite (calculs d'alésages etc)
if not closed_mode:
    for planche in planches :
        if planche.bloc in ["tiroir", "tiroir_push"]:
            shift = +300 * planche.normala
            planche.mesh.vertices += shift
            planche.points += shift
        elif planche.bloc in ["porteg", "ported", "porteg_push", "miroir"]:
            shift = +20 * planche.normala
            planche.mesh.vertices += shift
            planche.points += shift

# Filtrer uniquement les vraies planches pour le DXF et la suite
planches = [p for p in planches if (hasattr(p, 'planche') and p.planche) and getattr(p, 'bloc', None) != "coulisse"]

# Numéroter les planches pour le DXF
for i, p in enumerate(planches):
    p.nom = str(i + 1) # Commencer à 1 pour être plus naturel

# Grouper les planches par texture pour le DXF
groupes = sectionner_par_texture(planches)




# %%
# config des alesages

def config(planchechant,plancheplat):
    alesage_etagere_plat = 1  #à creer 
    alesage_etagere_chant = 1 #à creer 

    alesage_tourillon = Alesage(positionsnu=np.array([0,0,0]),rayon=3,profondeur=15,distance_au_coin=60,face_usinage="plat",couleur="red")
    alesage_tourillong = Alesage(positionsnu=np.array([-32,0,0]),rayon=4,profondeur=15,distance_au_coin=60,face_usinage="plat",couleur="red")
    alesage_tourillond = Alesage(positionsnu=np.array([32,0,0]),rayon=4,profondeur=15,distance_au_coin=60,face_usinage="plat",couleur="red")

    alesage_equerre_plat = Alesage(positionsnu=np.array([0,0,17.5]),rayon=2.5,profondeur=10,face_usinage="plat",couleur="red")
    alesage_equerre_chant = Alesage(positionsnu=np.array([0,-10,0]),rayon=2.5,profondeur=10,face_usinage="chant",couleur="red")

    alesage_excentrique_chant = Alesage(positionsnu=np.array([0,-34,0]),rayon=7.5,profondeur=15,distance_au_coin=60,face_usinage="chant",couleur="green")
    alesage_excentrique_plat = Alesage(positionsnu=np.array([0,0,0]),rayon=2.5,profondeur=10,distance_au_coin=60,face_usinage="plat",couleur="pink")

    alesage_porte_plat= Alesage(positionsnu=np.array([0,0,15]),rayon=17.5,profondeur=12.8,face_usinage="plat",distance_au_coin=100,couleur="blue")
    alesage_porte_chant1= Alesage(positionsnu=np.array([16,-37,0]),rayon=1.5,profondeur=5,face_usinage="chant",distance_au_coin=100, couleur="grey")
    alesage_porte_chant2= Alesage(positionsnu=np.array([-16,-37,0]),rayon=1.5,profondeur=5,face_usinage="chant",distance_au_coin=100,couleur="grey")



    if plancheplat.zone.bloc == None :
        if planchechant.zone.bloc == None :
            if planchechant.zone.type == "enveloppe_h" or planchechant.zone.type == "enveloppe_b" or planchechant.zone.type == "enveloppe_d" or planchechant.zone.type == "enveloppe_g" or planchechant.zone.type == "enveloppe_f" :
                if plancheplat.zone.type == "enveloppe_h" or plancheplat.zone.type == "enveloppe_b" or plancheplat.zone.type == "enveloppe_d" or plancheplat.zone.type == "enveloppe_g" or plancheplat.zone.type == "enveloppe_f" :
                    return [alesage_excentrique_plat,alesage_excentrique_chant,alesage_tourillond ,alesage_tourillong]
                elif plancheplat.zone.type == "cloisonnement_horizontale" or plancheplat.zone.type == "cloisonnement_verticale" or plancheplat.zone.type == "cloisonnement_avant" :
                    return []
            elif  planchechant.zone.type == "cloisonnement_verticale" :
                if plancheplat.zone.type == "enveloppe_h" or plancheplat.zone.type == "enveloppe_b" or plancheplat.zone.type == "enveloppe_d" or plancheplat.zone.type == "enveloppe_g" or plancheplat.zone.type == "enveloppe_f" or plancheplat.zone.type == "cloisonnement_horizontale"  :
                    return [alesage_excentrique_plat,alesage_excentrique_chant,alesage_tourillond ,alesage_tourillong]
            elif  planchechant.zone.type == "cloisonnement_avant" or planchechant.zone.type == "cloisonnement_horizontale" :
                return [alesage_equerre_plat,alesage_equerre_chant]
        elif planchechant.zone.bloc == "socle" and planchechant.zone.type == "cloisonnement_horizontale":
            return [alesage_excentrique_plat,alesage_excentrique_chant,alesage_tourillond ,alesage_tourillong]

            
    elif plancheplat.zone.bloc == "porteg"  :
        if planchechant.zone.type=="enveloppe_g" :
            return [alesage_porte_plat,alesage_porte_chant1,alesage_porte_chant2]
    elif plancheplat.zone.bloc == "ported"  :
        if planchechant.zone.type=="enveloppe_d" :
            return [alesage_porte_plat,alesage_porte_chant1,alesage_porte_chant2]        
    elif plancheplat.zone.bloc == "tiroir":
        if planchechant.zone.type == "enveloppe_a" or planchechant.zone.type == "enveloppe_b" or planchechant.zone.type == "enveloppe_d" or planchechant.zone.type == "enveloppe_g" or planchechant.zone.type == "enveloppe_f" :
            if plancheplat.zone.type == "enveloppe_h" or plancheplat.zone.type == "enveloppe_b" or plancheplat.zone.type == "enveloppe_d" or plancheplat.zone.type == "enveloppe_g" or plancheplat.zone.type == "enveloppe_f" or plancheplat.zone.type == "enveloppe_a":
                return [alesage_excentrique_plat,alesage_excentrique_chant,alesage_tourillond ,alesage_tourillong]
            elif plancheplat.zone.type == "cloisonnement_horizontale" or plancheplat.zone.type == "cloisonnement_verticale" or plancheplat.zone.type == "cloisonnement_avant" :
                return [alesage_tourillon]
        elif  planchechant.zone.type == "cloisonnement_verticale" :
            if plancheplat.zone.type == "enveloppe_h" or plancheplat.zone.type == "enveloppe_b" or plancheplat.zone.type == "enveloppe_d" or plancheplat.zone.type == "enveloppe_g" or plancheplat.zone.type == "enveloppe_f" :
                return [alesage_excentrique_plat,alesage_excentrique_chant,alesage_tourillond ,alesage_tourillong]
        elif  planchechant.zone.type == "cloisonnement_avant" or planchechant.zone.type == "cloisonnement_horizontale" :
            return [alesage_tourillon]




# %%
#coupes biaises détection des coupes biaies 
for planche in planches :
    normale=planche.plan[:3]
    for face in planche.listface :
        if face.chant :
            if abs(np.dot(normale,face.equation[:3]))>0.01:
                planche.biseau=abs(np.arccos(np.dot(normale,face.equation[:3]))*180/np.pi-90)




# %%
#placement par doublet 


faces = [face for planche in planches for face in planche.listface]

doublets = [
    (face1, face2)
    for i, face1 in enumerate(faces)
    for j, face2 in enumerate(faces)
    ]

doublet_contact=[]

for doublet in doublets : # creer le graph de connexité des planches 
    face = doublet[0]
    faceoppose = doublet[1]
    if face.remonter_facesupport() == faceoppose.remonter_facesupport().faceoppose :  #les faces sont en contact
        if faceoppose.chant and not face.chant : # Les faces sont chant et non chants
            if np.abs(np.dot(faceoppose.equation[:3],faceoppose.zone.face_usine.equation[:3]))<0.05 : # orthogonalité 
                doublet_contact.append(doublet) #doublet : [plat chant]
print(doublet_contact)

for doublet in doublet_contact : # place les alésages en fonction de la configuration 

    face = doublet[0]
    faceoppose = doublet[1]

    alesages = config(faceoppose,face)
    
    n= faceoppose.equation[:3]
    u= faceoppose.zone.face_usine.equation[:3]
    s= np.cross(n,u)
    M = np.column_stack((s, n, u))


    if alesages is None :
        1==1
    else : 
        for alesage in alesages :
            try : 

                # on commence par reconstituer le segment de contact
                distance_au_coin = alesage.distance_au_coin
                planche=faceoppose.zone
                segments = faceoppose.segments()
                points = planche.points
                plan= planche.plan
                segmentsreels = points[segments]
                new_segment=[]
                for segment in segmentsreels :
                    boolean=slice(segment,plan)
                    
                    if np.all(boolean) or np.all(~boolean):
                        pass
                    else :
                        new_segment.append((segment[0]+segment[1])/2)
            
                if len(new_segment) < 2:
                    print(f"[WARNING] Segment de contact trop court ou invalide pour les alésages sur {planche.nom}")
                    continue

                vect = new_segment[0]-new_segment[1]
                l = np.linalg.norm(vect)
                if l > 200 : 
                    centres = [new_segment[0] - vect/l*distance_au_coin,new_segment[1] + vect/l*distance_au_coin]
                else :
                    centres = [(new_segment[0]+new_segment[1])/2]
                
                #ajout des différents alésages 
                print(centres)
                print([alesage.rayon for alesage in alesages])
                for centre in centres :
                    alesagecopy=deepcopy(alesage)
                    alesagecopy.positionxyz = centre +  M @ alesagecopy.positionsnu
                    if alesagecopy.face_usinage == "chant" :
                        faceoppose.alesages.append(alesagecopy)
                    elif alesagecopy.face_usinage == "plat" :
                        face.alesages.append(alesagecopy)

            except Exception as e :
                print("error " , e) 


# %%
# séparartion des matériaux (cette section n'est plus utilisée car déplacée avant sys.exit)

planchescopy=deepcopy(planches)




# %%
# génération du dxf
doc = ezdxf.new()
# Définir explicitement que le dessin utilise des millimètres comme unité
doc.header['$INSUNITS'] = 4  # 4 = millimètres
doc.header['$MEASUREMENT'] = 1  # 1 = métrique
doc.header['$LUNITS'] = 2  # 2 = décimal

doc.layers.add("contour_haut", color=1)  
doc.layers.add("contour_bas", color=2) 
doc.layers.add("texte", color=9)  # Création d'une nouvelle couche pour le texte
msp = doc.modelspace()

marge = 50  # marge en mm
X = 0
Y = 0

diameter_layers = {}  # Dictionnaire pour stocker les layers créés
toutes_facades = []  # Collecter toutes les façades pour les placer ensemble en haut à droite
X_max_global = 0  # Suivre le X maximum de toutes les planches normales

for planches_index, planches in enumerate(groupes):

    # Séparer les façades (portes, tiroirs, miroirs) des autres planches
    facades_types = [
        "porteg", "ported", "portec", "porte_coulissante", "porteg_push",  # Portes
        "tiroir", "tiroir_push",  # Tiroirs (normaux et push-to-open)
        "miroir", "verre"  # Miroirs et vitres
    ]
    planches_normales = [p for p in planches if getattr(p, 'bloc', None) not in facades_types]
    planches_facades = [p for p in planches if getattr(p, 'bloc', None) in facades_types]

    # DEBUG: Afficher le nombre de planches dans chaque catégorie
    print(f"[DXF DEBUG Groupe {planches_index+1}] Total planches: {len(planches)}")
    print(f"[DXF DEBUG Groupe {planches_index+1}] Planches normales: {len(planches_normales)}")
    print(f"[DXF DEBUG Groupe {planches_index+1}] Façades: {len(planches_facades)}")
    if planches_facades:
        print(f"[DXF DEBUG Groupe {planches_index+1}] Types de façades: {[p.bloc for p in planches_facades]}")

    # Collecter les façades pour les placer ensemble plus tard
    for facade in planches_facades:
        toutes_facades.append((planches_index, facade))

    # Traiter d'abord les planches normales (à gauche)
    X_max_normales = 0
    for i, planche in enumerate(planches_normales):

        # Projeter les points et rester en mm
        projection = project_points_on_plane(planche.points,
                                            planche.points[planche.face_usine.contour[0]],
                                            np.cross(planche.sens_fibres,planche.face_usine.equation[:3]),
                                            planche.sens_fibres)
        # projection = projection / 1000  # Suppression de la conversion en m

        xmax = np.max(projection[:, 0])
        xmin = np.min(projection[:, 0])

        ymax = np.max(projection[:, 1])
        ymin = np.min(projection[:, 1])

        projection[:, 0] = projection[:, 0] - xmin + X
        projection[:, 1] = projection[:, 1] - ymin + Y

        for face in planche.listface:
            if planche.biseau:
                print(planche.biseau)
                if not face.chant:
                    if face == planche.face_usine:
                        contour_2d = projection[face.contour]
                        msp.add_lwpolyline(
                            points=contour_2d,
                            close=True,
                            dxfattribs={"layer": "contour_haut"},
                        )
                        
                        # Ajout de texte pour ce contour
                        texte = planche.nom+f"G{planches_index+1}"+"\n biseau"+str(np.round(planche.biseau))
                        # Calculer le centre du contour pour placer le texte
                        x_center = np.min(contour_2d[:, 0])
                        y_center = (np.max(contour_2d[:, 1]) + np.min(contour_2d[:, 1])) / 2
                        msp.add_text(
                            texte,
                            dxfattribs={
                                "layer": "texte",
                                "height": 15,  # hauteur du texte en mm
                                "style": "Standard",
                                "insert": (x_center, y_center)
                            }
                        )
                    else :
                        contour_2d = projection[face.contour]
                        msp.add_lwpolyline(
                            points=contour_2d,
                            close=True,
                            dxfattribs={"layer": "contour_bas"},
                        )

                
            else:
                if face == planche.face_usine: # ajouter les deux face non chant en cas de coupe biaise 
                    contour_2d = projection[face.contour]
                    msp.add_lwpolyline(
                        points=contour_2d,
                        close=True,
                        dxfattribs={"layer": "contour_haut"},
                    )
                    
                    # Ajout de texte pour ce contour
                    texte = planche.nom+f"G{planches_index+1}"
                    # Calculer le centre du contour pour placer le texte
                    x_center = np.min(contour_2d[:, 0])
                    y_center = (np.max(contour_2d[:, 1]) + np.min(contour_2d[:, 1])) / 2
                    msp.add_text(
                        texte,
                        dxfattribs={
                            "layer": "texte",
                            "height": 15,  # hauteur du texte en mm
                            "style": "Standard",
                            "insert": (x_center, y_center)
                        }
                    )

            for alesage in face.alesages:
                # Projeter le centre de l'alésage
                projectioncentre = project_points_on_plane(alesage.positionxyz,
                                                          planche.points[planche.face_usine.contour[0]],
                                                          np.cross(planche.sens_fibres,planche.face_usine.equation[:3]), 
                                                          planche.sens_fibres)
                # projectioncentre = projectioncentre / 1000  # Suppression de la conversion en m
                projectioncentre[:, 0] = projectioncentre[:, 0] - xmin + X
                projectioncentre[:, 1] = projectioncentre[:, 1] - ymin + Y

                # Définir le nom de couche avec unité mm
                radius_mm = alesage.rayon 
                diameter_mm = round(2 * radius_mm, 1) 
                layer_name = f"diam_{diameter_mm}mm" 

                # Ajouter le layer si ce diamètre n'a pas encore de couche
                if layer_name not in diameter_layers:
                    doc.layers.add(layer_name, color=len(diameter_layers) + 2)  # Assigner une couleur différente
                    diameter_layers[layer_name] = True  # Marquer comme ajouté

                # Ajouter le cercle au bon layer
                msp.add_circle(
                    center=(projectioncentre[0, 0], projectioncentre[0, 1]),
                    radius=radius_mm,
                    dxfattribs={"layer": layer_name},
                )

        X = X + marge + xmax - xmin
        X_max_normales = max(X_max_normales, X)

    # Mettre à jour le X maximum global
    X_max_global = max(X_max_global, X_max_normales)

    # Les façades seront placées après la boucle principale
    Y = Y + 1000  # Décalage de 1000 mm (1m) pour le groupe suivant
    X = 0 # Réinitialiser X pour le nouveau groupe

# Placer toutes les façades ensemble en haut à droite
print(f"[DXF DEBUG] Placement de {len(toutes_facades)} façades en haut à droite")
Y_facades = 0  # En haut du DXF
X_facades = X_max_global + 500  # À droite des planches normales, avec marge de 500mm

for planches_index, planche in toutes_facades:

    # Projeter les points et rester en mm
    projection = project_points_on_plane(planche.points,
                                        planche.points[planche.face_usine.contour[0]],
                                        np.cross(planche.sens_fibres,planche.face_usine.equation[:3]),
                                        planche.sens_fibres)

    xmax = np.max(projection[:, 0])
    xmin = np.min(projection[:, 0])

    ymax = np.max(projection[:, 1])
    ymin = np.min(projection[:, 1])

    projection[:, 0] = projection[:, 0] - xmin + X_facades
    projection[:, 1] = projection[:, 1] - ymin + Y_facades

    for face in planche.listface:
        if planche.biseau:
            print(planche.biseau)
            if not face.chant:
                if face == planche.face_usine:
                    contour_2d = projection[face.contour]
                    msp.add_lwpolyline(
                        points=contour_2d,
                        close=True,
                        dxfattribs={"layer": "contour_haut"},
                    )

                    # Ajout de texte pour ce contour
                    texte = planche.nom+f"G{planches_index+1}"+"\n biseau"+str(np.round(planche.biseau))
                    # Calculer le centre du contour pour placer le texte
                    x_center = np.min(contour_2d[:, 0])
                    y_center = (np.max(contour_2d[:, 1]) + np.min(contour_2d[:, 1])) / 2
                    msp.add_text(
                        texte,
                        dxfattribs={
                            "layer": "texte",
                            "height": 15,  # hauteur du texte en mm
                            "style": "Standard",
                            "insert": (x_center, y_center)
                        }
                    )
                else :
                    contour_2d = projection[face.contour]
                    msp.add_lwpolyline(
                        points=contour_2d,
                        close=True,
                        dxfattribs={"layer": "contour_bas"},
                    )


        else:
            if face == planche.face_usine: # ajouter les deux face non chant en cas de coupe biaise
                contour_2d = projection[face.contour]
                msp.add_lwpolyline(
                    points=contour_2d,
                    close=True,
                    dxfattribs={"layer": "contour_haut"},
                )

                # Ajout de texte pour ce contour
                texte = planche.nom+f"G{planches_index+1}"
                # Calculer le centre du contour pour placer le texte
                x_center = np.min(contour_2d[:, 0])
                y_center = (np.max(contour_2d[:, 1]) + np.min(contour_2d[:, 1])) / 2
                msp.add_text(
                    texte,
                    dxfattribs={
                        "layer": "texte",
                        "height": 15,  # hauteur du texte en mm
                        "style": "Standard",
                        "insert": (x_center, y_center)
                    }
                )

        for alesage in face.alesages:
            # Projeter le centre de l'alésage
            projectioncentre = project_points_on_plane(alesage.positionxyz,
                                                      planche.points[planche.face_usine.contour[0]],
                                                      np.cross(planche.sens_fibres,planche.face_usine.equation[:3]),
                                                      planche.sens_fibres)
            projectioncentre[:, 0] = projectioncentre[:, 0] - xmin + X_facades
            projectioncentre[:, 1] = projectioncentre[:, 1] - ymin + Y_facades

            # Définir le nom de couche avec unité mm
            radius_mm = alesage.rayon
            diameter_mm = round(2 * radius_mm, 1)
            layer_name = f"diam_{diameter_mm}mm"

            # Ajouter le layer si ce diamètre n'a pas encore de couche
            if layer_name not in diameter_layers:
                doc.layers.add(layer_name, color=len(diameter_layers) + 2)
                diameter_layers[layer_name] = True

            # Ajouter le cercle au bon layer
            msp.add_circle(
                center=(projectioncentre[0, 0], projectioncentre[0, 1]),
                radius=radius_mm,
                dxfattribs={"layer": layer_name},
            )

    X_facades = X_facades + marge + xmax - xmin

# Ajouter des métadonnées pour clarifier les unités
doc.header['$MENU'] = "Toutes les unités sont en millimètres"
doc.header['$INSUNITS'] = 4 # 4 = Millimètres dans la norme DXF

# Générer le nom du fichier DXF basé sur le nom du fichier GLB
dxf_filename = os.path.splitext(os.path.basename(output_path))[0] + ".dxf"
dxf_output_dir = os.path.dirname(output_path)
dxf_output_path = os.path.join(dxf_output_dir, dxf_filename)

# Sauvegarder aussi dans pieces/ pour compatibilité avec l'ancien code
pieces_dir = os.path.join(script_dir, "pieces")
os.makedirs(pieces_dir, exist_ok=True)
doc.saveas(os.path.join(pieces_dir, "piece_general.dxf"))

# Sauvegarder le DXF unique avec le même nom que le GLB
doc.saveas(dxf_output_path)
print(f"[INFO] Fichier DXF généré: {dxf_output_path}")

# S'assurer que le fichier est bien écrit sur le disque avant de quitter
try:
    if os.path.exists(dxf_output_path):
        # Optionnel: On peut aussi copier le fichier dans un endroit générique si besoin
        # Mais l'API cherche maintenant le fichier spécifique
        pass
except:
    pass

# On arrête le script ici pour éviter les générations SVG/DXF redondantes qui écrasent tout
sys.exit(0)

# %%
#generation de SVG

def generate_svg(groupes):
    i="general"
    dwg = svgwrite.Drawing(os.path.join(pieces_dir, f"piece_{i}.svg"), profile='tiny')
    # Définir les calques en utilisant des groupes SVG

    panneau_group = dwg.add(dwg.g(id=f'panneau', fill='none'))
    panneau_group.add(dwg.rect(size=(200,280),  stroke='blue',fill='none',stroke_width=1))

    x=200
    y=0
    marge = 2
    for planches in groupes:
        for i,planche in enumerate(planches) :
            planche_group = dwg.add(dwg.g(id=f'planche_{i}', fill='none'))
            projection = project_points_on_plane(planche.points ,planche.points[planche.face_usine.contour[0]],np.cross(planche.sens_fibres,planche.face_usine.equation[:3]), planche.sens_fibres)/10

            xmax, xmin = np.max(projection[:, 0]), np.min(projection[:, 0])
            ymax, ymin = np.max(projection[:, 1]), np.min(projection[:, 1])
            # Décalage pour positionner correctement la planche
            projection[:, 0] -= xmin-1
            projection[:, 0] = projection[:, 0] + x 
            projection[:, 1] -= ymin - 1 + y

            for face in planche.listface:
                if planche.biseau :
                    if not face.chant :
                        contour_2d = projection[face.contour]
                        points = [(float(x), float(y)) for x, y in contour_2d]
                        planche_group.add(dwg.polyline(points=points + [points[0]], stroke = "black", stroke_width=1, fill='none'))
                else :
                    if face == planche.face_usine:
                        contour_2d = projection[face.contour]
                        points = [(float(x), float(y)) for x, y in contour_2d]
                        planche_group.add(dwg.polyline(points=points + [points[0]], stroke = "black", stroke_width=1, fill='none'))

                for alesage in face.alesages:
                    projectioncentre = project_points_on_plane(alesage.positionxyz,planche.points[planche.face_usine.contour[0]],np.cross(planche.sens_fibres,planche.face_usine.equation[:3]), planche.sens_fibres)
                    projectioncentre =projectioncentre/10
                    projectioncentre[:, 0] -= xmin-1
                    projectioncentre[:, 0] = projectioncentre[:, 0] + x 
                    projectioncentre[:, 1] -= ymin - 1 + y
                    planche_group.add(dwg.circle(center=(projectioncentre[0, 0], projectioncentre[0, 1]),r=(alesage.rayon/10), stroke = alesage.couleur,stroke_width=1))

            x=x+xmax-xmin + marge 
        y=y-400
    #dwg.viewbox(-10,-10,x,7000)
    dwg.save()

generate_svg(groupes)


# %%
# svg to dxf 

import ezdxf
from svgpathtools import svg2paths

def svg_to_dxf(svg_file, dxf_file):
    # Charger les chemins à partir du fichier SVG
    paths, attributes = svg2paths(svg_file)

    # Créer un nouveau document DXF
    doc = ezdxf.new()
    msp = doc.modelspace()

    # Convertir chaque chemin SVG en polyligne DXF
    for path in paths:
        points = [(segment.start.real, segment.start.imag) for segment in path]
        if points:
            # Assurez-vous que le contour est fermé
            points.append(points[0])
            msp.add_lwpolyline(points, close=True)

    # Sauvegarder le fichier DXF
    doc.saveas(dxf_file)

# Exemple d'utilisation
svg_to_dxf('pieces/piece_general.svg', 'pieces/conversion.dxf')



# %%
#Calcul de prix 
planches=planchescopy
prix = 0 

prix_percage = 0.52 #PU
prix_chant = 2.15 #euro par ml
prix_paletisation = 55 #PU
prix_decoupe = 1.15 # euro par mL

prix_usine=0
prix_quincaille = 0 
prix_bois=0
prix_transport=150

prix_metrage = 100

for i, planche in enumerate(planches) :

    prix_usine = (planche.perimetre()/1000)*(prix_chant+prix_decoupe) + prix_usine
    prix_usine = len(planche.face_usine.alesages)*prix_percage + prix_usine

    prix_bois = planche.prix() + prix_bois

    if planche.bloc == "tiroir" :
        prix_quincaille += 15
    elif planche.bloc == "porteg" or planche.bloc == "ported" :
        prix_quincaille += 50

prix_usine=prix_usine+prix_paletisation

prix_montage = (prix_usine+prix_bois)*0.5



cout =  prix_usine+prix_transport+prix_bois+prix_quincaille + prix_montage + prix_metrage



def lire_facteur_prix(fichier="facteur_prix.txt"):
    try:
        with open(fichier, "r") as f:
            return float(f.read().strip())
    except (FileNotFoundError, ValueError):
        return 2  # Valeur par défaut si le fichier n'existe pas ou contient une valeur incorrecte

# Exemple d'utilisation
facteur = lire_facteur_prix()

prix = cout * facteur


# %%
##JSON
print("prix du meuble :",prix)

meublejson={}
meublejson["Description"]="meuble sur mesure"
meublejson["prixht"]=prix
meublejson["prixttc"]=np.round(prix*1.2,decimals=2)
meublejson["chaine"]=chaine
meublejson["sous_produit"]=[]

sousproduit={}
sousproduit["description"]="Conception"
sousproduit["prixht"]=prix_metrage*facteur
sousproduit["prixttc"]=sousproduit["prixht"]*1.2
meublejson["sous_produit"].append(sousproduit)

sousproduit={}
sousproduit["description"]="Approvisionnement Bois"
sousproduit["prixht"]=prix_bois*facteur
sousproduit["prixttc"]=sousproduit["prixht"]*1.2
meublejson["sous_produit"].append(sousproduit)

sousproduit={}
sousproduit["description"]="Quincaillerie"
sousproduit["prixht"]=prix_quincaille*facteur
sousproduit["prixttc"]=sousproduit["prixht"]*1.2
meublejson["sous_produit"].append(sousproduit)

sousproduit={}
sousproduit["description"]="Decoupe"
sousproduit["prixht"]=prix_usine*facteur
sousproduit["prixttc"]=sousproduit["prixht"]*1.2
meublejson["sous_produit"].append(sousproduit)

sousproduit={}
sousproduit["description"]="Transport"
sousproduit["prixht"]=prix_transport*facteur
sousproduit["prixttc"]=sousproduit["prixht"]*1.2
meublejson["sous_produit"].append(sousproduit)

sousproduit={}
sousproduit["description"]="Pose"
sousproduit["prixht"]=prix_montage*facteur
sousproduit["prixttc"]=sousproduit["prixht"]*1.2
meublejson["sous_produit"].append(sousproduit)

with open("produit.json", "w") as json_file:
    json.dump(meublejson, json_file, indent=4)
 # Write JSON to file

print(meublejson)



