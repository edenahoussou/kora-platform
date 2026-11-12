# Deploiement manuel

En cas de panne CI/CD, le deploiement peut se faire manuellement via SSH au VPS.

## Pre-requis

- Acces SSH au VPS : `ssh kora-vps` (alias configure dans `~/.ssh/config`)
- Le tunnel Pangolin doit etre up sur le VPS pour que le VPS puisse pull depuis Gitea
- Docker doit etre installe sur le VPS

## Deploiement staging manuel

```bash
ssh kora-vps
cd /opt/kora-staging
git pull origin main
docker compose -f docker-compose.staging.yml pull
docker compose -f docker-compose.staging.yml up -d --no-build
docker compose -f docker-compose.staging.yml exec app php artisan migrate --force
docker compose -f docker-compose.staging.yml exec app php artisan db:seed --class=KoraBusRolePermissionSeeder --force
docker compose -f docker-compose.staging.yml exec app php artisan db:seed --class=WalletLoyaltyRuleVersionSeeder --force
docker compose -f docker-compose.staging.yml exec app php artisan config:cache
docker compose -f docker-compose.staging.yml exec app php artisan route:cache
docker compose -f docker-compose.staging.yml exec app php artisan view:cache
```

Verifier : `curl -sS -o /dev/null -w "HTTP %{http_code}" http://51.255.196.129:8080/`

## Deploiement manuel avec build d'image (CI en panne)

Les fichiers compose referencent l'image par son nom complet registry :
`ghcr.io/edenahoussou/kora-ops-web:latest` (prod) et `ghcr.io/edenahoussou/kora-ops-web:staging` (staging).

**Piege** : builder avec un tag court (`-t kora-ops-web:latest`) cree une image
distincte de celle que le compose attend — `docker compose up -d` ne detecte
alors aucun changement et ne recree jamais le conteneur app (silencieusement).
Toujours builder avec le tag exact du compose.

Depuis le passage en **pull-only** (voir « Stockage Docker du VPS »), le compose
ne contient plus de section `build:` : un build local doit etre **pousse dans
GHCR** puis tire, sinon `pull` ecrasera l'image locale.

```bash
# Production : build + push du tag attendu par le compose
docker buildx build -f infra/docker/kora-ops-web/Dockerfile.prod \
    --platform linux/amd64 \
    --push \
    -t ghcr.io/edenahoussou/kora-ops-web:latest .

# Puis sur le VPS
cd /opt/kora-prod
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --no-build

# Staging : meme chose avec le tag :staging
docker buildx build -f infra/docker/kora-ops-web/Dockerfile.prod \
    --platform linux/amd64 --push \
    -t ghcr.io/edenahoussou/kora-ops-web:staging .
```

Si un build a malgre tout tourne sur le VPS, purger le cache dans la foulee
pour ne pas recreer 50 Go :

```bash
sudo docker builder prune -f --filter until=168h
```

Verifier que le nouveau code tourne reellement (ex. date de creation du conteneur
ou presence d'un fichier modifie) : `docker inspect kora-prod-app --format '{{.Created}}'`.

## Stockage Docker du VPS

Le VPS ne construit pas d'image en regime normal : la CI build (cache GitHub
Actions) et pousse sur GHCR, le VPS se contente de `pull`. Cette regle evite
l'accumulation de cache de build sur `/` (incident du 12/11/2026 : 51 Go de
cache, disque a 81 %).

- `docker-compose.{staging,prod}.yml` : `pull_policy: always`, aucune section
  `build:` (app, nginx et sync-public viennent de GHCR).
- `KORA_IMAGE_TAG` (optionnel, dans `.env.staging` / `.env.prod`) : force un tag
  precis pour un rollback, ex. `KORA_IMAGE_TAG=staging-<sha>` ou `v1.0.4`.
  Sans valeur, `staging` (staging) et `latest` (prod) sont utilises.
- `daemon.json` (`infra/vps/daemon.json`) : cache BuildKit plafonne a 8 Go,
  rotation des logs conteneurs (20 Mo x 3).
- Purge hebdomadaire `kora-docker-prune.timer` : cache de build > 7 jours,
  images dangling, `staging-<sha>` au-dela des 2 plus recents, builds locaux
  `kora-ops-wallet-candidate:*`, journaux systemd.

