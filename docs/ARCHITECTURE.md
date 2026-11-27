# Architecture et choix d'implémentation

Ce document explique le fonctionnement général du code et les choix faits lors de la reprise du projet (migration de Symfony 5.4 vers 7.4 LTS). L'installation est décrite dans le [README](../README.md), les règles de contribution dans [CONTRIBUTING](../CONTRIBUTING.md).

---

## 1. Vue d'ensemble

```
src/
├── Command/ConvertMediasWebpCommand.php   # Conversion ponctuelle des anciens .jpg en .webp
├── Controller/
│   ├── HomeController.php                 # Front-office (public)
│   └── Admin/                             # Back-office (/admin, authentifié)
│       ├── AlbumController.php            # ROLE_ADMIN
│       ├── GuestController.php            # ROLE_ADMIN
│       ├── MediaController.php            # Admin + invités actifs
│       └── SecurityController.php         # /login, /logout
├── DataFixtures/AppFixtures.php           # Jeu de données de test
├── Entity/                                # User, Media, Album
├── Form/                                  # AlbumType, GuestType, MediaType
├── Repository/                            # Requêtes paginées et optimisées
├── Security/UserChecker.php               # Blocage des invités suspendus
└── Service/ImageUploader.php              # Upload + conversion WebP
```

Le projet suit l'architecture Symfony standard : contrôleurs fins, logique de requêtes dans les repositories, logique métier réutilisable dans des services.

---

## 2. Modèle de données

```
User (1) ──────< (n) Media (n) >────── (0..1) Album
```

| Entité | Rôle | Points clés |
| :--- | :--- | :--- |
| `User` | Ina (admin) et les invités | `admin` (booléen), `isActive` (actif / bloqué), `email` unique servant d'identifiant de connexion, mot de passe hashé |
| `Media` | Une photographie | `path` (fichier WebP dans `public/uploads/`), `title`, séquence PostgreSQL explicite (`media_id_seq`), liaison vers `User` et `Album` |
| `Album` | Regroupement de médias d'Ina | `name` |

**Intégrité référentielle et suppressions** :
- **Suppression d'un invité et de son contenu** : la relation `User::$medias` est déclarée avec `cascade: ['remove']` et `orphanRemoval: true`. Supprimer un invité supprime donc ses médias en base ; `GuestController::delete()` supprime en plus les fichiers physiques correspondants sur le disque.
- **Suppression d'un album** : la relation `Media::$album` porte la contrainte `#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]`. Supprimer un album ne provoque pas d'erreur de clé étrangère et conserve les photographies associées dans le portfolio général (`album_id` passe à `NULL`).

**Rôles** : il n'y a pas de colonne `roles`. `User::getRoles()` calcule les rôles à partir du booléen `admin` :

```php
public function getRoles(): array
{
    $roles = ['ROLE_USER'];
    if ($this->admin) {
        $roles[] = 'ROLE_ADMIN';
    }
    return array_unique($roles);
}
```

Ce choix garde le schéma de la base d'origine (dumps SQL fournis) tout en s'intégrant au composant Security.

---

## 3. Authentification, sécurité et autorisations

### Chargement des utilisateurs depuis la base

À l'origine, les identifiants étaient stockés en dur. Ils sont désormais chargés depuis la base via un provider `entity` (`config/packages/security.yaml`) :

```yaml
providers:
    app_user_provider:
        entity:
            class: App\Entity\User
            property: email
```

Les mots de passe sont hashés avec l'algorithme `auto` (bcrypt/argon2 selon la plateforme).

### Connexion et déconnexion

- **Authentification (`form_login`)** : configurée sur `admin_login` (`/login`) avec vérification CSRF active (`enable_csrf: true` et jeton `authenticate` dans le formulaire) et redirection par défaut vers `/admin/media`.
- **Déconnexion (`logout`)** : interceptée directement par le firewall `main` via la route `app_logout` (`/logout`), qui invalide la session et redirige l'utilisateur vers la page d'accueil (`target: home`).

### Blocage des invités

`App\Security\UserChecker` est branché sur le firewall `main`. Si `isActive` vaut `false`, il lève une `CustomUserMessageAccountStatusException` : la connexion est refusée avec un message explicite. Un invité bloqué disparaît aussi du front-office (filtres `isActive = true` dans les repositories).

### Contrôle d'accès et protection CSRF

| Niveau | Mécanisme |
| :--- | :--- |
| Tout `/admin` | `access_control: ^/admin → ROLE_USER` (`role_hierarchy` fait hériter `ROLE_ADMIN` de `ROLE_USER`) |
| Albums et invités | `#[IsGranted('ROLE_ADMIN')]` sur les classes `AlbumController` et `GuestController` |
| Médias | Liste filtrée par propriétaire si l'utilisateur n'est pas admin (`MediaController::index()`), propriétaire forcé à l'ajout, contrôle de propriété avant suppression (`AccessDeniedException` / `403`) |
| Actions sensibles (CSRF & `POST`) | Toutes les opérations d'écriture ou de suppression (connexion, suppression d'un album `delete_album_{id}`, suppression d'un média `delete_media_{id}`, bascule de statut d'un invité `toggle_guest_{id}`, suppression d'un invité `delete_guest_{id}`) exigent exclusivement la méthode HTTP `POST` et un jeton CSRF dédié validé via `isCsrfTokenValid()` |

---

## 4. Upload des médias

Le flux d'ajout d'une photo se fait en deux étapes.

**1. Validation (composant Validator)** — contrainte sur `Media::$file` :

```php
#[Assert\File(
    maxSize: '2M',
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
    maxSizeMessage: 'Le fichier dépasse la taille maximale autorisée de 2 Mo.',
    mimeTypesMessage: 'Veuillez téléverser un format d\'image valide (JPEG, PNG ou WebP).'
)]
```

