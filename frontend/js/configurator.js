/**
 * ArchiMeuble - Configurator Logic
 * Gère toute la logique du configurateur
 * Auteur: Kenneth
 * Date: 2025-10-21
 */

// Configuration globale (ajustée pour prix de départ = 899€)
const config = {
    modules: 3,        // 3 modules pour correspondre au Scandinave
    hauteur: 73,       // en cm
    profondeur: 50,    // en cm (50cm)
    socle: 'none',     // Sans socle
    fond: true,        // Toujours présent (F)
    enveloppeBase: true,  // Toujours présent (Eb)
    finition: 'mat',
    color: '#FFFFFF'
};

// Dernier GLB généré (pour sauvegarde)
let lastGeneratedGlbUrl = null;

// Prompt du template (si chargé depuis un template)
let templatePrompt = null;

/**
 * Initialise tous les contrôles du configurateur
 */
function initControls() {
    console.log('Initialisation des contrôles...');

    // Boutons modules (affecte la largeur)
    const moduleBtns = document.querySelectorAll('.toggle-btn[data-modules]');
    moduleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            setActiveButton('.toggle-btn[data-modules]', this);
            config.modules = parseInt(this.dataset.modules);
            console.log('Modules:', config.modules);
            updateDimensionsOnly(); // Modifier uniquement les dimensions
        });
    });

    // Slider hauteur
    const heightSlider = document.getElementById('height-slider');
    const heightValue = document.getElementById('height-value');
    if (heightSlider && heightValue) {
        heightSlider.addEventListener('input', function() {
            const value = parseInt(this.value);
            config.hauteur = Math.round(value / 10); // Convertir mm en cm
            heightValue.textContent = value;
            updateDimensionsOnly(); // Modifier uniquement les dimensions
        });
    }

    // Boutons profondeur
    const depthBtns = document.querySelectorAll('.toggle-btn[data-depth]');
    depthBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            setActiveButton('.toggle-btn[data-depth]', this);
            config.profondeur = parseInt(this.dataset.depth) / 10; // mm vers cm
            console.log('Profondeur:', config.profondeur, 'cm');
            updateDimensionsOnly(); // Modifier uniquement les dimensions
        });
    });

    // Select socle
    const socleSelect = document.getElementById('socle-select');
    if (socleSelect) {
        socleSelect.addEventListener('change', function() {
            config.socle = this.value;
            console.log('Socle:', config.socle);
            updateAllFromUser();
        });
    }

    // Note: fond et enveloppeBase sont toujours activés (EbF obligatoire)
    // Pas de contrôles UI nécessaires pour ces options

    // Boutons finition
    const finishBtns = document.querySelectorAll('.toggle-btn[data-finish]');
    finishBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            setActiveButton('.toggle-btn[data-finish]', this);
            config.finition = this.dataset.finish;
            console.log('Finition:', config.finition);
            updateAllFromUser();
        });
    });

    // Color picker
    const colorPicker = document.getElementById('color-picker');
    if (colorPicker) {
        colorPicker.addEventListener('input', function() {
            config.color = this.value;
            console.log('Couleur:', config.color);
            updateAllFromUser();
        });
    }

    // Bouton Sauvegarder
    const saveBtn = document.getElementById('save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveConfiguration);
    }

    // Bouton Ajouter au panier
    const cartBtn = document.getElementById('cart-btn');
    if (cartBtn) {
        cartBtn.addEventListener('click', addToCart);
    }

    console.log('✓ Contrôles initialisés');
}

/**
 * Retire/Ajoute la classe "active" sur les boutons d'un groupe
 * @param {string} selector - Sélecteur CSS du groupe
 * @param {HTMLElement} activeBtn - Bouton à activer
 */
function setActiveButton(selector, activeBtn) {
    // Retirer "active" de tous les boutons du groupe
    document.querySelectorAll(selector).forEach(btn => {
        btn.classList.remove('active');
    });

    // Ajouter "active" au bouton cliqué
    activeBtn.classList.add('active');
}

/**
 * Met à jour tout : prompt, prix, et génération 3D
 */
function updateAll() {
    console.log('Configuration actuelle:', config);

    // Utiliser le prompt du template s'il existe, sinon générer depuis la config
    const prompt = templatePrompt || window.generatePrompt(config);

    // Calculer le prix
    const price = window.calculatePrice(config);

    // Afficher le prix dans l'interface
    const priceElement = document.getElementById('price');
    if (priceElement) {
        priceElement.textContent = price.toFixed(0);
    }

    // Générer le modèle 3D (avec un délai pour éviter trop d'appels API)
    clearTimeout(window.generateTimeout);
    window.generateTimeout = setTimeout(() => {
        generateModel(prompt);
    }, 300); // Attendre 300ms après le dernier changement (réduit pour être plus réactif)
}

/**
 * Met à jour tout en mode personnalisé (annule le mode template)
 * Appelé quand l'utilisateur modifie un paramètre structurel (modules, finition, etc.)
 */
function updateAllFromUser() {
    // Annuler le mode template (passer en mode configuration personnalisée)
    templatePrompt = null;

    // Appeler updateAll normalement
    updateAll();
}

/**
 * Met à jour uniquement les dimensions du template
 * Appelé quand l'utilisateur modifie hauteur/profondeur/largeur
 */
function updateDimensionsOnly() {
    console.log('Mise à jour dimensions uniquement');

    if (templatePrompt) {
        // Calculer la largeur depuis les modules (500mm par module)
        const largeur = config.modules * 500;
        const profondeur = config.profondeur * 10;  // cm vers mm
        const hauteur = config.hauteur * 10;        // cm vers mm

        // Modifier le prompt du template avec les nouvelles dimensions
        templatePrompt = window.modifyPromptDimensions(templatePrompt, largeur, profondeur, hauteur);
        console.log('Nouveau prompt avec dimensions modifiées:', templatePrompt);
    }

    // Appeler updateAll qui utilisera le templatePrompt modifié
    updateAll();
}

