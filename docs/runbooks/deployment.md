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
docker compose -f docker-compose.staging.yml build app
docker compose -f docker-compose.staging.yml up -d
docker compose -f docker-compose.staging.yml exec app php artisan migrate --force
docker compose -f docker-compose.staging.yml exec app php artisan config:cache
docker compose -f docker-compose.staging.yml exec app php artisan route:cache
docker compose -f docker-compose.staging.yml exec app php artisan view:cache
```

Verifier : `curl -sS -o /dev/null -w "HTTP %{http_code}" http://51.255.196.129:8080/`

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

> `app-staging.<domaine-final>` est un placeholder : remplacer par le domaine
> choisi quand il sera fixe. Le sous-domaine `app-staging` encode
> l'environnement, le domaine racine encode le produit.

- UI OPS : `https://app-staging.<domaine-final>/dashboard`
- API    : `https://app-staging.<domaine-final>/api/v1/health`

Le staging est exposé en HTTP sur le port `8080` du VPS. Pour le domaine
`app-staging.<domaine-final>` avec un certificat Let's Encrypt, on utilise
un reverse proxy Nginx host + certbot sur le VPS (en dehors des conteneurs).

### 1. DNS

Pointer un enregistrement A vers l'IP du VPS :

```
app-staging.<domaine-final>.  IN  A  51.255.196.129
```

### 2. Nginx host (reverse proxy vers le conteneur)

Installer Nginx sur le VPS puis créer `/etc/nginx/sites-available/kora-staging` :

```nginx
server {
    listen 80;
    server_name app-staging.<domaine-final>;

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
sudo certbot --nginx -d app-staging.<domaine-final> --redirect
```

certbot genere le certificat et modifie le vhost pour HTTPS avec renouvellement
automatique (`systemctl list-timers | grep certbot`).

### 4. Verifier en HTTPS

```bash
# UI OPS
curl -sS -o /dev/null -w "HTTP %{http_code}" https://app-staging.<domaine-final>/dashboard

# API health (statut complet)
curl -sS https://app-staging.<domaine-final>/api/v1/health | jq

# API ping (load balancer, doit renvoyer 204)
curl -sS -o /dev/null -w "HTTP %{http_code}" https://app-staging.<domaine-final>/api/v1/health/ping
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
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

Verifier : `curl -sS -o /dev/null -w "HTTP %{http_code}" http://51.255.196.129/`

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
