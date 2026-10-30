#!/usr/bin/env python3
"""
ArchiMeuble - Script Python de génération 3D
Ce script est un MOCK temporaire pour tester l'API generate.php
Il crée un fichier GLB vide pour simuler la génération.

Le vrai script sera créé plus tard avec la vraie logique de génération 3D.

Auteur: Ilyes (script de test)
Date: 2025-10-20
"""

import sys
import os

def main():
    # Vérifier les arguments
    if len(sys.argv) != 3:
        print("Usage: python procedure.py <prompt> <output_path>", file=sys.stderr)
        sys.exit(1)

    prompt = sys.argv[1]
    output_path = sys.argv[2]

    print(f"[MOCK] Génération du meuble avec le prompt: {prompt}")
    print(f"[MOCK] Fichier de sortie: {output_path}")

    # Créer le dossier parent si nécessaire
    output_dir = os.path.dirname(output_path)
    if output_dir and not os.path.exists(output_dir):
        os.makedirs(output_dir, exist_ok=True)
        print(f"[MOCK] Dossier créé: {output_dir}")

    # Créer un fichier GLB de test (vide pour l'instant)
    # Dans la vraie version, ici il y aura la génération 3D
    with open(output_path, 'wb') as f:
        # Écrire un header GLB minimal (magic number + version)
        # glTF binary header: b'glTF' + version (2) + length + JSON chunk
        f.write(b'glTF')  # Magic
        f.write(b'\x02\x00\x00\x00')  # Version 2
        f.write(b'\x00\x00\x00\x00')  # Total length (placeholder)
        print(f"[MOCK] Fichier GLB créé: {output_path}")

    # Vérifier que le fichier a été créé
    if os.path.exists(output_path):
        file_size = os.path.getsize(output_path)
        print(f"[MOCK] OK Fichier genere avec succes ({file_size} octets)")
        print(f"[MOCK] OK Prompt traite: {prompt}")
        return 0
    else:
        print(f"[MOCK] ERREUR: le fichier n'a pas ete cree", file=sys.stderr)
        return 1

if __name__ == "__main__":
    sys.exit(main())
