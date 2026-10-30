"""Pont d'identifiants entre le frontend (configurateur) et le backend.

Le frontend identifie un panneau par une chaîne du type "panel-top-0"
(type-index de segment). Le backend, lui, crée un seul objet physique
par type de panneau. Ces fonctions traduisent l'un vers l'autre pour
savoir quel panneau supprimer du DXF quand l'utilisateur en retire un
dans le configurateur.
"""


def count_segments_from_zones(zones, panel_type):
    """
    Analyse la structure des zones pour déterminer le nombre de segments pour un type de panneau.
    - top/bottom: nombre de colonnes uniques (divisions verticales au premier niveau)
    - left/right: nombre de rangées uniques (divisions horizontales au premier niveau)
    """
    if not zones:
        return 1

    # Pour top/bottom, on compte les colonnes (divisions verticales au niveau supérieur)
    if panel_type in ['top', 'bottom']:
        if zones.get('type') == 'vertical' and zones.get('children'):
            return len(zones['children'])
        # Si le premier niveau est horizontal avec des enfants qui sont verticaux
        if zones.get('type') == 'horizontal' and zones.get('children'):
            # Vérifier le premier enfant (le plus haut, qui touche le top)
            first_child = zones['children'][0]
            if first_child.get('type') == 'vertical' and first_child.get('children'):
                return len(first_child['children'])

    # Pour left/right, on compte les rangées (divisions horizontales au niveau supérieur)
    if panel_type in ['left', 'right']:
        if zones.get('type') == 'horizontal' and zones.get('children'):
            return len(zones['children'])
        # Si le premier niveau est vertical avec des enfants qui sont horizontaux
        if zones.get('type') == 'vertical' and zones.get('children'):
            # Vérifier le premier/dernier enfant selon left/right
            child_idx = 0 if panel_type == 'left' else -1
            child = zones['children'][child_idx]
            if child.get('type') == 'horizontal' and child.get('children'):
                return len(child['children'])

    return 1

# Helper function: convertir un ID frontend en ID backend
def convert_frontend_id_to_backend(frontend_id, zones_structure):
    """
    Convertit un ID frontend (ex: 'panel-top-0') en ID backend (ex: 'top-0-0').
    Retourne aussi le type de panneau et l'index du segment.
    """
    # Enlever le préfixe 'panel-' si présent
    if frontend_id.startswith('panel-'):
        clean_id = frontend_id[6:]  # Enlève 'panel-'
    else:
        clean_id = frontend_id

    # Séparer le type et l'index
    parts = clean_id.split('-')
    if len(parts) >= 2:
        panel_type = parts[0]  # 'top', 'bottom', 'left', 'right', 'back'
        segment_index = int(parts[1]) if parts[1].isdigit() else 0

        # Déterminer le nombre total de segments
        num_segments = count_segments_from_zones(zones_structure, panel_type)

        return {
            'panel_type': panel_type,
            'segment_index': segment_index,
            'num_segments': num_segments,
            'backend_id': f"{panel_type}-0-0"  # ID backend standard
        }

    return None
