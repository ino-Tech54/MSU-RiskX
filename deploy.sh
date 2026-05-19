#!/bin/bash
cd ~/Risk-and-Loss/app

echo "Pulling latest code..."
git pull origin master

echo "Running migrations..."
php artisan migrate --force

echo "Copying new frontend build..."
cp -r public/build/static/js/* public/static/js/
cp -r public/build/static/css/* public/static/css/
cp public/build/asset-manifest.json public/asset-manifest.json

NEW_JS=$(ls public/build/static/js/main.*.js | grep -v map | grep -v LICENSE | xargs basename)
sed -i "s/main\.[a-f0-9]*\.js/$NEW_JS/g" public/index.html

echo "Clearing caches..."
php artisan route:clear
php artisan config:clear

echo "Done! Deployed successfully."