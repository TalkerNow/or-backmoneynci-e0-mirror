#!/bin/bash
set -e

echo "🔍 Validation du build PHP..."
echo ""

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction pour afficher les erreurs
error() {
    echo -e "${RED}❌ $1${NC}"
    exit 1
}

# Fonction pour afficher les succès
success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Fonction pour afficher les avertissements
warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# 1. Vérifier que Docker est lancé
echo "📦 Vérification de Docker..."
if ! docker info > /dev/null 2>&1; then
    error "Docker n'est pas lancé. Démarrez Docker et réessayez."
fi
success "Docker est lancé"
echo ""

# 2. Vérifier que les conteneurs sont up
echo "🐳 Démarrage des conteneurs si nécessaire..."
if ! docker ps | grep -q "optionret_php82_test"; then
    docker compose up -d
    echo "⏳ Attente du démarrage des conteneurs..."
    sleep 8
fi
success "Conteneurs prêts"
echo ""

# 3. CRITIQUE : Vérifier la syntaxe PHP de tous les fichiers
echo "🔍 Vérification de la syntaxe PHP..."
echo "   Analyse de app/..."
SYNTAX_ERRORS=$(docker compose exec -T php-test sh -c 'for file in $(find app -name "*.php"); do php -l "$file" 2>&1 | grep -v "No syntax errors" | grep -v "Deprecated:" || true; done')
if [ ! -z "$SYNTAX_ERRORS" ]; then
    echo -e "${RED}Erreurs de syntaxe détectées dans app/:${NC}"
    echo "$SYNTAX_ERRORS"
    error "Corrigez les erreurs de syntaxe ci-dessus"
fi

echo "   Analyse de routes/..."
SYNTAX_ERRORS=$(docker compose exec -T php-test sh -c 'for file in $(find routes -name "*.php"); do php -l "$file" 2>&1 | grep -v "No syntax errors" | grep -v "Deprecated:" || true; done')
if [ ! -z "$SYNTAX_ERRORS" ]; then
    echo -e "${RED}Erreurs de syntaxe détectées dans routes/:${NC}"
    echo "$SYNTAX_ERRORS"
    error "Corrigez les erreurs de syntaxe ci-dessus"
fi

echo "   Analyse de config/..."
SYNTAX_ERRORS=$(docker compose exec -T php-test sh -c 'for file in $(find config -name "*.php"); do php -l "$file" 2>&1 | grep -v "No syntax errors" | grep -v "Deprecated:" || true; done')
if [ ! -z "$SYNTAX_ERRORS" ]; then
    echo -e "${RED}Erreurs de syntaxe détectées dans config/:${NC}"
    echo "$SYNTAX_ERRORS"
    error "Corrigez les erreurs de syntaxe ci-dessus"
fi

success "Aucune erreur de syntaxe PHP"
echo ""

# 4. CRITIQUE : Vérifier que Composer peut installer les dépendances
echo "📦 Vérification de Composer..."
COMPOSER_VALIDATE=$(docker compose exec -T php-test composer validate --no-check-publish 2>&1)
if ! echo "$COMPOSER_VALIDATE" | grep -q "valid"; then
    echo -e "${RED}Problème avec composer.json ou composer.lock:${NC}"
    echo "$COMPOSER_VALIDATE"
    error "Corrigez les erreurs Composer ci-dessus"
fi

# Vérifier que l'installation fonctionne
if ! docker compose exec -T php-test test -f vendor/autoload.php; then
    echo "   Installation des dépendances Composer..."
    COMPOSER_INSTALL=$(docker compose exec -T php-test composer install --no-interaction --optimize-autoloader 2>&1)
    if [ $? -ne 0 ]; then
        echo -e "${RED}Erreur lors de l'installation Composer:${NC}"
        echo "$COMPOSER_INSTALL"
        error "Impossible d'installer les dépendances"
    fi
fi

# Vérifier l'autoload
AUTOLOAD_OUTPUT=$(docker compose exec -T php-test composer dump-autoload --optimize 2>&1)
if [ $? -ne 0 ]; then
    echo -e "${RED}Erreur avec l'autoload Composer:${NC}"
    echo "$AUTOLOAD_OUTPUT"
    error "Problème avec l'autoload"
fi

