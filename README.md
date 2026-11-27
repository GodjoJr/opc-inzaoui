# Ina Zaoui — Portfolio Photographique & Gestion des Invités

Application web de portfolio photographique et de gestion d'artistes invités développée sous **Symfony 7.4 (LTS)**, **PHP 8.3** et **PostgreSQL 16**.

Documentation technique (fonctionnement du code et choix d'implémentation) : [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Prérequis

Avant d'installer le projet en local, assurez-vous de disposer des outils suivants :

- **PHP** : version `8.3` (ou supérieure) avec les extensions requises : `pdo_pgsql`, `gd` (avec support WebP), `intl`, `mbstring`, `xml`, ainsi que `pcov` ou `Xdebug` (pour la génération du rapport de couverture de code).
- **Composer** : version `2.x`.
- **Docker & Docker Compose** (pour l'exécution de PostgreSQL 16 et Adminer).
- **Serveur web local** : Symfony CLI (`symfony server:start`), Laravel Herd, Nginx ou Apache.
- **Git**.

---

## Installation

### 1. Cloner le dépôt et installer les dépendances PHP

```bash
git clone https://github.com/GodjoJr/opc-inzaoui.git inazaoui
cd inazaoui
composer install
```

**Fichiers fournis séparément** : `database.zip` (dumps SQL) et `uploads.zip` (images WebP) ne sont pas versionnés (volumineux, données). Ils sont transmis avec le projet en dehors du dépôt Git ; placez-les **à la racine du projet** (`inazaoui/database.zip`, `inazaoui/uploads.zip`) avant de poursuivre.

### 2. Configurer les variables d'environnement

Créez un fichier `.env.local` à la racine du projet :

```dotenv
APP_ENV=dev
APP_SECRET=remplacez_par_une_chaine_aleatoire_de_32_caracteres
DATABASE_URL="postgresql://postgres:postgres@127.0.0.1:5432/inazaoui_db?serverVersion=16&charset=utf8"
```

### 3. Initialiser la base de données PostgreSQL avec Docker Compose

Les fichiers SQL fournis dans `database.zip` ne contiennent que des instructions `INSERT`. Il est donc impératif de créer le schéma Doctrine avant d'importer les données :

1. **Démarrer les conteneurs Docker** (en s'assurant que le dossier `docker-entrypoint-initdb.d/` est vide) :
   ```bash
   mkdir -p docker-entrypoint-initdb.d
   docker compose up -d
   ```
   - **Adminer (Interface SQL) :** accessible sur `http://localhost:8080` (Système : `PostgreSQL`, Serveur : `database`, Utilisateur : `postgres`, Mot de passe : `postgres`, Base : `inazaoui_db`).

2. **Créer les tables via Doctrine :**
   ```bash
   php bin/console doctrine:schema:update --force
   ```

3. **Extraire les fichiers SQL dans `docker-entrypoint-initdb.d/` :**
   ```bash
   unzip -j database.zip 'database/*.sql' -d docker-entrypoint-initdb.d/
   ```

4. **Importer les données dans l'ordre strict des clés étrangères :**
   ```bash
   docker exec -i inazaoui_db psql -U postgres -d inazaoui_db < docker-entrypoint-initdb.d/1_user.sql
   docker exec -i inazaoui_db psql -U postgres -d inazaoui_db < docker-entrypoint-initdb.d/2_album.sql
   docker exec -i inazaoui_db psql -U postgres -d inazaoui_db < docker-entrypoint-initdb.d/3_media.sql
   ```

5. **Recaler les séquences d'identifiants :**
   Les dumps insèrent des identifiants explicites sans mettre à jour les séquences PostgreSQL. Sans cette étape, le premier ajout de média, d'invité ou d'album échoue (`duplicate key`).
   ```bash
   docker exec -i inazaoui_db psql -U postgres -d inazaoui_db -c "SELECT setval('media_id_seq', (SELECT MAX(id) FROM media)); SELECT setval('user_id_seq', (SELECT MAX(id) FROM \"user\")); SELECT setval(pg_get_serial_sequence('album', 'id'), (SELECT MAX(id) FROM album));"
   ```

6. **Définir le mot de passe du compte administrateur d'Ina (`ina@zaoui.com`) :**
   Les dumps SQL ne contiennent pas de mot de passe haché pour le compte d'administration en développement (`ina@zaoui.com`, distinct du compte `ina@inazaoui.com` utilisé dans les fixtures de test).
   - Générez un hash sécurisé :
     ```bash
     php bin/console security:hash-password
     ```
   - Mettez à jour le compte en base (conservez impérativement les apostrophes autour du hash pour éviter que le shell n'interprète les caractères `$`) :
     ```bash
     docker exec -i inazaoui_db psql -U postgres -d inazaoui_db -c "UPDATE \"user\" SET password = '<votre_hash_bcrypt>' WHERE email = 'ina@zaoui.com';"
     ```

### 4. Extraire et configurer les médias (Images WebP)

1. Extrayez les images `.webp` de l'archive `uploads.zip` directement dans `public/uploads/` :
   ```bash
   mkdir -p public/uploads
   unzip -j uploads.zip 'uploads/*.webp' -d public/uploads/
   chmod -R 775 public/uploads
   ```

2. **Migration d'anciens médias `.jpg` / `.png` (si applicable) :**
   Si des visuels historiques au format JPEG/PNG sont ajoutés ultérieurement, exécutez la commande console dédiée pour les convertir en WebP et réaligner la base de données :
   ```bash
   php bin/console app:convert-medias-webp
   ```

---

## Usage

### Démarrer l'application

Avec la CLI Symfony :
```bash
symfony server:start -d
```
L'application est accessible sur `http://127.0.0.1:8000` (ou votre hôte virtuel local, ex. `http://inazaoui.test`).

### Routes principales

#### Front-office (Accès public)
| Route | Description | Pagination |
| :--- | :--- | :--- |
| `/` | Page d'accueil | — |
| `/portfolio` | Galerie complète (médias d'Ina et des invités actifs) | 12 médias / page |
| `/portfolio/{id}` | Galerie filtrée par album (`404` si l'album n'existe pas) | 12 médias / page |
| `/guests` | Liste des artistes invités actifs et compteur d'œuvres | 10 invités / page |
| `/guest/{id}` | Profil et galerie d'un artiste invité actif (`404` si suspendu) | 12 médias / page |
| `/about` | Présentation de la photographe Ina Zaoui | — |

#### Back-office (Authentification sur `/login`)
| Route | Accès | Description | Pagination |
| :--- | :--- | :--- | :--- |
| `/admin/media` | Admin & Invités actifs | Gestion des photographies (filtrée par propriétaire pour un invité) | 25 médias / page |
| `/admin/media/add` | Admin & Invités actifs | Téléversement sécurisé avec conversion automatique en WebP | — |
| `/admin/album` | `ROLE_ADMIN` | Gestion des albums | 15 albums / page |
| `/admin/guests` | `ROLE_ADMIN` | Gestion des invités (création, activation/suspension, suppression) | 15 invités / page |

---

## Architecture & Choix d'Implémentation

- **`src/Entity/`** : Modèles Doctrine (`User`, `Album`, `Media`) avec attributs PHP 8 natifs. L'entité `Media` utilise une séquence PostgreSQL explicite (`SEQUENCE` sur `media_id_seq`) et une contrainte `onDelete: 'SET NULL'` sur la relation `album`. La relation `User -> Media` applique `cascade: ['remove']` et `orphanRemoval: true`.
- **`src/Security/`** : Authentification dynamique via `EntityUserProvider` (`security.yaml`), protection CSRF sur le formulaire de connexion (`enable_csrf: true`) et contrôle du statut actif via `UserChecker` (`CustomUserMessageAccountStatusException`).
- **`src/Controller/`** : Séparation stricte des droits (`ROLE_ADMIN` pour albums et invités, isolation des médias par propriétaire pour les invités) et protection CSRF systématique sur toutes les actions de modification/suppression (`POST`).
- **`src/Service/ImageUploader.php`** : Validation stricte via `#[Assert\File]` (poids max **2 Mo**, types MIME `image/jpeg`, `image/png`, `image/webp`), redimensionnement proportionnel (largeur max **1 920 px**) et conversion automatique au format **WebP** (qualité **82 %**) via GD.
- **`src/Repository/`** : Élimination des requêtes **N+1** sur la page des invités : le nombre de médias est calculé en SQL (`COUNT` + `GROUP BY`), sans charger les objets `Media`, et généralisation de la pagination (`Paginator` Doctrine et composant Twig `templates/partials/_pagination.html.twig`).

---

## Qualité, Tests & Couverture de Code

Les fixtures (`App\DataFixtures\AppFixtures`) alimentent la base de test (`inazaoui_db_test`, suffixe `_test` ajouté automatiquement par Doctrine). **Ne les chargez pas sur la base de dev** : elles purgent les données importées depuis les dumps.

Comptes créés par les fixtures :
- Admin : `ina@inazaoui.com` / `Admin1234!`
- Invité actif : `paul.actif@example.com` / `Guest1234!`

### 1. Lancer la suite de tests PHPUnit
```bash
# Initialiser la base de test
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:schema:update --force --env=test
php bin/console doctrine:fixtures:load --env=test --no-interaction

# Exécuter PHPUnit
php bin/phpunit
```

### 2. Générer le rapport de couverture de code (Xdebug via Herd / PCOV)
- **Sur Mac Intel avec Laravel Herd (Xdebug) :**
  ```bash
  composer test:coverage
  ```
- **Sur tout autre environnement (Linux, CI GitHub Actions, PHP Homebrew avec PCOV) :**
  ```bash
  php -d pcov.enabled=1 bin/phpunit --coverage-html coverage
  ```

Le rapport HTML est généré dans `coverage/index.html` (non versionné).

### 3. Analyse statique & Intégration continue (GitHub Actions)
```bash
vendor/bin/phpstan analyse --no-progress
php bin/console lint:yaml config --parse-tags
php bin/console lint:twig templates
php bin/console lint:container
```
Chaque `push` et `pull_request` sur `main` exécute automatiquement ce contrôle qualité complet via `.github/workflows/ci.yml` sous **PHP 8.3** et **PostgreSQL 16** (seuil minimal de couverture exigé : **70 %**).

---

## Contribuer au projet

Consultez le fichier [`CONTRIBUTING.md`](CONTRIBUTING.md) pour connaître la procédure de contribution, les conventions de nommage des branches et commits ainsi que les règles de validation avant fusion.