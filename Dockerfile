# Dockerfile pour ArchiMeuble Backend
# Image de base avec PHP 8.2 et support Python
FROM php:8.2-cli-bookworm

# Configuration du fuseau horaire (Europe/Paris)
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Forcer HTTPS pour apt (contourne les portails captifs réseau)
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources 2>/dev/null; \
    sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list 2>/dev/null; \
    true

# Ajouter le dépôt officiel PostgreSQL pour avoir le client v16
RUN apt-get update && apt-get install -y gnupg2 lsb-release && \
    echo "deb https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list && \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/postgresql.gpg && \
    apt-get update

# Installation des dépendances système
RUN apt-get install -y \
    postgresql-client-16 \
    libpq-dev \
    python3 \
    python3-pip \
    python3-venv \
    git \
    curl \
    wget \
    unzip \
    cron \
    fontconfig \
    libfreetype6 \
    libjpeg62-turbo \
    libpng16-16 \
    libx11-6 \
    libxcb1 \
    libxext6 \
    libxrender1 \
    xfonts-75dpi \
    xfonts-base \
    libosmesa6 \
    libosmesa6-dev \
    libgl1-mesa-dri \
    xvfb \
    tzdata \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer (gestionnaire de dépendances PHP)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Note: wkhtmltopdf n'est pas disponible dans Debian Trixie ARM64
# Le code utilise automatiquement un fallback (FPDF/DomPDF) si wkhtmltopdf n'est pas installé
# Si nécessaire, installer manuellement : https://wkhtmltopdf.org/downloads.html

# Créer un environnement virtuel Python
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Variables d'environnement pour PyVista en mode headless (sans écran)
ENV PYVISTA_OFF_SCREEN=true
ENV PYVISTA_USE_IPYVTK=false
ENV VTK_SILENCE_GET_VOID_POINTER_WARNINGS=1
ENV MESA_GL_VERSION_OVERRIDE=3.3
ENV DISPLAY=:99

# Copier requirements.txt et installer les dépendances Python
COPY requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

# Créer le répertoire de travail
WORKDIR /app

# Invalider le cache Docker pour forcer la copie des nouveaux fichiers
ARG CACHEBUST=7

# Copier tous les fichiers de l'application
COPY . /app

# Créer les dossiers nécessaires
RUN mkdir -p /app/devis \
    && mkdir -p /app/pieces \
    && mkdir -p /app/uploads \
    && mkdir -p /app/models \
    && mkdir -p /app/database \
    && chmod -R 777 /app

# S'assurer que les scripts sont exécutables et avec des fins de ligne Unix
RUN cp /app/backup-database.sh /usr/local/bin/backup-database.sh \
    && sed -i 's/\r$//' /usr/local/bin/backup-database.sh \
    && sed -i 's/\r$//' /app/start.sh \
    && sed -i 's/\r$//' /app/init_db.sql \
    && sed -i 's/\r$//' /app/create_missing_tables.py \
    && chmod +x /usr/local/bin/backup-database.sh \
    && chmod +x /app/start.sh \
    && chmod +x /app/create_missing_tables.py

# Exposer le port 8080 pour le serveur PHP (Railway utilise 8080)
EXPOSE 8080

# Script de démarrage qui initialise la BDD puis lance PHP
CMD ["/bin/bash", "/app/start.sh"]