La vérification se fait sur le **type MIME réel** du fichier (détecté par son contenu), pas sur l'extension. Le message d'erreur s'affiche sous le champ du formulaire.

**2. Traitement (`App\Service\ImageUploader`)** — une fois le formulaire valide :
- génération d'un nom de fichier sûr et unique (slug + `uniqid()`) ;
- redimensionnement à 1920 px de large maximum ;
- conversion en **WebP** (qualité 82, transparence PNG conservée) ;
- enregistrement dans `public/uploads/` et retour du chemin relatif stocké dans `Media::$path`.

Pourquoi WebP : les photos d'origine pesaient lourd. Le WebP réduit fortement le poids des images, donc le temps de chargement des galeries.

La commande `php bin/console app:convert-medias-webp` a servi à convertir une fois les anciens fichiers `.jpg` et à mettre à jour les chemins en base.

---

## 5. Performances

### Problème de la page « Invités »

La page `/guests` affiche chaque invité avec son nombre de photos. L'ancien code chargeait la liste des invités, puis Doctrine exécutait **une requête supplémentaire par invité** pour charger ses médias (problème **N+1**). Le nombre de requêtes augmentait donc avec le nombre d'invités.

### Correction

`UserRepository::findActiveGuestsPaginated()` calcule le nombre de photos **en SQL** (`COUNT` + `GROUP BY`) et pagine les invités (10 par page). Aucun objet `Media` n'est chargé :

```php
$this->createQueryBuilder('u')
    ->select('u AS guest', 'COUNT(m.id) AS mediaCount')
    ->leftJoin('u.medias', 'm')
    ->where('u.admin = :isAdmin')
    ->andWhere('u.isActive = :isActive')
    ->groupBy('u.id')
    ->orderBy('u.name', 'ASC')
    ->setFirstResult(($page - 1) * $limit)
    ->setMaxResults($limit);
```

Une seconde requête `COUNT(u.id)` donne le total pour la pagination. La page exécute donc **2 requêtes**, quel que soit le nombre d'invités ou de photos. Le template affiche `row.mediaCount` au lieu de `guest.medias|length`.

Alternatives écartées :
- **Jointure qui charge les médias** (`leftJoin` + `addSelect('m')`) : une seule requête, mais Doctrine construit tous les objets `Media` de chaque invité juste pour les compter ; la mémoire augmente avec le nombre de photos.
- **`fetch: 'EXTRA_LAZY'`** sur `User::$medias` : `|length` déclenche un `COUNT` par invité, ce qui recrée le problème N+1.

### Pagination générale

Toutes les listes sont paginées côté base de données, jamais en PHP :

| Page | Méthode | Par page |
| :--- | :--- | :--- |
| `/portfolio/{id}` | `MediaRepository::findPortfolioPaginated()` | 12 |
| `/guest/{id}` | `MediaRepository::findByUserPaginated()` | 12 |
| `/guests` | `UserRepository::findActiveGuestsPaginated()` | 10 |
| `/admin/media` | `findBy()` + `count()` | 25 |
| `/admin/album`, `/admin/guests` | `findAllPaginated()`, `findAllGuestsPaginated()` | 15 |

Le portfolio exclut les médias des invités bloqués (`u.id IS NULL OR u.isActive = true`) : les médias d'Ina sans propriétaire restent visibles.

---

## 6. Tests

| Type | Emplacement | Contenu |
| :--- | :--- | :--- |
| Fonctionnels | `tests/Controller/` | Requêtes HTTP sur chaque page du front-office, pagination, erreurs 404/403/405, connexion et déconnexion (`/logout`), droits d'accès admin / invité, rejet CSRF et validation d'upload |
| Unitaires | `tests/Service/`, `tests/Form/`, `tests/Controller/UserCheckerTest.php` | `ImageUploader`, formulaires, `UserChecker` |

Les tests tournent sur une base dédiée (`inazaoui_db_test`, suffixe ajouté par Doctrine en environnement `test`) remplie par `AppFixtures` : un admin, des invités actifs et bloqués, des albums et des médias. Les données sont donc prévisibles et indépendantes de la base de développement.

---

## 7. Qualité et intégration continue

Le workflow GitHub Actions (`.github/workflows/ci.yml`) s'exécute à chaque push et pull request sur `main` :

1. installation de PHP 8.3 (extensions du projet), des dépendances Composer et d'une base PostgreSQL 16 ;
2. création de la base de test et chargement des fixtures ;
3. lint des fichiers YAML, Twig et du conteneur ; validation du mapping Doctrine ;
4. analyse statique **PHPStan** (niveau 5, extensions Symfony et Doctrine) ;
5. tests **PHPUnit** avec couverture ; le pipeline échoue si la couverture est inférieure à **70 %**.

---

## 8. Choix de version

Le projet tourne sous **Symfony 7.4 LTS** (contrainte `extra.symfony.require: 7.4.*` dans `composer.json`) et **PHP 8.3 minimum**.

- Symfony publie une version mineure tous les six mois et une version majeure tous les deux ans. La dernière mineure de chaque branche (x.4) est une **LTS**.
- La 7.4 LTS (novembre 2025) reçoit les corrections de bugs jusqu'en novembre 2028 et les correctifs de sécurité jusqu'en novembre 2029.
- La 8.0, sortie en même temps, n'est maintenue que huit mois et exige PHP 8.4. Pour un site client qui doit rester stable sans montée de version fréquente, la LTS est le choix le plus sûr.
- Prochaine étape : migrer vers la 8.4 LTS (novembre 2027), après avoir supprimé les dépréciations signalées par la 7.4.