# 🌍 ARAILD Platform — Documentation Complète

> Plateforme web de levée de fonds et de gestion d'impact pour l'ONG ARAILD (Cameroun)

---

## 🚀 Démarrage rapide

### Prérequis
- Node.js 20+
- PostgreSQL 15+ (ou Docker)
- Compte Stripe (test ou production)

### 1. Cloner et configurer

```bash
git clone https://github.com/araild/platform.git
cd araild-platform
```

### 2. Backend

```bash
cd backend
cp .env.example .env
# Éditez .env avec vos vraies valeurs

npm install
npx prisma generate
npx prisma db push
npm run db:seed    # Données initiales (admin, programmes, partenaires)
npm run dev        # http://localhost:4000
```

**Compte admin par défaut :**
- Email : `admin@araild.com`
- Mot de passe : `Admin@ARAILD2024!`
- ⚠️ **Changez ce mot de passe immédiatement en production !**

### 3. Frontend

```bash
cd frontend
cp .env.local.example .env.local
# Définissez NEXT_PUBLIC_API_URL=http://localhost:4000/api

npm install
npm run dev        # http://localhost:3000
```

---

## 🐳 Déploiement Docker (production)

```bash
# 1. Copier et configurer les variables d'environnement
cp .env.example .env
# Remplissez TOUTES les variables (JWT_SECRET, Stripe, SMTP...)

# 2. Lancer tous les services
docker-compose up -d

# 3. Premier démarrage : appliquer les migrations
docker exec araild_api npx prisma migrate deploy
docker exec araild_api npm run db:seed

# 4. Vérifier
curl http://localhost/health
```

---

## ☁️ Déploiement Cloud (recommandé)

### Option A — Railway (le plus simple)

```bash
# Backend
railway up --service backend

# Frontend  
railway up --service frontend

# PostgreSQL : ajouter le plugin Railway PostgreSQL
```

### Option B — Render

1. Créer un service Web pour le backend (`npm run build && npm start`)
2. Créer un service Web pour le frontend (`npm run build && npm start`)
3. Créer une base PostgreSQL managée
4. Configurer les variables d'environnement

### Option C — VPS (Ubuntu 22.04)

```bash
# Installer Docker
curl -fsSL https://get.docker.com | sh

# Cloner le repo
git clone https://github.com/araild/platform.git /opt/araild
cd /opt/araild

# Configurer
cp .env.example .env
nano .env  # Remplir toutes les valeurs

# Démarrer
docker-compose up -d

# SSL avec Certbot
docker-compose run certbot
```

---

## 🔑 Variables d'environnement

### Backend (.env)

| Variable | Description | Exemple |
|----------|-------------|---------|
| `DATABASE_URL` | URL PostgreSQL | `postgresql://user:pass@host:5432/db` |
| `JWT_SECRET` | Clé secrète JWT (32+ chars) | `your_super_secret_key_here` |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe | `sk_live_...` |
| `STRIPE_WEBHOOK_SECRET` | Secret webhook Stripe | `whsec_...` |
| `SMTP_HOST` | Serveur SMTP | `smtp.gmail.com` |
| `SMTP_USER` | Email SMTP | `noreply@araild.com` |
| `SMTP_PASS` | Mot de passe app SMTP | `xxxx xxxx xxxx xxxx` |
| `FRONTEND_URL` | URL du frontend | `https://araild.com` |
| `ADMIN_EMAIL` | Email admin pour notifs | `contact@araild.com` |

### Frontend (.env.local)

| Variable | Description |
|----------|-------------|
| `NEXT_PUBLIC_API_URL` | URL de l'API backend |
| `NEXT_PUBLIC_STRIPE_PK` | Clé publique Stripe |

---

## 🔌 Configuration Stripe

### 1. Créer les webhooks

Dans le dashboard Stripe → Développeurs → Webhooks :
```
URL: https://api.araild.com/api/donations/webhook
Événements à écouter:
  - checkout.session.completed
  - checkout.session.expired
  - invoice.payment_succeeded
  - charge.refunded
```

### 2. Tester en local avec Stripe CLI

```bash
stripe listen --forward-to localhost:4000/api/donations/webhook
```

---

## 📁 Structure du projet

```
araild-platform/
├── backend/                 # API Node.js + Express
│   ├── src/
│   │   ├── controllers/     # Logique métier (donations, programs, etc.)
│   │   ├── routes/          # Définition des routes API
│   │   ├── middleware/       # Auth JWT, rate limiting
│   │   └── services/        # Email, Stripe
│   └── prisma/
│       ├── schema.prisma    # Schéma base de données
│       └── seed.ts          # Données initiales
│
├── frontend/                # Next.js 14
│   ├── app/                 # Pages (App Router)
│   │   ├── page.tsx         # Accueil
│   │   ├── donate/          # Page dons Stripe
│   │   ├── impact/          # Résultats + graphiques
│   │   ├── programs/        # 4 piliers
│   │   ├── contact/         # Formulaire contact/bailleurs
│   │   └── admin/           # Dashboard admin (protégé)
│   └── components/
│       ├── home/            # Hero, Stats, Storytelling, Urgence
│       ├── impact/          # Graphiques Recharts
│       ├── layout/          # Navbar, Footer
│       └── shared/          # StatCard, NewsletterForm
│
└── docker-compose.yml       # Orchestration production
```

---

## 🛠️ Commandes utiles

```bash
# Backend
npm run dev          # Développement avec hot reload
npm run db:studio    # Interface Prisma Studio (http://localhost:5555)
npm run db:seed      # Réinitialiser les données de test

# Frontend
npm run dev          # Développement
npm run build        # Build production
npm run lint         # Vérifier le code

# Docker
docker-compose up -d          # Démarrer tous les services
docker-compose logs -f        # Voir les logs
docker-compose down           # Arrêter
docker-compose down -v        # Arrêter + supprimer les volumes
```

---

## 📊 API Reference

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/auth/login` | Connexion admin |
| GET  | `/api/stats/impact` | Stats impact publiques |
| GET  | `/api/programs` | Liste des programmes |
| GET  | `/api/articles` | Blog public |
| POST | `/api/donations/checkout` | Créer session Stripe |
| POST | `/api/donations/webhook` | Webhook Stripe |
| POST | `/api/contacts` | Formulaire contact |
| POST | `/api/newsletter/subscribe` | Newsletter |
| GET  | `/api/admin/dashboard` | Dashboard admin (auth) |
| GET  | `/api/donations/export` | Export CSV (auth) |

---

## 🎨 Identité visuelle

| Couleur | Hex | Usage |
|---------|-----|-------|
| Violet ARAILD | `#7B2D8B` | Couleur principale |
| Bleu confiance | `#1B4FD8` | Secondaire |
| Vert impact | `#16A34A` | Environnement, succès |
| Rouge urgence | `#DC2626` | CTA dons, alertes |

---

## 🤝 Support

- Email technique : contact@araild.com
- Site : www.araild.com
- Téléphone : +237 650 70 83 30

---

*Développé pour ARAILD — Action pour la Recherche et l'Appui aux Initiatives Locales de Développement*
*Bafoussam, Cameroun — 2024*
