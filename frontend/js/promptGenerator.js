/**
 * ArchiMeuble - Prompt Generator & Price Calculator
 * Génère le prompt M1(...) et calcule le prix
 * Auteur: Kenneth
 * Date: 2025-10-21
 */

/**
 * Génère le prompt M1 à partir de la configuration
 * Format: M1(largeur,profondeur,hauteur)E[F][Hx(config_modules)]
 *
 * @param {Object} config - Configuration actuelle
 * @returns {string} - Prompt au format M1
 */
function generatePrompt(config) {
    // Calculer la largeur en fonction du nombre de modules
    // Chaque module fait 500mm de large
    const largeur = config.modules * 500;

    // Convertir profondeur et hauteur de cm en mm
    const profondeur = config.profondeur * 10;
    const hauteur = config.hauteur * 10;

    // Construire la partie de base M1(largeur,profondeur,hauteur)
    let prompt = `M1(${largeur},${profondeur},${hauteur})`;

    // Ajouter EbF - toujours présent pour tous les meubles M1
    prompt += 'EbF';

    // Si plusieurs modules, ajouter la configuration des modules
    if (config.modules > 1) {
        prompt += `H${config.modules}(`;

        // Pour l'instant, tous les modules sont vides (F = false)
        // Kenneth peut personnaliser cela plus tard
        const moduleConfig = Array(config.modules).fill('F').join(',');
        prompt += moduleConfig;

        prompt += ')';
    }

    console.log('Prompt généré:', prompt);
    return prompt;
}

/**
 * Calcule le prix total en fonction de la configuration
 *
 * @param {Object} config - Configuration actuelle
 * @returns {number} - Prix total en euros
 */
function calculatePrice(config) {
    // Prix de base incluant EbF (ajusté pour correspondre aux templates de la BDD)
    // EbF est toujours inclus (enveloppe + base + fond)
    let price = 580;  // 450 + 80 (fond) + 50 (enveloppe base)

    // Ajouter le prix des modules (150€ par module)
    price += config.modules * 150;

    // Ajouter le prix de la hauteur (2€ par cm au-dessus de 60cm)
    if (config.hauteur > 60) {
        price += (config.hauteur - 60) * 2;
    }

    // Ajouter le prix de la profondeur (3€ par cm)
    price += config.profondeur * 3;

    // Ajouter le prix de la finition
    const finitionPrices = {
        'mat': 0,
        'brillant': 60,
        'bois': 100
    };
    price += finitionPrices[config.finition] || 0;

    // Ajouter le prix du socle
    const soclePrices = {
        'none': 0,
        'metal': 40,
        'wood': 60
    };
    price += soclePrices[config.socle] || 0;

    // Ajouter le prix de la couleur (basé sur le code hex)
    // Pour simplifier, on utilise une correspondance approximative
    const colorPrices = {
        '#FFFFFF': 30,  // Blanc
        '#000000': 40,  // Noir
        '#8B4513': 50,  // Bois/Noyer
        '#D3D3D3': 20,  // Gris clair
    };

    // Prix par défaut si couleur non reconnue
    const colorPrice = colorPrices[config.color] || 0;
    price += colorPrice;

    console.log('Prix calculé:', price, '€');
    return price;
}

/**
 * Modifie les dimensions (L, P, H) dans un prompt existant
 * @param {string} prompt - Prompt existant (ex: M1(1500,500,730)EbFSV3(H2,P,P))
 * @param {number} largeur - Nouvelle largeur en mm
 * @param {number} profondeur - Nouvelle profondeur en mm
 * @param {number} hauteur - Nouvelle hauteur en mm
 * @returns {string} - Prompt avec dimensions modifiées
 */
function modifyPromptDimensions(prompt, largeur, profondeur, hauteur) {
    // Regex pour capturer M1(L,P,H) et le reste du prompt
    const regex = /^(M[1-5])\((\d+),(\d+),(\d+)\)(.*)$/;
    const match = prompt.match(regex);

    if (match) {
        const meubleType = match[1];  // M1, M2, etc.
        const reste = match[5];        // EbFSV3(H2,P,P)

        // Reconstruire le prompt avec les nouvelles dimensions
        return `${meubleType}(${largeur},${profondeur},${hauteur})${reste}`;
    }

    // Si le regex ne match pas, retourner le prompt original
    console.warn('Impossible de parser le prompt:', prompt);
    return prompt;
}

// Exposer les fonctions globalement
window.generatePrompt = generatePrompt;
window.calculatePrice = calculatePrice;
window.modifyPromptDimensions = modifyPromptDimensions;