### Installer le plafond et la purge

```bash
# 1. Plafond du cache + rotation des logs (redemarre Docker : coupure de quelques secondes)
scp infra/vps/daemon.json kora-vps:/tmp/daemon.json
ssh kora-vps 'sudo cp /etc/docker/daemon.json /etc/docker/daemon.json.bak 2>/dev/null; \
    sudo mv /tmp/daemon.json /etc/docker/daemon.json && sudo systemctl restart docker'

# 2. Purge hebdomadaire
scp infra/vps/prune-docker.sh kora-vps:/tmp/prune-docker.sh
scp infra/vps/kora-docker-prune.service infra/vps/kora-docker-prune.timer kora-vps:/tmp/
ssh kora-vps 'sudo install -m 755 /tmp/prune-docker.sh /usr/local/bin/kora-docker-prune.sh && \
    sudo install -m 644 /tmp/kora-docker-prune.service /etc/systemd/system/ && \
    sudo install -m 644 /tmp/kora-docker-prune.timer /etc/systemd/system/ && \
    sudo systemctl daemon-reload && sudo systemctl enable --now kora-docker-prune.timer'

# 3. Verification
ssh kora-vps 'docker buildx du | tail -2; systemctl list-timers | grep kora-docker-prune; df -h /'
```

Le resultat de chaque purge est journalise dans `/var/log/kora-docker-prune.log`.

**Apres le redemarrage du daemon** : verifier que `kora-prod-app` est bien
remonte. Horizon tente un arret propre en interrogeant Redis ; si Redis redemarre
au meme moment, l'exception fait sortir supervisord et le conteneur reste en
`Exited (0)` — `restart: unless-stopped` ne le relance pas (constate le
12/11/2026). Remede :

```bash
ssh kora-vps 'docker start kora-prod-app'
ssh kora-vps "curl -sS -o /dev/null -w 'prod %{http_code}\n' http://127.0.0.1:9080/api/v1/health"
```

Les conteneurs deja crees conservent leur ancienne configuration de logs : la
rotation `20 Mo x 3` s'applique aux conteneurs recrees au prochain deploiement.

L'espace disque est surveille par `health:check` (`UsedDiskSpaceCheck`) :
*warning* a 75 %, *echec* (donc notification) a 85 %.

## Endpoints de health check

L'API expose deux endpoints dedies au monitoring et au load balancing :

| Endpoint | Usage | Reponse |
| --- | --- | --- |
| `GET /api/v1/health` | Monitoring : statut DB, cache (Redis), version API | `200` si tout est ok, `503` si degrade |
| `GET /api/v1/health/ping` | Liveness probe load balancer | `204 No Content` tant que le process repond |

### Verifier le health staging

```bash
# Statut complet (DB + Redis + version)
curl -sS http://51.255.196.129:8080/api/v1/health | jq

# Ping load balancer (doit renvoyer 204)
curl -sS -o /dev/null -w "HTTP %{http_code}" http://51.255.196.129:8080/api/v1/health/ping
```

Exemple de reponse saine :

```json
{
  "status": "ok",
  "service": "kora-platform",
  "scope": "mvp-bus",
  "version": "v1",
  "timestamp": "2026-08-13T17:30:00.000000Z",
  "checks": {
    "database": { "status": "ok", "latency_ms": 2 },
    "cache": { "status": "ok", "latency_ms": 1 }
  }
}
```

## Domaine staging + SSL/TLS (Let's Encrypt)

L'API et l'OPS Web vivent dans la meme application Laravel (UI Inertia sous `/`
et REST API sous `/api/v1/*`). Un seul domaine staging suffit.

Domaines valides :

- Staging : `https://staging.koraexpress.app`
- Prod    : `https://prod.koraexpress.app`

Le staging est exposé en HTTP sur le port `8080` du VPS. Pour le domaine
`staging.koraexpress.app` avec un certificat Let's Encrypt, on utilise
un reverse proxy Nginx host + certbot sur le VPS (en dehors des conteneurs).

### 1. DNS

Pointer un enregistrement A vers l'IP du VPS :

```
staging.koraexpress.app.  IN  A  51.255.196.129
prod.koraexpress.app.     IN  A  51.255.196.129
```

### 2. Nginx host (reverse proxy vers le conteneur)

