#!/bin/bash
set -e

echo "🚀 ARAILD Platform — Déploiement"
echo "================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Check .env exists
if [ ! -f .env ]; then
  echo -e "${RED}❌ Fichier .env manquant. Copiez .env.example et configurez-le.${NC}"
  exit 1
fi

# Check required vars
required_vars=("DATABASE_URL" "JWT_SECRET" "STRIPE_SECRET_KEY" "STRIPE_WEBHOOK_SECRET")
for var in "${required_vars[@]}"; do
  if ! grep -q "^${var}=" .env || grep -q "^${var}=$" .env; then
    echo -e "${RED}❌ Variable manquante ou vide : ${var}${NC}"
    exit 1
  fi
done

echo -e "${GREEN}✅ Variables d'environnement OK${NC}"

# Pull latest code
echo -e "\n${YELLOW}📥 Mise à jour du code...${NC}"
git pull origin main

# Build & deploy
echo -e "\n${YELLOW}🐳 Build Docker...${NC}"
docker-compose build --no-cache

echo -e "\n${YELLOW}▶️  Démarrage des services...${NC}"
docker-compose up -d

# Wait for DB
echo -e "\n${YELLOW}⏳ Attente de la base de données...${NC}"
sleep 8
until docker exec araild_db pg_isready -U araild_user -d araild_db 2>/dev/null; do
  echo "  Base de données non prête, attente..."
  sleep 3
done

echo -e "${GREEN}✅ Base de données prête${NC}"

# Run migrations
echo -e "\n${YELLOW}🔄 Migrations...${NC}"
docker exec araild_api npx prisma migrate deploy

# Seed if first deploy
if [ "$1" = "--seed" ]; then
  echo -e "\n${YELLOW}🌱 Seed des données initiales...${NC}"
  docker exec araild_api npm run db:seed
fi

# Health check
echo -e "\n${YELLOW}🏥 Vérification santé...${NC}"
sleep 3
if curl -sf http://localhost:4000/health > /dev/null; then
  echo -e "${GREEN}✅ API opérationnelle${NC}"
else
  echo -e "${RED}⚠️  API non accessible sur le port 4000${NC}"
fi

echo -e "\n${GREEN}🎉 Déploiement terminé !${NC}"
echo "  Site web : http://localhost:3000"
echo "  API      : http://localhost:4000"
echo "  Admin    : http://localhost:3000/admin/login"
echo ""
echo "Logs: docker-compose logs -f"
