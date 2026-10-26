# Copie fidèle de l'hébergement LWS (développement et tests)

Ce dossier sert à **développer et tester** MX-CONNECT dans les mêmes conditions que l'hébergement mutualisé LWS. Il ne sert jamais en production.

## Ce que reproduit la copie

- **Serveur web** : Apache, avec `.htaccess` actif et `RewriteBase /`. La racine web est `production/`, l'équivalent de `htdocs/mconnect`.
- **PHP 8.4** avec gd (WebP/JPEG), pdo_mysql, zip et intl. Les fonctions système sont désactivées comme sur LWS : `exec`, `shell_exec`, `proc_open`, `popen`…
- **Base de données** : MariaDB 11.8, avec un seul utilisateur. Les bases (`mx_central`, `mx_mutueltest`, `mx_tests`) sont créées d'avance, comme dans le panneau LWS. L'application ne crée aucune base (`TENANT_DB_MODE=manual`).
- **Cache et sessions** en fichiers ; file d'attente dans la table `jobs` ; e-mails écrits dans le journal.

## Démarrer

1. Sur l'hôte, une seule fois :

   ```
   (cd production/mxconnect && composer install --no-scripts --ignore-platform-req=php)
   ```

2. Lancer la copie :

   ```
   bash devtools/copie-lws/lancer.sh
   ```

   Ce script :
   - démarre MariaDB et Apache/PHP ;
   - crée `production/mxconnect/.env` avec des mots de passe locaux aléatoires, rangés dans `.secrets.local`, ignoré par git.

3. Installer la base centrale :

   ```
   docker exec -u application -w /repo/production/mxconnect mxlws-web php /repo/devtools/copie-lws/artisan migrate --force
   docker exec -u application -w /repo/production/mxconnect mxlws-web php /repo/devtools/copie-lws/artisan db:seed --force
   ```

4. Ajouter les données de test locales :

   ```
   docker exec -u application -w /repo/production/mxconnect mxlws-web php /repo/devtools/copie-lws/donnees-locales.php
   ```

   Ce script crée :
   - la mutuelle `mutueltest`, avec un administrateur et un chargé de communication ;
   - 3 membres ;
   - une page de membre, avec ses projets, ses offres et ses demandes.

   Les identifiants sont écrits dans `production/mxconnect/storage/app/comptes-locaux.txt`, hors dépôt et hors ZIP.

5. Ouvrir http://m.araild.test:8080. Il faut d'abord ajouter `127.0.0.1 m.araild.test mutueltest.m.araild.test` au fichier hosts.

## Tests automatiques

Les tests utilisent la base `mx_tests`, jamais la base centrale :

```
docker exec -u application -w /repo/production/mxconnect mxlws-web php vendor/bin/pest
```

Les dossiers `tests/` et `phpunit.xml` ne sont **pas** livrés dans le ZIP de production.

## Arrêter

```
bash devtools/copie-lws/lancer.sh arreter
```

## Limite connue

La copie tourne en HTTP. Dans le back-office des mutuelles, la CSP (`img-src … https:`) bloque donc les logos servis en `http://` par le domaine central. En production (HTTPS), ces logos sont autorisés.

La caméra (lecteur de QR du portail prestataire) n'est disponible que dans un contexte sécurisé (HTTPS en production). Pour la tester en local, ajoutez l'alias `mutueltest.localhost` à la mutuelle de test (Chromium considère `*.localhost` comme sûr) :

```
docker exec mxlws-db mariadb -uroot -p"$DB_ROOT_PASSWORD" -e "insert ignore into mx_central.domains (domain, mutual_id, created_at, updated_at) values ('mutueltest.localhost','mut_01',now(),now());"
```

puis ouvrez `http://mutueltest.localhost:8080/prestataire`.
