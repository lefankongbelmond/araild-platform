#!/usr/bin/env bash
# Copie fidèle de l'hébergement mutualisé LWS pour développer et tester MX-CONNECT.
#   - Apache + PHP 8.4 (gd WebP/JPEG, pdo_mysql, zip, intl), .htaccess et RewriteBase / actifs
#   - fonctions système désactivées (exec, shell_exec, proc_open…) comme sur LWS
#   - MariaDB 11.8, un seul utilisateur, bases créées « à la main » (pas de CREATE DATABASE par l'appli)
#   - racine web = production/ (équivalent de htdocs/mconnect)
# Usage : bash devtools/copie-lws/lancer.sh            (démarre / redémarre)
#         bash devtools/copie-lws/lancer.sh arreter    (arrête et supprime les conteneurs)
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
HERE="$REPO/devtools/copie-lws"
NET=mxlws
DB=mxlws-db
WEB=mxlws-web
PORT="${PORT:-8080}"
SECRETS="$HERE/.secrets.local"     # généré, ignoré par git

if [[ "${1:-}" == "arreter" ]]; then
  docker rm -f "$WEB" "$DB" >/dev/null 2>&1 || true
  echo "Copie LWS arrêtée."
  exit 0
fi

docker info >/dev/null 2>&1 || { echo "Démon Docker absent : lancez 'dockerd &' (root) puis réessayez."; exit 1; }

# Mots de passe locaux jetables (jamais ceux de la production).
if [[ ! -f "$SECRETS" ]]; then
  {
    echo "DB_ROOT_PASSWORD=$(head -c 18 /dev/urandom | base64 | tr -dc A-Za-z0-9)"
    echo "DB_PASSWORD=$(head -c 18 /dev/urandom | base64 | tr -dc A-Za-z0-9)"
    echo "INSTALL_TOKEN=$(head -c 30 /dev/urandom | base64 | tr -dc A-Za-z0-9)"
    echo "CRON_TOKEN=$(head -c 30 /dev/urandom | base64 | tr -dc A-Za-z0-9)"
  } > "$SECRETS"
fi
# shellcheck disable=SC1090
source "$SECRETS"

docker network inspect "$NET" >/dev/null 2>&1 || docker network create "$NET" >/dev/null

if ! docker ps --format '{{.Names}}' | grep -qx "$DB"; then
  docker rm -f "$DB" >/dev/null 2>&1 || true
  docker run -d --name "$DB" --network "$NET" \
    -e MARIADB_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
    mariadb:11.8 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >/dev/null
  echo -n "MariaDB démarre"
  until docker exec "$DB" mariadb -uroot -p"$DB_ROOT_PASSWORD" -e 'select 1' >/dev/null 2>&1; do echo -n .; sleep 1; done; echo
  # Comme sur LWS : bases créées dans le panneau, un seul utilisateur avec droits dessus.
  docker exec "$DB" mariadb -uroot -p"$DB_ROOT_PASSWORD" -e "
    CREATE DATABASE IF NOT EXISTS mx_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS mx_mutueltest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS mx_tests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS mx_tests_tenant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'mxdev'@'%' IDENTIFIED BY '$DB_PASSWORD';
    GRANT ALL PRIVILEGES ON mx_central.* TO 'mxdev'@'%';
    GRANT ALL PRIVILEGES ON mx_mutueltest.* TO 'mxdev'@'%';
    GRANT ALL PRIVILEGES ON mx_tests.* TO 'mxdev'@'%';
    GRANT ALL PRIVILEGES ON mx_tests_tenant.* TO 'mxdev'@'%';
    FLUSH PRIVILEGES;"
fi

ENV_FILE="$REPO/production/mxconnect/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  cat > "$ENV_FILE" <<EOF
APP_NAME=MX-CONNECT
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://m.araild.test:$PORT
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_TIMEZONE=Africa/Douala

DB_CONNECTION=central
DB_HOST=$DB
DB_PORT=3306
DB_DATABASE=mx_central
DB_USERNAME=mxdev
DB_PASSWORD=$DB_PASSWORD

CENTRAL_DOMAIN=m.araild.test
CENTRAL_DOMAINS=m.araild.test,localhost,127.0.0.1
TENANT_BASE_DOMAIN=m.araild.test
TENANT_DB_MODE=manual

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
MAIL_MAILER=log
LOG_CHANNEL=stack
LOG_LEVEL=debug

INSTALL_TOKEN=$INSTALL_TOKEN
CRON_TOKEN=$CRON_TOKEN
EOF
fi

for d in storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/framework/testing storage/logs bootstrap/cache; do
  mkdir -p "$REPO/production/mxconnect/$d"
done

docker rm -f "$WEB" >/dev/null 2>&1 || true
docker run -d --name "$WEB" --network "$NET" -p "$PORT:80" \
  -v "$REPO:/repo" \
  -e WEB_DOCUMENT_ROOT=/repo/production \
  -e PHP_DISABLE_FUNCTIONS="exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec" \
  -e PHP_MEMORY_LIMIT=512M -e PHP_POST_MAX_SIZE=110M -e PHP_UPLOAD_MAX_FILESIZE=110M \
  webdevops/php-apache:8.4 >/dev/null
sleep 2

run() { docker exec -u application -w /repo/production/mxconnect "$WEB" "$@"; }
docker exec "$WEB" chown -R application:application /repo/production/mxconnect/storage /repo/production/mxconnect/bootstrap/cache

if [[ ! -f "$REPO/production/mxconnect/vendor/autoload.php" ]]; then
  # Composer tourne sur l'hôte (le conteneur ne connaît pas le certificat du proxy sortant).
  echo "Dépendances absentes : lancez d'abord, sur l'hôte :"
  echo "  (cd production/mxconnect && composer install --no-scripts --ignore-platform-req=php)"
  exit 1
fi

# Clé générée sur l'hôte : le .env appartient à l'hôte, pas à l'utilisateur web du conteneur.
grep -q '^APP_KEY=base64' "$ENV_FILE" || sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" "$ENV_FILE"

echo
echo "Copie LWS prête : http://m.araild.test:$PORT  (ajoutez « 127.0.0.1 m.araild.test mutueltest.m.araild.test » à /etc/hosts)"
echo "Installateur    : http://m.araild.test:$PORT/_installation?token=$INSTALL_TOKEN"
echo "Commandes       : docker exec -u application -w /repo/production/mxconnect $WEB php /repo/devtools/copie-lws/artisan <cmd>"
