# Ina Zaoui — Portfolio Photographique & Gestion des Invités

Application web de portfolio photographique et de gestion d'artistes invités développée sous **Symfony 7.x**, **PHP 8.3** et **PostgreSQL**.

---

## Prérequis

Avant d'installer le projet en local, assurez-vous de disposer des outils suivants :

* **PHP** : version `8.2` ou `8.3` avec les extensions requises : `pdo_pgsql`, `gd` (support WebP), `intl`, `mbstring`, `xml`, `pcov` (pour la couverture de code).
* **Composer** : version `2.x`.
* **Docker & Docker Compose** (pour l'exécution de PostgreSQL 16 et Adminer).
* **Serveur web local** : Symfony CLI (`symfony serve`), Laravel Herd, Nginx ou Apache.
* **Git**.

---

## Installation

### 1. Cloner le dépôt et installer les dépendances PHP

```bash
git clone <url-du-depot> inazaoui
cd inazaoui
composer install
```

### 2. Configurer les variables d'environnement

Créez un fichier `.env.local` à la racine du projet :

```dotenv
APP_ENV=dev
APP_SECRET=remplacez_par_une_chaine_aleatoire_de_32_caracteres
DATABASE_URL="postgresql://postgres:postgres@127.0.0.1:5432/inazaoui_db?serverVersion=16&charset=utf8"
```

### 3. Démarrer la base de données avec Docker Compose

Le projet s'appuie sur `docker-compose.yml` pour instancier PostgreSQL 16 et Adminer.

#### A. Initialisation automatique de la base
Placez les 3 fichiers d'initialisation (`1_user.sql`, `2_album.sql`, `3_media.sql`) dans le dossier `./docker-entrypoint-initdb.d/` à la racine du projet.

Lancez les conteneurs :
```bash
docker compose up -d
```
* **Adminer (Interface SQL) :** accessible sur `http://localhost:8080` (Système: `PostgreSQL`, Serveur: `database`, Utilisateur: `postgres`, Mot de passe: `postgres`, DB: `inazaoui_db`).

#### B. Option manuelle (import SQL direct)
Si le conteneur tourne déjà sans les tables :
```bash
docker exec -i inazaoui_db psql -U postgres -d inazaoui_db < docker-entrypoint-initdb.d/1_user.sql
docker exec -i inazaoui_db psql -U postgres -d inazaoui_db < docker-entrypoint-initdb.d/2_album.sql
docker exec -i inazaoui_db psql -U postgres -d inazaoui_db < docker-entrypoint-initdb.d/3_media.sql
```

### 4. Extraire et configurer les médias (Images WebP)

1. Placez les images au format `.webp` dans le dossier `public/uploads/` (ou dézippez l'archive `uploads.zip` si fournie dans ce dossier) :
   ```bash
   mkdir -p public/uploads
   # Si uploads.zip est fourni à la racine :
   unzip uploads.zip -d public/uploads/
   chmod -R 775 public/uploads
   ```

2. **Si la base SQL contient encore des chemins en `.jpg` :**
   Exécutez la commande console de conversion pour mettre à jour la base de données et convertir d'éventuels visuels résiduels :
   ```bash
   php bin/console app:convert-medias-webp
   ```
   *(Ou alignez directement les valeurs de `3_media.sql` avec `UPDATE media SET path = REPLACE(path, '.jpg', '.webp');` via Adminer).*

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
| `/portfolio` | Galerie complète (œuvres des invités actifs) | 12 médias / page |
| `/portfolio/{id}` | Galerie filtrée par album | 12 médias / page |
| `/guests` | Liste des artistes invités actifs et nombre d'œuvres | 10 invités / page |
| `/guest/{id}` | Profil et galerie d'un artiste invité | 12 médias / page |
| `/qui-suis-je` | Présentation de l'artiste Ina Zaoui | — |

#### Back-office (Authentification sur `/login`)
| Route | Accès | Description | Pagination |
| :--- | :--- | :--- | :--- |
| `/admin/media` | Admin & Invités actifs | Gestion des photographies | 25 médias / page |
| `/admin/media/add` | Admin & Invités actifs | Téléversement avec conversion automatique WebP | — |
| `/admin/album` | `ROLE_ADMIN` | Gestion des albums | 15 albums / page |
| `/admin/guests` | `ROLE_ADMIN` | Gestion des invités (activation/suspension/suppression) | 15 invités / page |

---

## Tests et Couverture de Code

Les fixtures (`App\DataFixtures\AppFixtures`) sont réservées **exclusivement à l'environnement de test** (`--env=test`).

### Lancer la suite de tests PHPUnit
```bash
# 1. Initialiser la base de test
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:schema:update --force --env=test
php bin/console doctrine:fixtures:load --env=test --no-interaction

# 2. Exécuter PHPUnit (30 tests)
php bin/phpunit
```

### Générer le rapport de couverture (PCOV)
```bash
composer test:coverage
```
Pour afficher le rapport HTML dans le navigateur (macOS) :
```bash
open coverage/index.html
```