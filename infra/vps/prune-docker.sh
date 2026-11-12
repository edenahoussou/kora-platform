#!/usr/bin/env bash
# Purge hebdomadaire du stockage Docker du VPS KORA : cache de build 7 jours,
# images obsolètes, journaux systemd. Les images en cours et les rollbacks sont conservés.
set -uo pipefail

LOG_FILE=/var/log/kora-docker-prune.log
REGISTRY_IMAGE='ghcr.io/edenahoussou/kora-ops-web'
KEEP_STAGING_TAGS=2

log() { printf '%s %s\n' "$(date -Is)" "$*" >> "$LOG_FILE"; }

log '=== début purge Docker ==='

docker builder prune -f --filter until=168h >> "$LOG_FILE" 2>&1 || log 'builder prune: échec'

# Tags staging-<sha> au-delà des N plus récents : re-tirables depuis GHCR si besoin.
mapfile -t staging_tags < <(
    docker images --format '{{.CreatedAt}}\t{{.Repository}}:{{.Tag}}' \
        | grep -E "	${REGISTRY_IMAGE}:staging-[0-9a-f]{7,40}$" \
        | sort -r \
        | cut -f2 || true
)

for ref in "${staging_tags[@]:KEEP_STAGING_TAGS}"; do
    [[ -z "$ref" ]] && continue
    if docker rmi "$ref" >> "$LOG_FILE" 2>&1; then
        log "image supprimée: $ref"
    else
        log "image conservée (utilisée par un conteneur): $ref"
    fi
done

# Builds locaux de diagnostic jamais déployés. Le rollback Wallet explicite est conservé.
mapfile -t obsolete < <(
    docker images --format '{{.Repository}}:{{.Tag}}' \
        | grep -E '^(kora-ops-wallet-candidate:|kora-ops-web:latest$)' || true
)

for ref in "${obsolete[@]}"; do
    [[ -z "$ref" ]] && continue
    if docker rmi "$ref" >> "$LOG_FILE" 2>&1; then
        log "image supprimée: $ref"
    else
        log "image conservée (utilisée par un conteneur): $ref"
    fi
done

# Les images supprimées laissent des couches orphelines : on les purge en dernier.
docker image prune -f >> "$LOG_FILE" 2>&1 || log 'image prune: échec'

journalctl --vacuum-size=200M >> "$LOG_FILE" 2>&1 || log 'journal vacuum: échec'

log "espace après purge: $(df -h / | awk 'NR==2 {print $3" utilisés, "$4" libres ("$5")"}')"
log '=== fin purge Docker ==='
