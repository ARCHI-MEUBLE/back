"""Modèle géométrique du meuble : Alesage, Face, Zone.

Zone est la classe centrale : elle représente un volume qui peut se
découper (clip/couper/cloisonner) pour produire les planches du meuble,
générer leur maillage 3D (trimesh) et leur texture.

Note d'implémentation : Zone.texturer() et Zone.prix() ont besoin du
catalogue de textures (textures_dict) et Zone.texturer() a aussi besoin
du dossier du script (script_dir) pour retrouver les images de texture
sur disque. Ces deux valeurs ne sont connues qu'au lancement du script
(elles dépendent du catalogue chargé et de l'emplacement d'installation),
donc procedure_real.py les injecte dans ce module juste après l'import :

    import lib.geometry_model as geometry_model
    geometry_model.textures_dict = textures_dict
    geometry_model.script_dir = script_dir

Elles doivent être renseignées avant toute création de Zone.
"""

import os
from copy import deepcopy

import numpy as np
import pyvista as pv
import trimesh
from PIL import Image

from lib.geometry_utils import (
    create_number_image,
    line_plane_intersection,
    perpendicular_unit_vectors,
    reconstruct_contour,
    slice,
)

# Injectés par procedure_real.py après le chargement du catalogue (voir docstring ci-dessus)
textures_dict = None
script_dir = None


class Alesage:  # l'objet alésage défini en totalité les caractéistique d'un alésage dans une planche
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
