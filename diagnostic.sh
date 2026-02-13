#!/bin/bash

echo "╔════════════════════════════════════════════════════╗"
echo "║   DIAGNOSTIC RECONNAISSANCE FACIALE - SYMFONY      ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1️⃣  VÉRIFICATION DES FICHIERS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "src/EventSubscriber/FaceAuthSubscriber.php" ]; then
    echo -e "${GREEN}✅ FaceAuthSubscriber.php${NC}"
else
    echo -e "${RED}❌ FaceAuthSubscriber.php MANQUANT${NC}"
fi

if [ -f "src/Controller/FaceAuthController.php" ]; then
    echo -e "${GREEN}✅ FaceAuthController.php${NC}"
else
    echo -e "${RED}❌ FaceAuthController.php MANQUANT${NC}"
fi

if [ -f "templates/admin/security/face_verify_required.html.twig" ]; then
    echo -e "${GREEN}✅ face_verify_required.html.twig${NC}"
else
    echo -e "${RED}❌ face_verify_required.html.twig MANQUANT${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2️⃣  VÉRIFICATION DES ROUTES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php bin/console debug:router | grep -E "(face_verify|face_login)" || echo -e "${RED}❌ Aucune route trouvée${NC}"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3️⃣  VÉRIFICATION DE L'ENTITY USER"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if grep -q "getFaceDescriptor" src/Entity/User.php; then
    echo -e "${GREEN}✅ User::getFaceDescriptor() existe${NC}"
else
    echo -e "${RED}❌ User::getFaceDescriptor() MANQUANT${NC}"
fi

if grep -q "setFaceDescriptor" src/Entity/User.php; then
    echo -e "${GREEN}✅ User::setFaceDescriptor() existe${NC}"
else
    echo -e "${RED}❌ User::setFaceDescriptor() MANQUANT${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4️⃣  DERNIÈRES ERREURS DANS LES LOGS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "var/log/dev.log" ]; then
    echo -e "${YELLOW}📋 Dernières lignes du log dev:${NC}"
    tail -n 30 var/log/dev.log | grep -E "(ERROR|CRITICAL|face|Face)" || echo "Aucune erreur récente"
else
    echo -e "${RED}❌ Fichier var/log/dev.log introuvable${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5️⃣  VÉRIFICATION DU RÉPERTOIRE FACES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -d "public/uploads/faces" ]; then
    echo -e "${GREEN}✅ Répertoire public/uploads/faces existe${NC}"
    echo "   Permissions: $(stat -c '%A' public/uploads/faces 2>/dev/null || stat -f '%Sp' public/uploads/faces)"
    echo "   Nombre de fichiers: $(ls -1 public/uploads/faces 2>/dev/null | wc -l)"
else
    echo -e "${YELLOW}⚠️  Répertoire public/uploads/faces n'existe pas${NC}"
    echo "   Création..."
    mkdir -p public/uploads/faces
    chmod 777 public/uploads/faces
    echo -e "${GREEN}✅ Répertoire créé${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6️⃣  VÉRIFICATION BASE DE DONNÉES"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php bin/console doctrine:schema:validate 2>&1 | head -n 10

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7️⃣  NETTOYAGE DU CACHE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php bin/console cache:clear
echo -e "${GREEN}✅ Cache nettoyé${NC}"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "8️⃣  TEST DE LA ROUTE face_verify_check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "Tentative de requête POST vers /face-verify-check..."
curl -X POST http://localhost:8000/face-verify-check \
  -H "Content-Type: application/json" \
  -d '{"test": true}' \
  -s -o /tmp/face_test_response.txt \
  -w "Status: %{http_code}\n"

echo ""
echo "Réponse du serveur:"
head -n 20 /tmp/face_test_response.txt

echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║              FIN DU DIAGNOSTIC                     ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo "💡 PROCHAINES ÉTAPES:"
echo "   1. Vérifiez les erreurs ci-dessus"
echo "   2. Consultez var/log/dev.log pour plus de détails"
echo "   3. Si erreur 500, regardez la stack trace complète"
echo ""
