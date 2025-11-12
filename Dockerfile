# Dockerfile pour ArchiMeuble Backend
# Image de base avec PHP 8.2 et support Python
FROM php:8.2-cli

# Configuration du fuseau horaire (Europe/Paris)
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
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
    libpng16-16t64 \
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
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Installer Composer (gestionnaire de dépendances PHP)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer wkhtmltopdf depuis le binaire officiel
RUN wget -q https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && apt-get update \
    && apt-get install -y --no-install-recommends ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm -rf /var/lib/apt/lists/*

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
RUN cp /app/init_db.sh /usr/local/bin/init_db.sh \
    && cp /app/backup-database.sh /usr/local/bin/backup-database.sh \
    && sed -i 's/\r$//' /usr/local/bin/init_db.sh \
    && sed -i 's/\r$//' /usr/local/bin/backup-database.sh \
    && sed -i 's/\r$//' /app/start.sh \
    && sed -i 's/\r$//' /app/samples_init.sql \
    && sed -i 's/\r$//' /app/init_missing_tables.sql \
    && sed -i 's/\r$//' /app/install_dependencies.sh \
    && sed -i 's/\r$//' /app/create_missing_tables.py \
    && sed -i 's/\r$//' /app/setup-backup-cron.sh \
    && chmod +x /usr/local/bin/init_db.sh \
    && chmod +x /usr/local/bin/backup-database.sh \
    && chmod +x /app/start.sh \
    && chmod +x /app/install_dependencies.sh \
    && chmod +x /app/create_missing_tables.py \
    && chmod +x /app/setup-backup-cron.sh

# Exposer le port 8000 pour le serveur PHP
EXPOSE 8000

# Script de démarrage qui initialise la BDD puis lance PHP
CMD ["/bin/bash", "/app/start.sh"]
