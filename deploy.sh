#!/bin/bash
cd ~/Risk-and-Loss/app

echo "Pulling latest code..."
git pull origin master

echo "Running migrations..."
php artisan migrate --force

echo "Clearing caches..."
php artisan route:clear
php artisan config:clear

echo "Done! Deployed successfully."