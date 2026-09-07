#!/bin/sh

set -u

# Le script s'exécute depuis le dossier où il se trouve.
# Place-le de préférence à la racine du projet.
cd "$(dirname "$0")" || exit 1

FAIL_FAST="${FAIL_FAST:-0}"
KEEP_LOGS="${KEEP_LOGS:-0}"

TMP_DIR="$(mktemp -d)"
FAILED_STEPS=""
FAILED_COUNT=0

cleanup() {
    if [ "$KEEP_LOGS" = "1" ]; then
        echo "Logs conservés dans : $TMP_DIR"
    else
        rm -rf "$TMP_DIR"
    fi
}

trap cleanup EXIT

# Extensions recherchées pour essayer de retrouver les fichiers concernés.
EXT_REGEX='php|phtml|blade\.php|js|jsx|mjs|cjs|ts|tsx|vue|json|css|scss|sass|less|html|htm|md|markdown|yml|yaml|xml'

slugify() {
    printf '%s' "$1" |
        tr -c 'A-Za-z0-9' '-' |
        sed -e 's/--*/-/g' -e 's/^-//' -e 's/-$//'
}

extract_files() {
    _log="$1"

    # On nettoie un peu la sortie avant d'extraire les chemins.
    # Le sed supprime par exemple la ligne PHPStan :
    # Note: Using configuration file .../phpstan.neon
    sed -e '/Using configuration file/d' "$_log" 2>/dev/null |
        grep -Eo "[A-Za-z0-9_@./~-]+\.($EXT_REGEX)" |
        sed -e 's/[.,;:"]*$//' |
        grep -Ev '(^|/)(node_modules|vendor)/' |
        sort -u

    return 0
}

run_step() {
    _cmd="$1"
    _slug="$(slugify "$_cmd")"
    _log="$TMP_DIR/$_slug.log"

    printf '\n=====================================================\n'
    printf '▶ %s\n' "$_cmd"
    printf '=====================================================\n'

    sh -c "$_cmd" >"$_log" 2>&1
    _rc=$?

    if [ "$_rc" -eq 0 ]; then
        printf '✅ OK\n'
        return 0
    fi

    FAILED_COUNT=$((FAILED_COUNT + 1))

    if [ -z "$FAILED_STEPS" ]; then
        FAILED_STEPS="- $_cmd"
    else
        FAILED_STEPS="$FAILED_STEPS
- $_cmd"
    fi

    printf '❌ La commande a échoué avec le code %s\n\n' "$_rc"

    printf '--- Sortie de la commande ---\n'
    cat "$_log"

    printf '\n--- Fichiers probablement concernés ---\n'
    _files="$(extract_files "$_log")"

    if [ -n "$_files" ]; then
        printf '%s\n' "$_files"
    else
        echo "(aucun fichier détecté automatiquement, regarde la sortie ci-dessus)"
    fi

    if [ "$FAIL_FAST" = "1" ]; then
        exit "$_rc"
    fi

    return "$_rc"
}

command -v composer >/dev/null 2>&1 || {
    echo "❌ composer introuvable"
    exit 127
}

command -v npm >/dev/null 2>&1 || {
    echo "❌ npm introuvable"
    exit 127
}

run_step "composer lint:check"
run_step "composer types:check"
run_step "npm run lint:check"
run_step "npm run types:check"
run_step "npm run format:check"

printf '\n====================== Résumé ======================\n'

if [ "$FAILED_COUNT" -gt 0 ]; then
    printf '❌ %s check(s) en erreur :\n' "$FAILED_COUNT"
    printf '%s\n' "$FAILED_STEPS"
    exit 1
fi

printf '✅ Tous les checks sont OK\n'