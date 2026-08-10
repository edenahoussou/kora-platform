# Local Development

```bash
cd /var/www/html/kora-platform
docker compose up -d kora-ops-web

cd apps/kora-ops-web
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan test
```

Si le PHP local ne contient pas `pdo_pgsql`, utiliser le conteneur KORA OPS Web :

```bash
cd /var/www/html/kora-platform
docker compose build kora-ops-web
docker compose run --rm kora-ops-web php artisan migrate
docker compose run --rm kora-ops-web php artisan test
```

Services locaux :

- PostgreSQL: `127.0.0.1:5432`, database `kora`, user `kora`, password `secret`.
- Redis: `127.0.0.1:6379`.
- Mailpit: SMTP `127.0.0.1:1025`, UI `http://127.0.0.1:8025`.

Pre-requis PHP local, uniquement si les commandes Laravel sont executees hors Docker :

- `pdo_pgsql` est requis pour executer les migrations PostgreSQL depuis Laravel.
- Redis utilise `predis/predis`, donc l'extension PHP `redis` n'est pas obligatoire.
