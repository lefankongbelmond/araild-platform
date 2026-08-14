#!/usr/bin/env bash
# MX-CONNECT — first deploy on a fresh LWS VPS (Ubuntu). Run as a sudoer.
set -euo pipefail

APP_DIR=/var/www/mx-connect

echo ">> System packages"
sudo apt update
sudo apt install -y nginx redis-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath \
  php8.3-curl php8.3-zip php8.3-gd php8.3-redis mysql-server

echo ">> Composer"
php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

echo ">> App"
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader
cp -n .env.example .env
php artisan key:generate
php artisan storage:link

echo ">> Central DB migrate + seed"
php artisan migrate --force
php artisan db:seed --force

echo ">> Services"
sudo cp deploy/nginx.conf /etc/nginx/sites-available/mx-connect
sudo ln -sf /etc/nginx/sites-available/mx-connect /etc/nginx/sites-enabled/mx-connect
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/mx-connect.conf
sudo supervisorctl reread && sudo supervisorctl update
( crontab -l 2>/dev/null; cat deploy/crontab.txt ) | crontab -
sudo nginx -t && sudo systemctl reload nginx

echo ">> Certbot (wildcard requires DNS-01)"
echo "Run: sudo certbot --nginx -d mx-connect.com -d www.mx-connect.com -d '*.mx-connect.com'"
echo "Done. Rotate the seeded super-admin password immediately."
