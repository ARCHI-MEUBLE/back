# Dockerfile pour ArchiMeuble Backend
# Image de base avec PHP 8.2 et support Python
FROM php:8.2-cli

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    python3 \
    python3-pip \
    python3-venv \
    git \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Créer un environnement virtuel Python
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Copier requirements.txt et installer les dépendances Python
COPY requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

# Créer le répertoire de travail
WORKDIR /app

# Copier tous les fichiers de l'application
COPY . /app

# Créer les dossiers nécessaires
RUN mkdir -p /app/devis \
    && mkdir -p /app/pieces \
    && mkdir -p /app/uploads \
    && mkdir -p /app/models \
    && mkdir -p /app/database \
    && chmod -R 777 /app

# S'assurer que le script d'initialisation est exécutable et copié au bon endroit
RUN cp /app/init_db.sh /usr/local/bin/init_db.sh && chmod +x /usr/local/bin/init_db.sh

# Exposer le port 8000 pour le serveur PHP
EXPOSE 8000

# Script de démarrage qui initialise la BDD puis lance PHP
CMD ["/bin/bash", "-c", "/usr/local/bin/init_db.sh && php -S 0.0.0.0:8000 router.php"]