Installer Nginx sur le VPS puis créer `/etc/nginx/sites-available/kora-staging` :

```nginx
server {
    listen 80;
    server_name staging.koraexpress.app;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/kora-staging /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 3. Certificat SSL (certbot)

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d staging.koraexpress.app -d prod.koraexpress.app --redirect
```

certbot genere le certificat et modifie le vhost pour HTTPS avec renouvellement
automatique (`systemctl list-timers | grep certbot`).

### 4. Verifier en HTTPS

```bash
# UI OPS
curl -sS -o /dev/null -w "HTTP %{http_code}" https://staging.koraexpress.app/dashboard

# API health (statut complet)
curl -sS https://staging.koraexpress.app/api/v1/health | jq

# API ping (load balancer, doit renvoyer 204)
curl -sS -o /dev/null -w "HTTP %{http_code}" https://staging.koraexpress.app/api/v1/health/ping
```

## Deploiement production manuel

```bash
ssh kora-vps
cd /opt/kora-prod

# 1. Backup DB
TIMESTAMP=$(date +%Y%m%d%H%M%S)
docker compose -f docker-compose.prod.yml exec -T postgres pg_dump -U kora kora_prod > backups/db-backup-$TIMESTAMP.sql

# 2. Pull du tag cible
git fetch --tags
git checkout v0.1.0  # remplacer par le tag voulu

# 3. Build et restart
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=KoraBusRolePermissionSeeder --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=WalletLoyaltyRuleVersionSeeder --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

Verifier : `curl -sS -o /dev/null -w "HTTP %{http_code}" http://51.255.196.129/`

## Compte de revue store (fenetre temporaire)

Avant une soumission Apple/Google, ouvrir la fenetre de revue en production :

```bash
cd /opt/kora-prod
# 1. .env.prod : STORE_REVIEW_ENABLED=true, STORE_REVIEW_OTP_CODE=<code campagne>, whitelist a jour
docker compose -f docker-compose.prod.yml up -d            # env_file lu au demarrage
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --class=StoreReviewAccountSeeder --force
```

Fermer la fenetre des l'acceptation de la fiche : `STORE_REVIEW_ENABLED=false`, vider
`STORE_REVIEW_OTP_CODE`, `docker compose up -d`, `config:cache`, `queue:restart`. Procedure complete
(staging, comptes, recette, depannage) : `docs/runbooks/store-review.md`.

## Structure des repertoires VPS

```
/opt/kora-staging/     # clone du repo + .env.staging
  docker-compose.staging.yml
  .env.staging
/opt/kora-prod/        # clone du repo + .env.prod
  docker-compose.prod.yml
  .env.prod
  backups/             # backups DB automatiques
```

## Verifier l'etat des services

```bash
ssh kora-vps
docker ps                              # tous les conteneurs
docker compose -f /opt/kora-staging/docker-compose.staging.yml ps
docker compose -f /opt/kora-prod/docker-compose.prod.yml ps
```

## Logs

Les conteneurs staging/production forcent `LOG_STACK=docker` (channel stack
`daily` + `stderr`) : les logs Laravel vont a la fois dans
`storage/logs/laravel-*.log` (persiste via le volume) et sur stderr, capturables
par `docker logs`.

```bash
# Staging
ssh kora-vps "docker logs kora-staging-app --tail 50 -f"
ssh kora-vps "docker logs kora-staging-nginx --tail 50 -f"

# Logs applicatif fichier (rotation quotidienne, 14 jours)
ssh kora-vps "docker compose -f /opt/kora-staging/docker-compose.staging.yml exec app tail -n 100 storage/logs/laravel-$(date +%Y-%m-%d).log"

# Production
ssh kora-vps "docker logs kora-prod-app --tail 50 -f"
ssh kora-vps "docker logs kora-prod-nginx --tail 50 -f"
```

## Tunnel Pangolin sur le VPS

Le VPS doit avoir le tunnel Pangolin up pour atteindre Gitea (pull, push d'images).

```bash
ssh kora-vps "sudo systemctl status pangolin-client"
ssh kora-vps "sudo systemctl restart pangolin-client"
ssh kora-vps "curl -sS -o /dev/null -w 'HTTP %{http_code}' https://gitea.mezagency.fr/"
```
