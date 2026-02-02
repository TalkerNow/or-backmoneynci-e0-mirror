#!/bin/bash

# Script pour installer les Git hooks dans le projet

echo "📥 Installation des Git hooks..."

# Créer le répertoire hooks s'il n'existe pas
mkdir -p .git/hooks

# Copier le hook pre-push
cat > .git/hooks/pre-push << 'EOF'
#!/bin/bash

# Git pre-push hook pour valider le code avant de push
# Ce hook lance automatiquement le script de validation

echo "🔄 Lancement de la validation avant push..."
echo ""

# Exécuter le script de validation
./scripts/validate.sh

# Si le script de validation échoue, empêcher le push
if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Le push a été annulé car la validation a échoué."
    echo "💡 Corrigez les erreurs et réessayez."
    echo "💡 Pour bypass la validation (déconseillé): git push --no-verify"
    exit 1
fi

echo ""
echo "✅ Validation réussie. Push en cours..."
exit 0
EOF

# Rendre le hook exécutable
chmod +x .git/hooks/pre-push

echo "✅ Git hook pre-push installé avec succès !"
echo ""
echo "📝 Le hook sera exécuté automatiquement avant chaque push."
echo "💡 Pour désactiver temporairement : git push --no-verify"
