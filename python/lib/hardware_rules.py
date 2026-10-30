"""Règles de placement de la quincaillerie (alésages) entre deux planches.

config(planche_chant, planche_plat) regarde les types de zone des deux
planches en contact (enveloppe, cloisonnement, porte, tiroir...) et
retourne la liste des Alesage à percer à leur jonction (tourillons,
charnières excentriques, équerres...).
"""

import numpy as np

from lib.geometry_model import Alesage


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
