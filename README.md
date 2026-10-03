1. Copie el archivo .env.example a .env

```
    cp .env.example .env
```

2. Rellene los datos del .env

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=root
DB_PASSWORD=
```

4. Ejecute Contenedor de Laravel

```
    docker-compose up -d
```

5. Ejecute Comandos Adicionales del Contenedor de Laravel

```
    docker exec digital-world composer install

    docker exec digital-world php artisan key:generate

    docker exec digital-world php artisan storage:link

    docker exec digital-world chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

    docker exec digital-world chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

    docker exec digital-world php artisan optimize

    docker exec digital-world npm install

    docker exec digital-world npm run build
```

Si cambió el `Dockerfile`, levante los contenedores con `docker-compose up -d --build`.
