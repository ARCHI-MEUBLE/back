/**
 * ArchiMeuble - 3D Viewer JavaScript
 * Gère l'affichage et la manipulation du model-viewer
 * Auteur: Stephen
 */

/**
 * Met à jour le modèle 3D affiché
 * @param {string} glbUrl - URL du fichier GLB à afficher
 */
function updateModel(glbUrl) {
    const viewer = document.getElementById('viewer3d');
    const loadingOverlay = document.getElementById('loading-overlay');

    if (!viewer) {
        console.error('Model viewer non trouvé');
        return;
    }

    // Afficher le loading
    showLoading();

    // Mettre à jour le src du model-viewer
    viewer.setAttribute('src', glbUrl);

    // Écouter l'événement de chargement
    viewer.addEventListener('load', function onLoad() {
        hideLoading();
        viewer.removeEventListener('load', onLoad);
    });

    // Gérer les erreurs de chargement
    viewer.addEventListener('error', function onError(event) {
        console.error('Erreur lors du chargement du modèle 3D:', event);
        hideLoading();
        showError('Impossible de charger le modèle 3D');
        viewer.removeEventListener('error', onError);
    });
}

/**
 * Affiche l'overlay de chargement
 */
function showLoading() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('hidden');
    }
}

/**
 * Masque l'overlay de chargement
 */
function hideLoading() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
}

/**
 * Affiche un message d'erreur dans le viewer
 * @param {string} message - Message d'erreur à afficher
 */
function showError(message) {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
                <p style="color: #E55A2B; font-weight: 600;">${message}</p>
                <p style="font-size: 14px; color: #6B6B6B; margin-top: 8px;">
                    Veuillez réessayer ou contacter le support
                </p>
            </div>
        `;
        loadingOverlay.classList.remove('hidden');
    }
}

/**
 * Charge un modèle par défaut (optionnel)
 */
function loadDefaultModel() {
    // Si un modèle GLB par défaut existe, le charger
    const defaultModelUrl = '/frontend/assets/models/default.glb';

    // Vérifier si le fichier existe avant de le charger
    fetch(defaultModelUrl, { method: 'HEAD' })
        .then(response => {
            if (response.ok) {
                updateModel(defaultModelUrl);
            } else {
                // Pas de modèle par défaut, afficher un message
                hideLoading();
            }
        })
        .catch(() => {
            // Ignorer l'erreur, pas de modèle par défaut
            hideLoading();
        });
}

/**
 * Initialise le viewer au chargement de la page
 */
function initViewer() {
    const viewer = document.getElementById('viewer3d');

    if (!viewer) {
        console.warn('Model viewer non trouvé sur cette page');
        return;
    }

    // Configuration par défaut du viewer
    viewer.setAttribute('camera-controls', '');
    viewer.setAttribute('auto-rotate', '');
    viewer.setAttribute('shadow-intensity', '1');

    // Essayer de charger un modèle par défaut
    loadDefaultModel();
}

// Exporter les fonctions pour qu'elles soient accessibles globalement
window.updateModel = updateModel;
window.showLoading = showLoading;
window.hideLoading = hideLoading;

// Initialiser le viewer au chargement du DOM
document.addEventListener('DOMContentLoaded', initViewer);
