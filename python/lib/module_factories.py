"""Fabriques de zones de base (M0 à M5) et parsing de la chaîne de config.

M0/M4/M5 construisent une Zone à partir de dimensions brutes (points 3D
positionnés à la main puis assemblés en faces). M1/M2/M3 sont de simples
raccourcis vers M0 avec des arguments dupliqués.

subsequence() est l'autre brique utilisée par le parseur (process()) :
elle découpe une chaîne du type "a,b,(c,d)" en ses sous-parties de
premier niveau, en respectant les parenthèses/crochets imbriqués.
"""

import numpy as np

from lib.geometry_model import Face, Zone
from lib.geometry_utils import plane_equation


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