/**
 * Génère le modèle 3D en appelant l'API
 * @param {string} prompt - Prompt M1(...)
 */
async function generateModel(prompt) {
    console.log('Génération du modèle 3D avec prompt:', prompt);

    // Afficher le loading
    if (window.showLoading) {
        window.showLoading();
    }

    try {
        // Appel à l'API generate
        const response = await fetch('/api/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ prompt: prompt })
        });

        const data = await response.json();

        if (data.success) {
            console.log('✓ Modèle 3D généré:', data.glb_url);

            // Sauvegarder l'URL du GLB
            lastGeneratedGlbUrl = data.glb_url;

            // Mettre à jour le viewer 3D
            if (window.updateModel) {
                window.updateModel(data.glb_url);
            }
        } else {
            console.error('Erreur API generate:', data.error);

            // Afficher l'erreur dans le viewer
            if (window.showError) {
                window.showError('Erreur lors de la génération du meuble');
            }
        }

    } catch (error) {
        console.error('Erreur lors de l\'appel API generate:', error);

        if (window.showError) {
            window.showError('Impossible de générer le meuble');
        }
    } finally {
        // Masquer le loading
        if (window.hideLoading) {
            window.hideLoading();
        }
    }
}

/**
 * Sauvegarde la configuration actuelle dans la base de données
 */
async function saveConfiguration() {
    console.log('Sauvegarde de la configuration...');

    // Générer le prompt et le prix
    const prompt = window.generatePrompt(config);
    const price = window.calculatePrice(config);

    // ID de session (générer ou récupérer depuis localStorage)
    let userSession = localStorage.getItem('archimeuble_session');
    if (!userSession) {
        userSession = 'session_' + Date.now();
        localStorage.setItem('archimeuble_session', userSession);
    }

    try {
        // Appel à l'API configurations
        const response = await fetch('/api/configurations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_session: userSession,
                prompt: prompt,
                price: price,
                glb_url: lastGeneratedGlbUrl
            })
        });

        const data = await response.json();

        if (data.success) {
            console.log('✓ Configuration sauvegardée avec ID:', data.id);
            alert('Configuration sauvegardée avec succès !');
        } else {
            console.error('Erreur sauvegarde:', data.error);
            alert('Erreur lors de la sauvegarde : ' + data.error);
        }

    } catch (error) {
        console.error('Erreur lors de l\'appel API configurations:', error);
        alert('Impossible de sauvegarder la configuration');
    }
}

/**
 * Ajoute la configuration actuelle au panier (localStorage)
 */
function addToCart() {
    console.log('Ajout au panier...');

    // Générer le prompt et le prix
    const prompt = window.generatePrompt(config);
    const price = window.calculatePrice(config);

    // Récupérer le panier actuel
    let cart = JSON.parse(localStorage.getItem('archimeuble_cart') || '[]');

    // Ajouter l'article
    const item = {
        id: Date.now(),
        config: { ...config },
        prompt: prompt,
        price: price,
        glb_url: lastGeneratedGlbUrl,
        timestamp: new Date().toISOString()
    };

    cart.push(item);

    // Sauvegarder dans localStorage
    localStorage.setItem('archimeuble_cart', JSON.stringify(cart));

    console.log('✓ Meuble ajouté au panier. Total articles:', cart.length);
    alert(`Meuble ajouté au panier !\nPrix: ${price}€\nArticles dans le panier: ${cart.length}`);
}

/**
 * Charge une configuration depuis un template
 * @param {number} templateId - ID du template
 */
async function loadTemplate(templateId) {
    console.log('Chargement du template:', templateId);

    try {
        const response = await fetch(`/backend/api/templates.php?id=${templateId}`);
        const data = await response.json();

        if (data.success && data.data) {
            const template = data.data;

            console.log('✓ Template chargé:', template);

            // Stocker le prompt du template
            templatePrompt = template.prompt;

            // Mettre à jour le prix affiché
            const priceElement = document.getElementById('price');
            if (priceElement) {
                priceElement.textContent = template.base_price.toFixed(0);
            }

            // Générer le modèle 3D avec le prompt du template
            generateModel(template.prompt);
        }

    } catch (error) {
        console.error('Erreur chargement template:', error);
    }
}

/**
 * Initialisation au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('ArchiMeuble Configurator - Kenneth');

    // Initialiser les contrôles
    initControls();

    // Vérifier si un template est demandé dans l'URL (?template=ID)
    const urlParams = new URLSearchParams(window.location.search);
    const templateId = urlParams.get('template');

    if (templateId) {
        // Charger le template depuis l'API
        console.log('Template ID détecté dans l\'URL:', templateId);
        loadTemplate(templateId);
    } else {
        // Pas de template, utiliser la configuration par défaut
        const initialPrice = window.calculatePrice(config);
        const priceElement = document.getElementById('price');
        if (priceElement) {
            priceElement.textContent = initialPrice.toFixed(0);
        }

        // Générer le modèle 3D initial
        const initialPrompt = window.generatePrompt(config);
        generateModel(initialPrompt);
    }

    console.log('✓ Configurateur prêt');
});

// Exposer la configuration pour debug
window.config = config;
window.updateAll = updateAll;
window.updateAllFromUser = updateAllFromUser;
window.updateDimensionsOnly = updateDimensionsOnly;
window.loadTemplate = loadTemplate;
window.getTemplatePrompt = () => templatePrompt;
