/**
 * ArchiMeuble - Catalog JavaScript
 * Charge les meubles depuis l'API et affiche les cartes
 * Auteur: Stephen
 */

// URL de l'API templates
const API_TEMPLATES_URL = '/backend/api/templates.php';

/**
 * Charge les meubles depuis l'API
 */
async function loadCatalog() {
    const catalogGrid = document.getElementById('catalog-grid');

    try {
        // Afficher un message de chargement
        catalogGrid.innerHTML = '<div class="loading">Chargement des meubles...</div>';

        // Appel à l'API
        const response = await fetch(API_TEMPLATES_URL);

        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        const data = await response.json();

        // Vérifier que la requête a réussi
        if (!data.success) {
            throw new Error(data.error || 'Erreur lors du chargement des meubles');
        }

        // Vérifier qu'il y a des meubles
        if (!data.data || data.data.length === 0) {
            catalogGrid.innerHTML = '<div class="loading">Aucun meuble disponible pour le moment.</div>';
            return;
        }

        // Vider le conteneur
        catalogGrid.innerHTML = '';

        // Créer une carte pour chaque meuble
        data.data.forEach(furniture => {
            const card = createFurnitureCard(furniture);
            catalogGrid.appendChild(card);
        });

    } catch (error) {
        console.error('Erreur lors du chargement du catalogue:', error);
        catalogGrid.innerHTML = `
            <div class="loading">
                Impossible de charger les meubles.
                <br>Veuillez réessayer plus tard.
                <br><small>${error.message}</small>
            </div>
        `;
    }
}

/**
 * Crée une carte HTML pour un meuble
 * @param {Object} furniture - Données du meuble
 * @returns {HTMLElement} - Élément DOM de la carte
 */
function createFurnitureCard(furniture) {
    // Créer le conteneur de la carte
    const card = document.createElement('div');
    card.className = 'furniture-card';

    // Image du meuble
    const imageDiv = document.createElement('div');
    imageDiv.className = 'furniture-card-image';

    // Si une image existe, l'afficher
    if (furniture.image_url) {
        const img = document.createElement('img');
        img.src = furniture.image_url;
        img.alt = furniture.name;
        img.onerror = function() {
            // Si l'image ne charge pas, afficher un placeholder
            this.parentElement.innerHTML = '<div style="font-size: 48px; color: #ccc;">📺</div>';
        };
        imageDiv.appendChild(img);
    } else {
        // Placeholder si pas d'image
        imageDiv.innerHTML = '<div style="font-size: 48px; color: #ccc;">📺</div>';
    }

    // Contenu de la carte
    const contentDiv = document.createElement('div');
    contentDiv.className = 'furniture-card-content';

    // Titre
    const title = document.createElement('h3');
    title.className = 'furniture-card-title';
    title.textContent = furniture.name;

    // Prix
    const price = document.createElement('div');
    price.className = 'furniture-card-price';
    price.textContent = `${furniture.base_price.toFixed(2)} €`;

    // Bouton Configurer
    const button = document.createElement('a');
    button.href = `/configurator?template=${furniture.id}`;
    button.className = 'btn btn-primary';
    button.textContent = 'Configurer';

    // Assembler la carte
    contentDiv.appendChild(title);
    contentDiv.appendChild(price);
    contentDiv.appendChild(button);

    card.appendChild(imageDiv);
    card.appendChild(contentDiv);

    return card;
}

// Charger le catalogue au chargement de la page
document.addEventListener('DOMContentLoaded', loadCatalog);
