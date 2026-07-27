#!/bin/bash
# Usage: ./deploy-backend.sh <backend_path> <github_user> <github_token> <repo_full_name> [repo_path]
# repo_path defaults to backend_path for backward compatibility with single-directory repos.

BACKEND_PATH=$1
GITHUB_USER=$2
GITHUB_TOKEN=$3
REPO_FULL_NAME=$4
REPO_PATH=${5:-$BACKEND_PATH}

cd "$BACKEND_PATH" || exit 1

if [ ! -f ".env" ]; then
    echo "ERROR: No se encontro .env en $BACKEND_PATH"
    exit 1
fi

set -a
source .env
set +a

echo "Iniciando deploy del backend..."

(cd "$REPO_PATH" && git pull "https://${GITHUB_USER}:${GITHUB_TOKEN}@github.com/${REPO_FULL_NAME}.git" main) || exit 1

echo "Limpiando cache..."
php artisan cache:clear || exit 1
php artisan config:clear || exit 1
php artisan route:clear || exit 1

echo "Regenerando cache..."
php artisan config:cache || exit 1
php artisan config:clear || exit 1
php artisan route:cache || exit 1

echo "Ejecutando migraciones..."
php artisan migrate --force || exit 1

echo "Reiniciando workers..."
php artisan queue:restart || exit 1
sleep 10 || exit 1

echo "Deploy completado exitosamente"
