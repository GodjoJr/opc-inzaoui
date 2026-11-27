# Guide de contribution — Ina Zaoui

Ce guide fixe les règles pour contribuer au projet sans réintroduire la dette technique corrigée lors de la migration. Avant de commencer, lisez le [README](README.md) (installation) et [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) (fonctionnement du code).

---

## 1. Workflow GitHub

```
Issue  →  Branche  →  Commits  →  Pull Request  →  CI verte + revue  →  Merge dans main
```

### Issues
Tout travail commence par une issue :
- **Bug** : titre clair, étapes pour reproduire (URL, rôle connecté, données saisies), comportement attendu / observé, message d'erreur Symfony le cas échéant.
- **Fonctionnalité** : besoin, entités impactées (`User`, `Media`, `Album`), critères d'acceptation.

### Branches
Créez une branche depuis `main` à jour, nommée `<type>/<numero-issue>-<description-courte>` :

| Type | Usage | Exemple |
| :--- | :--- | :--- |
| `feat` | Nouvelle fonctionnalité | `feat/12-export-album` |
| `fix` | Correction de bug | `fix/15-suppression-media-invite` |
| `refactor` | Refactorisation sans changement fonctionnel | `refactor/18-guest-repository` |
| `test` | Ajout ou correction de tests | `test/20-portfolio` |
| `docs` | Documentation | `docs/22-readme-installation` |
| `chore` | Dépendances, configuration, CI | `chore/25-update-symfony` |

`main` est protégée : aucun push direct.

### Commits
Format [Conventional Commits](https://www.conventionalcommits.org/fr/) :

```
<type>(<portée>): <description à l'impératif>
```

Exemples :
```
feat(guest): ajoute le blocage d'un invité
fix(media): vérifie le propriétaire avant suppression
test(front): couvre la pagination du portfolio
```

Un commit = un changement cohérent. Référencez l'issue dans le corps du commit ou de la PR (`Closes #15`).

### Pull Requests
Une PR doit :
- cibler `main` et rester courte (une fonctionnalité ou un correctif) ;
- décrire **quoi** et **pourquoi**, avec des captures pour les changements visuels ;
- lier l'issue (`Closes #<numero>`) ;
- inclure les tests correspondants et la mise à jour de la documentation si nécessaire.

### Politique de validation
Une PR n'est mergée que si :
1. la **CI GitHub Actions est verte** (lint, PHPStan, PHPUnit, couverture ≥ 70 %) ;
2. elle a été **relue et approuvée** par au moins un autre développeur ;
3. les commentaires de revue sont résolus ;
4. elle est à jour avec `main` (pas de conflit).

Merge en **squash** pour garder un historique lisible.

### Revue de code
Le relecteur vérifie notamment :
- le respect des droits d'accès (un invité ne touche qu'à ses propres médias, albums et invités réservés à `ROLE_ADMIN`) ;
- l'absence de requêtes N+1 (barre de debug Symfony, onglet Doctrine) ;
- la présence de tests pertinents ;
- la lisibilité (noms explicites, méthodes courtes).

---

## 2. Bonnes pratiques de code

- **Standards** : PSR-12, PHP 8.3, typage strict des paramètres et retours, attributs natifs (`#[Route]`, `#[IsGranted]`, `#[ORM\...]`, `#[Assert\...]`).
- **Contrôleurs fins** : les requêtes vont dans les repositories, la logique réutilisable dans `src/Service/`.
- **Sécurité** :
  - protégez chaque route admin (`#[IsGranted]` ou contrôle du propriétaire) ;
  - les actions qui modifient des données passent en `POST` avec jeton CSRF ;
  - ne stockez jamais de secret dans le dépôt : utilisez `.env.local`.
- **Base de données** :
  - paginez toute liste côté base (`setMaxResults`, `Paginator`), jamais en PHP ;
  - chargez les relations utiles avec une jointure (`leftJoin` + `addSelect`) pour éviter le N+1.
- **Médias** : passez toujours par `ImageUploader` (validation, redimensionnement, WebP). Toute nouvelle contrainte de fichier passe par le composant Validator.
- **Fichiers non versionnés** : dumps SQL, archives `.zip`, `public/uploads/*`, `.env.local`, rapports générés.

---

## 3. Tests et analyse statique

Lancez ces commandes **avant chaque push** ; ce sont les mêmes que la CI.

```bash
# Lint
php bin/console lint:yaml config --parse-tags
php bin/console lint:twig templates
php bin/console lint:container
php bin/console doctrine:schema:validate --skip-sync --env=test

# Analyse statique
vendor/bin/phpstan analyse

# Tests
php bin/phpunit

# Couverture (PCOV requis)
composer test:coverage
```

Règles :
- **Tout correctif de bug** s'accompagne d'un test qui reproduit le bug.
- **Toute nouvelle page ou route** a au moins un test fonctionnel (statut HTTP, contenu, droits d'accès).
- La couverture ne doit pas descendre sous **70 %** (contrôlé par la CI).
- Si un test a besoin de nouvelles données, ajoutez-les dans `AppFixtures` plutôt que de les créer dans le test.
- PHPStan ne doit remonter aucune erreur. N'ajoutez pas d'exception dans `phpstan.dist.neon` sans la justifier dans la PR.

---

## 4. Maintenance

- **Dépendances** : `composer outdated` régulièrement ; mettez à jour dans une branche `chore/` dédiée et vérifiez la CI.
- **Dépréciations** : surveillez l'onglet *Deprecations* de la barre de debug et la sortie de PHPUnit. Les supprimer au fil de l'eau prépare la migration vers Symfony 8.4 LTS (novembre 2027).
- **Version de Symfony** : le projet est verrouillé sur 7.4 LTS (`extra.symfony.require` dans `composer.json`). Ne changez de branche Symfony que dans une PR dédiée.
- **Documentation** : mettez à jour le `README.md` (commande, dépendance, variable d'environnement) et `docs/ARCHITECTURE.md` (choix d'implémentation) dans la même PR que le code concerné.
