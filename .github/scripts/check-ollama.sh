#!/bin/bash
# Vérifie qu'Ollama tourne sur l'hôte et expose les modèles requis par le
# backend (voir README.md #Prérequis) avant de démarrer la stack -- Ollama
# n'est pas conteneurisé ici, un oubli se traduit sinon par des erreurs de
# connexion tardives une fois l'app démarrée.
set -euo pipefail

OLLAMA_URL="${OLLAMA_BASE_URL:-http://localhost:11434}"
REQUIRED_MODELS=("qwen3.6" "mxbai-embed-large")

TAGS_JSON=$(curl -sf --max-time 3 "$OLLAMA_URL/api/tags" 2>/dev/null) || {
  echo "❌ Ollama ne répond pas sur $OLLAMA_URL -- démarrez-le avant 'make start' (tourne sur l'hôte, pas en conteneur : https://ollama.ai/download)."
  exit 1
}

MISSING=()
for model in "${REQUIRED_MODELS[@]}"; do
  if ! MODEL="$model" python3 -c "
import json, os, sys
models = [m['name'].split(':')[0] for m in json.load(sys.stdin).get('models', [])]
sys.exit(0 if os.environ['MODEL'] in models else 1)
" <<<"$TAGS_JSON" 2>/dev/null; then
    MISSING+=("$model")
  fi
done

if [ ${#MISSING[@]} -gt 0 ]; then
  echo "❌ Modèle(s) Ollama manquant(s) : ${MISSING[*]}"
  for model in "${MISSING[@]}"; do
    echo "   ollama pull $model"
  done
  exit 1
fi

echo "✅ Ollama tourne et expose tous les modèles requis (${REQUIRED_MODELS[*]})."
