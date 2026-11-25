#!/bin/sh
set -e

# Si .env n'existe pas, copier .env.example
if [ ! -f .env ]; then
    echo "Creation de .env depuis .env.example..."
    cp .env.example .env
fi

# Creation du fichier SQLite s'il n'existe pas
mkdir -p database
if [ ! -f database/database.sqlite ]; then
    echo "Initialisation de la base SQLite..."
    touch database/database.sqlite
fi

# S'assurer que le dossier storage existe et a les bons droits
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public/photos
chmod -R 775 storage bootstrap/cache database

# Generation de la cle d'application si elle est vide
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo "Generation de la cle APP_KEY..."
    php artisan key:generate --force
fi

# Lien symbolique pour le stockage public des photos
php artisan storage:link --force 2>/dev/null || true

# Execution des migrations
echo "Execution des migrations..."
php artisan migrate --force

# Execution des seeders initiaux (langues, categories, pays) s'ils ne sont pas encore presents
php artisan db:seed --force 2>/dev/null || true

# Creation automatique de l'admin si ADMIN_PASSWORD est renseigne dans l'environnement
if [ -n "$ADMIN_PASSWORD" ]; then
    echo "Initialisation du compte administrateur..."
    php artisan db:seed --class=Database\\Seeders\\AdminSeeder --force 2>/dev/null || true
fi

echo "Demarrage de l'API Laravel sur le port 8000..."
exec php artisan serve --host=0.0.0.0 --port=8000
