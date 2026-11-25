# Guide de contribution — Ina Zaoui

Merci de contribuer au projet **Ina Zaoui**. Voici les règles essentielles pour garantir la cohérence et la stabilité du code.

## 1. Signaler un problème (Bug)
Ouvrez une issue en précisant :
* Le titre clair du problème.
* Les étapes pour le reproduire (URL, rôle connecté, données saisies).
* Le comportement attendu vs le comportement observé (avec l'erreur Symfony si applicable).

## 2. Proposer une fonctionnalité
Ouvrez une issue décrivant le besoin, l'impact sur les entités (`User`, `Media`, `Album`) et les critères de validation.

## 3. Contribuer au code
1. Créez une branche dédiée depuis `main` (`feat/nom-feature` ou `fix/nom-fix`).
2. Respectez les standards PHP 8 / Symfony 7 (attributs natifs, typage strict, PSR-12).
3. Évitez les requêtes N+1 et utilisez systématiquement `ImageUploader` pour les médias.
4. Ouvrez une Pull Request vers `main`.

## 4. Tests & Documentation
* **Tests :** Tous les tests doivent passer (`php bin/phpunit`) avec une couverture minimale maintenue à **70 %** (`composer test:coverage`).
* **Documentation :** Mettez à jour le `README.md` si vous ajoutez une commande, une dépendance ou une variable d'environnement.