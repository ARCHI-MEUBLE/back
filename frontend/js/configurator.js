/**
 * ArchiMeuble - Configurator JavaScript
 * Gère les interactions avec les contrôles du configurateur
 * NOTE: Ce fichier contient la logique UI de base.
 * Kenneth ajoutera la logique de génération de prompt et calcul de prix.
 * Auteur: Stephen
 */

// État de la configuration (sera utilisé par Kenneth pour générer le prompt)
let currentConfig = {
    modules: 1,
    height: 730,
    depth: 320,
    socle: 'metal',
    fond: false,
    finition: 'mat',
    color: '#FFFFFF'
};

/**
 * Initialise tous les contrôles du configurateur
 */
function initConfiguratorControls() {
    // Boutons modules
    initModulesButtons();

    // Slider hauteur
    initHeightSlider();

    // Boutons profondeur
    initDepthButtons();

    // Select socle
    initSocleSelect();

    // Checkbox fond
    initFondCheckbox();

    // Boutons finition
    initFinitionButtons();

    // Color picker
    initColorPicker();

    // Boutons actions
    initActionButtons();
}

/**
 * Initialise les boutons de sélection de modules
 */
function initModulesButtons() {
    const buttons = document.querySelectorAll('.toggle-btn[data-modules]');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Retirer la classe active de tous les boutons
            buttons.forEach(btn => btn.classList.remove('active'));

            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');

            // Mettre à jour la configuration
            currentConfig.modules = parseInt(this.dataset.modules);

            console.log('Modules sélectionnés:', currentConfig.modules);

            // Kenneth ajoutera ici : updatePrice() et generatePrompt()
        });
    });
}

/**
 * Initialise le slider de hauteur
 */
function initHeightSlider() {
    const slider = document.getElementById('height-slider');
    const valueDisplay = document.getElementById('height-value');

    if (!slider || !valueDisplay) return;

    slider.addEventListener('input', function() {
        // Mettre à jour l'affichage de la valeur
        valueDisplay.textContent = this.value;

        // Mettre à jour la configuration
        currentConfig.height = parseInt(this.value);

        console.log('Hauteur:', currentConfig.height, 'mm');

        // Kenneth ajoutera ici : updatePrice() et generatePrompt()
    });
}

/**
 * Initialise les boutons de profondeur
 */
function initDepthButtons() {
    const buttons = document.querySelectorAll('.toggle-btn[data-depth]');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Retirer la classe active de tous les boutons
            buttons.forEach(btn => btn.classList.remove('active'));

            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');

            // Mettre à jour la configuration
            currentConfig.depth = parseInt(this.dataset.depth);

            console.log('Profondeur:', currentConfig.depth, 'mm');

            // Kenneth ajoutera ici : updatePrice() et generatePrompt()
        });
    });
}

/**
 * Initialise le select de socle
 */
function initSocleSelect() {
    const select = document.getElementById('socle-select');

    if (!select) return;

    select.addEventListener('change', function() {
        currentConfig.socle = this.value;

        console.log('Socle:', currentConfig.socle);

        // Kenneth ajoutera ici : updatePrice()
    });
}

/**
 * Initialise le checkbox pour le fond
 */
function initFondCheckbox() {
    const checkbox = document.getElementById('fond-checkbox');

    if (!checkbox) return;

    checkbox.addEventListener('change', function() {
        currentConfig.fond = this.checked;

        console.log('Panneau fond:', currentConfig.fond);

        // Kenneth ajoutera ici : updatePrice() et generatePrompt()
    });
}

/**
 * Initialise les boutons de finition
 */
function initFinitionButtons() {
    const buttons = document.querySelectorAll('.toggle-btn[data-finish]');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Retirer la classe active de tous les boutons
            buttons.forEach(btn => btn.classList.remove('active'));

            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');

            // Mettre à jour la configuration
            currentConfig.finition = this.dataset.finish;

            console.log('Finition:', currentConfig.finition);

            // Kenneth ajoutera ici : updatePrice()
        });
    });
}

/**
 * Initialise le color picker
 */
function initColorPicker() {
    const colorPicker = document.getElementById('color-picker');
    const colorLabel = document.querySelector('.color-label');

    if (!colorPicker || !colorLabel) return;

    colorPicker.addEventListener('input', function() {
        // Mettre à jour la configuration
        currentConfig.color = this.value;

        // Mettre à jour le label avec le nom de la couleur
        const colorName = getColorName(this.value);
        colorLabel.textContent = colorName;

        console.log('Couleur:', currentConfig.color);

        // Kenneth ajoutera ici : updatePrice()
    });
}

/**
 * Retourne un nom de couleur basé sur le code hex
 * @param {string} hex - Code couleur hexadécimal
 * @returns {string} - Nom de la couleur
 */
function getColorName(hex) {
    const colors = {
        '#FFFFFF': 'Blanc',
        '#000000': 'Noir',
        '#8B4513': 'Bois',
        '#808080': 'Gris',
        '#D3D3D3': 'Gris clair'
    };

    // Retourner le nom si trouvé, sinon retourner le code hex
    return colors[hex.toUpperCase()] || hex;
}

/**
 * Initialise les boutons d'action
 */
function initActionButtons() {
    const saveBtn = document.getElementById('save-btn');
    const cartBtn = document.getElementById('cart-btn');

    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            console.log('Sauvegarde de la configuration:', currentConfig);
            // Kenneth ajoutera ici : saveConfiguration()
            alert('Configuration sauvegardée ! (Fonctionnalité à implémenter par Kenneth)');
        });
    }

    if (cartBtn) {
        cartBtn.addEventListener('click', function() {
            console.log('Ajout au panier:', currentConfig);
            // Kenneth ajoutera ici : addToCart()
            alert('Ajouté au panier ! (Fonctionnalité à implémenter par Kenneth)');
        });
    }
}

/**
 * Fonction placeholder pour mettre à jour le prix
 * Kenneth implémentera cette fonction avec la vraie logique de calcul
 */
function updatePrice() {
    // TODO (Kenneth): Calculer le prix en fonction de currentConfig
    // et mettre à jour l'élément #price
    console.log('updatePrice() sera implémenté par Kenneth');
}

/**
 * Fonction placeholder pour générer le prompt
 * Kenneth implémentera cette fonction avec la vraie logique
 */
function generatePrompt() {
    // TODO (Kenneth): Générer le prompt au format M1(...) à partir de currentConfig
    // et appeler l'API generate
    console.log('generatePrompt() sera implémenté par Kenneth');
}

// Exporter la configuration pour que Kenneth puisse y accéder
window.currentConfig = currentConfig;

// Initialiser les contrôles au chargement du DOM
document.addEventListener('DOMContentLoaded', initConfiguratorControls);
