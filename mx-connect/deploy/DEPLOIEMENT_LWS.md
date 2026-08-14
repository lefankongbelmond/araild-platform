# MX-CONNECT — Guide de mise en ligne sur VPS LWS (www.mx-connect.com)

Guide pas à pas pour un **VPS LWS sous Ubuntu 22.04/24.04**, du DNS jusqu'au fil
social temps réel. Multi-tenant : `www.mx-connect.com` = site central ; chaque
mutuelle = un sous-domaine `<slug>.mx-connect.com` avec **sa propre base**.

> Rappel : le code a été validé **structurellement** mais jamais exécuté. La
> première exécution réelle, c'est ici. Fais un premier passage sur un VPS de
> test si possible.

---

## 0. Pré-requis
- Un VPS LWS (2 vCPU / 4 Go RAM minimum recommandé pour Reverb + files + MySQL).
- Accès SSH root (ou un compte sudoer).
- Le domaine **mx-connect.com** géré chez LWS (zone DNS accessible).
- L'archive du projet (`mx-connect-foundation.zip`) ou l'accès au dépôt Git.

---

## 1. DNS chez LWS
Dans l'espace client LWS → **Nom de domaine → Zone DNS** de `mx-connect.com`,
créer trois enregistrements **A** pointant vers l'IP publique du VPS :

| Type | Nom | Valeur |
|------|-----|--------|
| A | `@` (mx-connect.com) | IP_DU_VPS |
| A | `www` | IP_DU_VPS |
| A | `*` (wildcard tenants) | IP_DU_VPS |

Le wildcard `*` permet à toute mutuelle `<slug>.mx-connect.com` de résoudre sans
retoucher le DNS à chaque création. Attendre la propagation (quelques minutes à
quelques heures) : `dig +short www.mx-connect.com` doit renvoyer l'IP.

---

## 2. Préparation du serveur
```bash
ssh root@IP_DU_VPS
adduser deploy && usermod -aG sudo deploy   # (optionnel) compte de déploiement
timedatectl set-timezone Africa/Douala
apt update && apt upgrade -y
```

---

## 3. Récupérer le code
```bash
sudo mkdir -p /var/www/mx-connect && sudo chown -R $USER:$USER /var/www/mx-connect
# Option A — via l'archive :
unzip mx-connect-foundation.zip -d /tmp && cp -r /tmp/mx-connect/. /var/www/mx-connect/
# Option B — via Git :
# git clone <URL_DU_DEPOT> /var/www/mx-connect
cd /var/www/mx-connect
```

---

## 4. Installer la pile logicielle
Le script `deploy/deploy.sh` installe tout (Nginx, PHP 8.3, MySQL, Redis,
Supervisor, Composer) et fait le premier `migrate --seed`. Tu peux le lancer…
```bash
cd /var/www/mx-connect && bash deploy/deploy.sh
```
…ou le faire manuellement (voir §5–§9 si tu préfères contrôler chaque étape).
Dans les deux cas, **relis §5 (base) et §6 (.env) avant** car ils contiennent
des points spécifiques au multi-tenant.

---

## 5. MySQL — base centrale + droits tenancy (POINT IMPORTANT)
MX-CONNECT crée **une base par mutuelle** à la volée. L'utilisateur MySQL doit
donc pouvoir **créer/supprimer des bases**, pas seulement écrire dans la base
centrale.
```bash
sudo mysql
```
```sql
CREATE DATABASE c1central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mconnect'@'127.0.0.1' IDENTIFIED BY 'MOT_DE_PASSE_FORT';

-- Base centrale : tous les droits.
GRANT ALL PRIVILEGES ON c1central.* TO 'mconnect'@'127.0.0.1';

-- Bases tenant (préfixe mxconnect_mut_*) : droits complets sur le motif…
GRANT ALL PRIVILEGES ON `mxconnect\_mut\_%`.* TO 'mconnect'@'127.0.0.1';
-- …et le droit de CRÉER/SUPPRIMER une base (nécessaire au provisioning tenant).
GRANT CREATE, DROP ON *.* TO 'mconnect'@'127.0.0.1';

FLUSH PRIVILEGES;
```
> Si tu préfères cloisonner, stancl/tenancy accepte un utilisateur distinct et
> privilégié pour la création de bases ; pour un pilote, l'approche ci-dessus est
> suffisante et simple.

---

## 6. Fichier .env
```bash
cd /var/www/mx-connect
cp deploy/.env.production.example .env
php artisan key:generate
php artisan reverb:install    # génère REVERB_APP_ID / KEY / SECRET dans .env
```
Puis éditer `.env` et renseigner : `DB_PASSWORD`, le SMTP (`MAIL_*`), la
passerelle SMS le cas échéant, et les identifiants de sauvegarde (`BACKUP_*`,
`AWS_*`). Vérifier que `REVERB_HOST=www.mx-connect.com`, `REVERB_PORT=443`,
`REVERB_SCHEME=https`. Laisser `REDIS_PASSWORD=null` si Redis local sans auth.

Permissions Laravel :
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
```

---

## 7. Migrations + données initiales (base centrale)
```bash
php artisan migrate --force
php artisan db:seed --force        # rôles, pays/devises/locales de référence,
                                   # plans de facturation de départ, super-admin
php artisan storage:link
```
> Note ce que le seeder affiche pour le **super-admin** : son mot de passe devra
> être changé immédiatement après la première connexion.

---

## 8. Créer la première mutuelle (crée sa base tenant)
Deux voies :
- **Interface** : se connecter sur `https://www.mx-connect.com` en super-admin →
  *Réseau → Mutuelles → Créer*. Le provisioning crée la base
  `mxconnect_mut_<slug>`, lance ses migrations et son seed, et rattache le
  sous-domaine `<slug>.mx-connect.com`.
