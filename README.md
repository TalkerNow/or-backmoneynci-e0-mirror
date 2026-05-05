<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 1500 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Lancement du docker en local (dev)

### Premier démarrage et installation des hooks Git

**Installer les Git hooks pour validation automatique :**

```bash
./scripts/install-hooks.sh
```

Ce hook validera automatiquement votre code avant chaque push.

**Démarrer l'environnement local :**

```bash
docker compose up -d
```

Cela lit automatiquement :

- `docker-compose.yml` (configuration de base)
- `docker-compose.override.yml` (DB Docker `db`, nginx dev, montage `.env.local` → `.env`)

L'API sera disponible sur : **http://localhost:8000**

### Validation du code

**Valider manuellement avant de pusher :**

```bash
./scripts/validate.sh
```

Ce script vérifie :

- ✅ Docker est lancé
- ✅ Syntaxe PHP valide
- ✅ Dépendances Composer à jour
- ✅ Configuration Laravel valide
- ✅ Routes Laravel valides
- ✅ Tests PHPUnit (si présents)
- ✅ Permissions correctes

**Bypass la validation (déconseillé) :**

```bash
git push --no-verify
```

### CI/CD GitLab

Le fichier `.gitlab-ci.yml` lance automatiquement :

- **validate** : Validation de la syntaxe et configuration
- **test** : Tests PHPUnit
- **build** : Build des images Docker

Ces jobs se lancent automatiquement sur les branches `main`, `develop`, et les merge requests.

- (Optionnel) Charger les variables d'environnement de `.env.local` dans ton shell
  pour les scripts bash (ex: sync-db-from-prod.sh) :

  - `export $(grep -v '^#' .env.local | xargs)`
  - ⚠️ Ça ne change rien pour Laravel dans le conteneur. Laravel lit le fichier `.env`
    **du conteneur**, qui est monté depuis `.env.local` via `docker.compose.override.yml`.

- Démarrer l'environnement local :

  - `docker compose up -d`
    - lit automatiquement :
      - `docker-compose.yml`
      - `docker.compose.override.yml` (DB docker `db`, nginx dev, montage `.env.local` → `.env`)

- Migrations dev (DB locale dans Docker : service `db`, base `moneynci_local`) :
  - Appliquer les migrations :
    - `docker compose exec php-test php artisan migrate`
  - Réinitialiser complètement la base + seed :
    - `docker compose exec php-test php artisan migrate:fresh --seed`
  - Si tu modifies `.env.local`, vider le cache de config avant :
    - `docker compose exec php-test php artisan config:clear`

## Prod ACTUELLE (DB externe, hors Docker)

Sur le serveur de prod actuel, **uniquement** :

- Fichier d'environnement :

  - `.env` présent sur le serveur (APP_ENV=production)
  - Ne pas utiliser `.env.local` ni `export $(grep -v '^#' .env.local | xargs)` en prod.

- Démarrer les conteneurs :

  - `docker compose -f docker-compose.yml up -d`

- Migrations prod (DB externe, valeurs de `.env` en prod) :
  - Pour `php-prod` :
    - `docker compose exec php-prod php artisan migrate`
  - Pour l'environnement de test prod (`php-test` avec DB `moneynci_test`) :
    - `docker compose exec php-test php artisan migrate`

> Ne PAS utiliser `docker.compose.override.yml` ni `docker.compose.prod.yml` sur ce serveur.  
> En prod actuelle, la base reste externe (DB_HOST=host.docker.internal).

## Prod FULL Docker DB (future, optionnelle)

Sur un serveur prévu pour tout dockeriser (y compris MySQL), utiliser :

- `docker compose -f docker.compose.prod.yml up -d`
- Migrations dans ce cas (DB dans le service `db`) :
  - `docker compose -f docker.compose.prod.yml exec php-prod php artisan migrate`

Sur un serveur prévu pour tout dockeriser (y compris MySQL), utiliser :

- `docker compose -f docker.compose.prod.yml up -d`
- Migrations dans ce cas (DB dans le service `db`) :
  - `docker compose -f docker.compose.prod.yml exec php-prod php artisan migrate`

## Déploiement CI/CD (test)

Tout push ou merge sur la branche `dev` déclenche un déploiement automatique sur la VPS de test (`vps-a1b847f6.vps.ovh.net`) via GitLab CI.

Le pipeline ouvre une session SSH vers la VPS et lance `~/redeploy-test.sh`, qui :
1. Pull les 2 repos (`backmoneynci-test` et `frontmoneynci-test`) sur la branche `dev` (`git reset --hard origin/dev`)
2. Rebuild le backend Docker (services `php-test`, `nginx-test`, `browsershot-test` via `docker-compose.test.yml`)
3. Run `composer install --no-dev` et `php artisan migrate --force`
4. Rebuild le front si commit a changé (`npm install --legacy-peer-deps` + `npm run build`, output dans `~/frontmoneynci-test/build-test`)
5. Reload Apache (vhost test sur `:8083`)
6. Healthchecks `:8000/api/debug-db` et `:8083/`

Concurrence sérialisée par `flock` sur `/tmp/redeploy-test.lock` — si 2 pipelines fire en même temps, le 2ᵉ attend que le 1ᵉʳ finisse.

URLs :
- Front test : http://vps-a1b847f6.vps.ovh.net:8083
- API test : http://api-test.optionretraite.fr/api

Le déploiement prod reste **manuel** (out of scope pour cette première version).

Spec : `docs/superpowers/specs/2026-05-05-cicd-dev-vps-test-design.md`
Plan : `docs/superpowers/plans/2026-05-05-cicd-dev-vps-test.md`
