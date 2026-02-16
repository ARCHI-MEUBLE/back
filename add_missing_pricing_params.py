#!/usr/bin/env python3
"""
Script pour ajouter les paramètres de prix manquants (penderie, poignées, etc.)
"""

import sqlite3
import os

# Chemin vers la base de données
DB_PATH = os.path.join(os.path.dirname(__file__), 'archimeuble.db')

def add_missing_params():
    """Ajoute les paramètres manquants pour penderie, poignées et autres."""

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    print("Ajout des paramètres de prix manquants...")

    # Liste des paramètres à ajouter
    params_to_add = [
        # PENDERIE
        ('wardrobe', 'rod', 'price_per_linear_meter', 20, 'eur_linear_m', 'Prix de la barre de penderie par mètre linéaire'),

        # POIGNÉES
        ('handles', 'horizontal_bar', 'price_per_unit', 15, 'eur', "Prix d'une poignée barre horizontale"),
        ('handles', 'vertical_bar', 'price_per_unit', 15, 'eur', "Prix d'une poignée barre verticale"),
        ('handles', 'knob', 'price_per_unit', 10, 'eur', "Prix d'un bouton de porte"),
        ('handles', 'recessed', 'price_per_unit', 20, 'eur', "Prix d'une poignée encastrée"),

        # SOCLE BOIS - paramètres complémentaires
        ('bases', 'wood', 'price_per_m3', 800, 'eur_m3', 'Prix du bois pour socle au m³'),
        ('bases', 'wood', 'height', 80, 'mm', 'Hauteur fixe du socle bois'),
        ('bases', 'metal', 'base_foot_count', 2, 'units', 'Nombre minimum de pieds (base)'),

        # AFFICHAGE PRIX
        ('display', 'price', 'display_mode', 0, 'units', "Mode d'affichage (0=Direct, 1=Intervalle)"),
        ('display', 'price', 'deviation_range', 100, 'eur', "Écart pour l'affichage en intervalle"),
    ]

    added_count = 0
    skipped_count = 0

    for category, item_type, param_name, param_value, unit, description in params_to_add:
        # Vérifier si le paramètre existe déjà
        cursor.execute("""
            SELECT id FROM pricing_config
            WHERE category = ? AND item_type = ? AND param_name = ?
        """, (category, item_type, param_name))

        if cursor.fetchone() is None:
            # Ajouter le paramètre
            cursor.execute("""
                INSERT INTO pricing_config (category, item_type, param_name, param_value, unit, description, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            """, (category, item_type, param_name, param_value, unit, description))
            print(f"  + Ajouté: {category}/{item_type}/{param_name} = {param_value} {unit}")
            added_count += 1
        else:
            print(f"  - Ignoré (existe déjà): {category}/{item_type}/{param_name}")
            skipped_count += 1

    conn.commit()
    conn.close()

    print(f"\nRésumé:")
    print(f"  - Paramètres ajoutés: {added_count}")
    print(f"  - Paramètres ignorés (déjà existants): {skipped_count}")
    print("\nTerminé!")

if __name__ == '__main__':
    add_missing_params()