- **Ligne de commande** (si un tinker/commande est préféré) : créer le `Mutual`
  via l'UI reste le plus sûr car il déclenche `MutualProvisioningService`.

Vérifier : `https://<slug>.mx-connect.com` doit afficher l'espace de la mutuelle.

---

## 9. Services (Nginx, Supervisor, Cron)
```bash
# Nginx (bloc central + wildcard tenants + proxy WebSocket Reverb)
sudo cp deploy/nginx.conf /etc/nginx/sites-available/mx-connect
sudo ln -sf /etc/nginx/sites-available/mx-connect /etc/nginx/sites-enabled/mx-connect
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Supervisor : workers de file + serveur Reverb (fil social temps réel)
sudo mkdir -p /var/log/mx-connect
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/mx-connect.conf
sudo cp deploy/supervisor-worker.conf /etc/supervisor/conf.d/mxconnect-worker.conf   # optionnel (workers dédiés)
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status        # mxconnect-queue et mxconnect-reverb doivent tourner

# Cron : le planificateur (rappels, arriérés, sauvegardes, offres, facturation)
( crontab -l 2>/dev/null; cat deploy/crontab.txt ) | crontab -
```

Optimisations production :
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## 10. Certificat TLS **wildcard** (Let's Encrypt via DNS-01)
Un certificat pour `*.mx-connect.com` **exige un challenge DNS-01** (le challenge
HTTP ne peut pas valider un wildcard). LWS n'a pas de plugin certbot officiel :
on fait un DNS-01 **manuel** (ajout d'un enregistrement TXT dans la zone LWS).
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --manual --preferred-challenges dns \
  -d mx-connect.com -d www.mx-connect.com -d '*.mx-connect.com'
```
Certbot affiche une valeur à publier en **TXT** sur `_acme-challenge.mx-connect.com`
dans la zone DNS LWS. Ajouter l'enregistrement, attendre la propagation
(`dig +short TXT _acme-challenge.mx-connect.com`), puis valider. Les chemins de
certificat correspondent déjà à `nginx.conf`
(`/etc/letsencrypt/live/mx-connect.com/…`). Recharger Nginx :
```bash
sudo systemctl reload nginx
```
> Renouvellement : le DNS-01 manuel n'est pas auto-renouvelé. Pour automatiser,
> utiliser un fournisseur DNS avec plugin certbot/acme, ou gérer un renouvellement
> assisté. À défaut, prévoir un rappel tous les ~80 jours.

---

## 11. Tester le temps réel (fil social)
1. `sudo supervisorctl status mxconnect-reverb` → **RUNNING**.
2. Se connecter comme membre sur `https://<slug>.mx-connect.com/espace-membre`,
   ouvrir `.../espace-membre/fil` : la pastille doit passer à **« En direct »**.
3. Publier depuis un second appareil/onglet membre de la même mutuelle : le
   message doit apparaître **instantanément** sans rafraîchir.
4. Vérifier qu'un membre d'une **autre** mutuelle ne reçoit rien (cloisonnement
   du canal privé `private-tenant.<id>.feed`).

Diagnostic si « Hors ligne » : `tail -f storage/logs/reverb.log`, vérifier que
Nginx relaie bien `/app` et `/apps` (bloc §36 de `nginx.conf`) et que
`REVERB_HOST`/`REVERB_SCHEME` pointent sur `https://www.mx-connect.com`.

---

## 12. Vérifications finales
- `https://www.mx-connect.com/up` → page santé framework.
- `https://www.mx-connect.com/health` → JSON `app`/`db`/`cache` (200).
- **Changer le mot de passe super-admin** immédiatement, activer sa MFA.
- (Recommandé) sur un VPS de test : `php artisan test` pour exécuter la suite
  Pest (~31 fichiers) — première exécution réelle du code.

---

## 13. Exploitation courante
- **Déployer une mise à jour** : `git pull` (ou re-upload) →
  `composer install --no-dev -o` → `php artisan migrate --force` →
  `php artisan optimize` → `php artisan queue:restart` →
  `sudo supervisorctl restart mxconnect-reverb`.
- **Sauvegardes** : `mxconnect:backup` tourne à 02:30 (cron) et journalise chaque
  restauration/essai dans le registre PRA (*Réseau → PRA*). Vérifier que les
  archives partent bien hors-serveur (S3/`BACKUP_DISK`).
- **Files d'attente** : après chaque déploiement, `php artisan queue:restart`.
- **Logs** : `storage/logs/mxconnect.log` (JSON, corrélé par `request_id`),
  `queue.log`, `reverb.log`.

---

## 14. Dépannage express
| Symptôme | Piste |
|----------|-------|
| Provisioning d'une mutuelle échoue | Droits `CREATE/DROP` MySQL manquants (voir §5) |
| Sous-domaine tenant → page centrale | DNS wildcard `*` absent, ou domaine listé comme central |
| Fil « Hors ligne » | Reverb arrêté, proxy `/app` Nginx absent, `REVERB_HOST` faux |
| 419 / CSRF sur l'API membre | Normal seulement hors `api/member/*` ; ces routes sont exemptées |
| Rappels non envoyés | Cron `schedule:run` absent, ou worker `mxconnect-queue` arrêté |
| Sauvegarde vide | `BACKUP_*`/`AWS_*` non renseignés dans `.env` |