success "Dépendances Composer valides"
echo ""

# 5. CRITIQUE : Vérifier que l'application Laravel démarre sans erreur
echo "🚀 Vérification du démarrage de Laravel..."

# Tester que artisan fonctionne
ARTISAN_VERSION=$(docker compose exec -T php-test php artisan --version 2>&1)
if [ $? -ne 0 ]; then
    echo -e "${RED}Erreur au démarrage de Laravel:${NC}"
    echo "$ARTISAN_VERSION"
    error "Laravel ne démarre pas correctement"
fi

# Vérifier la configuration
CONFIG_CLEAR=$(docker compose exec -T php-test php artisan config:clear 2>&1)
if [ $? -ne 0 ]; then
    echo -e "${RED}Erreur de configuration Laravel:${NC}"
    echo "$CONFIG_CLEAR"
    error "Problème avec la configuration"
fi

success "Laravel démarre correctement"
echo ""

# 6. CRITIQUE : Vérifier que les routes se chargent sans erreur
echo "🛣️  Vérification des routes..."
ROUTE_OUTPUT=$(docker compose exec -T php-test php artisan route:list 2>&1)
ROUTE_EXIT_CODE=$?

# Vérifier le code de sortie (plus fiable que de parser la sortie)
if [ $ROUTE_EXIT_CODE -ne 0 ]; then
    echo -e "${RED}$ROUTE_OUTPUT${NC}"
    error "Erreurs dans les routes Laravel"
fi

# Compter les routes en cherchant les lignes qui contiennent les méthodes HTTP
ROUTE_COUNT=$(echo "$ROUTE_OUTPUT" | grep -c "GET\|POST\|PUT\|PATCH\|DELETE" | head -1 || echo "0")
if [ "$ROUTE_COUNT" -eq 0 ]; then
    warning "Aucune route détectée (vérifiez routes/api.php et routes/web.php)"
else
    success "$ROUTE_COUNT routes chargées avec succès"
fi
echo ""

# 7. CRITIQUE : Vérifier que les classes et namespaces sont valides
echo "🔄 Vérification de l'autoload des classes..."
docker compose exec -T php-test php artisan clear-compiled > /dev/null 2>&1 || true

OPTIMIZE_CLEAR=$(docker compose exec -T php-test php artisan optimize:clear 2>&1)
if [ $? -ne 0 ]; then
    warning "Certains caches n'ont pas pu être nettoyés"
    echo "$OPTIMIZE_CLEAR" | head -5
fi

# Tenter de charger toutes les classes
AUTOLOAD_STRICT=$(docker compose exec -T php-test composer dump-autoload --optimize --strict-psr 2>&1)
if [ $? -ne 0 ]; then
    echo -e "${RED}Problème avec les namespaces PSR-4:${NC}"
    echo "$AUTOLOAD_STRICT" | grep -E "error|Error|PSR-4|Class|namespace" || echo "$AUTOLOAD_STRICT"
    error "Corrigez les namespaces ci-dessus"
fi

success "Autoload des classes valide"
echo ""

# 8. OPTIONNEL : Tests PHPUnit
if [ -f "phpunit.xml" ]; then
    echo "🧪 Lancement des tests PHPUnit..."
    TEST_OUTPUT=$(docker compose exec -T php-test php artisan test --stop-on-failure 2>&1 || true)
    if echo "$TEST_OUTPUT" | grep -qE "FAILURES!|ERRORS!"; then
        echo -e "${RED}$TEST_OUTPUT${NC}"
        error "Tests PHPUnit échoués"
    elif echo "$TEST_OUTPUT" | grep -q "Tests:"; then
        success "Tests PHPUnit réussis"
    else
        warning "Aucun test trouvé ou problème lors de l'exécution"
    fi
    echo ""
fi

# 9. Récapitulatif
echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Build validé avec succès !${NC}"
echo -e "${GREEN}   - Syntaxe PHP : OK${NC}"
echo -e "${GREEN}   - Dépendances Composer : OK${NC}"
echo -e "${GREEN}   - Laravel démarre : OK${NC}"
echo -e "${GREEN}   - Routes chargées : OK${NC}"
echo -e "${GREEN}   - Classes autoload : OK${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
echo ""
echo "💡 Votre code est prêt à être déployé !"
echo ""
