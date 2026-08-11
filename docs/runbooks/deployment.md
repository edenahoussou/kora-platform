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

```bash
# Staging
ssh kora-vps "docker logs kora-staging-app --tail 50 -f"
ssh kora-vps "docker logs kora-staging-nginx --tail 50 -f"

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
