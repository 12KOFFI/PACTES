# PACTE — Plateforme de Recrutement et de Gestion des Candidatures

Plateforme web Symfony dédiée à la gestion de la campagne de recrutement et de formation par apprentissage du projet **PACTE** (au titre de l'année 2026), pour le métier d'**installateur de systèmes solaires photovoltaïques**.

---

## 1. Stack Technique Réelle

- **PHP** : `>= 8.0.2` (PHP 8.2 recommandé)
- **Framework** : Symfony `6.2.*`
- **ORM** : Doctrine ORM `^2.12`
- **Moteur de template** : Twig `^2.12 | ^3.0`
- **Génération PDF** : Dompdf `^2.0`
- **Traitement documents** : PhpSpreadsheet `^1.24`, PhpWord `^0.18.3`
- **Assets & Styles** : Webpack Encore `^5.1.0`, Tailwind CSS `^3.4.17`, PostCSS
- **Front-end dynamique** : Alpine.js, Stimulus, Font Awesome 6
- **Base de données** : MySQL / MariaDB

---

## 2. Installation et Démarrage Local

### Prérequis
- PHP 8.1+ avec extensions : `pdo_mysql`, `ctype`, `iconv`, `gd`, `fileinfo`, `intl`, `mbstring`.
- Composer 2.x
- Node.js (18+) et npm
- Symfony CLI (recommandé) ou serveur PHP interne

### 1. Installation des dépendances PHP et JavaScript
```bash
composer install
npm install
```

### 2. Configuration de l'environnement
Copier le fichier d'environnement et ajuster les identifiants de base de données :
```bash
cp .env .env.local
```
Éditer `.env.local` pour définir l'URL de connexion à la base de données :
```ini
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_pacte?serverVersion=8.0&charset=utf8mb4"
```

### 3. Compilation des assets
```bash
# Pour le développement
npm run dev

# En mode écoute automatique (watch)
npm run watch

# Pour la production
npm run build
```

### 4. Base de données et schéma
```bash
# Vérifier la validité du schéma Doctrine
php bin/console doctrine:schema:validate

# Mettre à jour la base de données si nécessaire
php bin/console doctrine:schema:update --force
```

### 5. Démarrage du serveur de développement
```bash
# Avec Symfony CLI
symfony server:start

# Ou avec le serveur intégré PHP
php -S 127.0.0.1:8000 -t public
```

---

## 3. Commandes Utiles de Contrôle et Maintenance

```bash
# Vider le cache de développement
php bin/console cache:clear

# Valider la syntaxe des templates Twig
php bin/console lint:twig templates

# Valider la syntaxe des fichiers de configuration YAML
php bin/console lint:yaml config

# Vérifier la liste des routes enregistrées
php bin/console debug:router
```

---

## 4. Recommandations pour la Mise en Production et la Charge (§9)

> **Important** : L'application peut être soumise à un afflux massif de connexions et de dépôts simultanés lors de l'ouverture officielle des candidatures. Le code applicatif ne garantit pas à lui seul la tenue en charge : l'infrastructure d'hébergement doit être convenablement dimensionnée et testée avant la mise en service.

### 4.1 Mode Production & Optimisation du Cache
- Définir l'environnement de production dans les variables d'environnement du serveur :
  ```ini
  APP_ENV=prod
  APP_DEBUG=0
  ```
- Installer les dépendances sans les paquets de développement avec optimisation de l'autoloader :
  ```bash
  composer install --no-dev --optimize-autoloader
  ```
- Préchauffer le cache de production :
  ```bash
  php bin/console cache:warmup --env=prod
  ```
- **OPcache** : Activer obligatoirement l'extension PHP OPcache en production avec les directives adaptées :
  ```ini
  opcache.enable=1
  opcache.memory_consumption=256
  opcache.max_accelerated_files=20000
  opcache.validate_timestamps=0
  ```

### 4.2 Téléversement des Pièces Jointes
Chaque candidat soumet obligatoirement trois (03) pièces justificatives (pièce d'identité, justificatif de diplôme/niveau/activité, carte CMU). Les directives PHP dans `php.ini` doivent être configurées pour accepter ces transferts simultanés :
- `upload_max_filesize` : minimum `5M` à `10M` par fichier (selon les limites autorisées dans le formulaire).
- `post_max_size` : supérieur à la somme des 3 fichiers téléversés (ex. `32M` ou `64M`).
- `max_file_uploads` : au moins `10` (permettant l'envoi simultané des pièces d'une candidature).
- `memory_limit` : minimum `256M` (voire `512M` pour absorber les traitements en rafale).

### 4.3 Dimensionnement PHP-FPM et Base de Données
- **PHP-FPM** : Configurer le gestionnaire de processus (ex. mode `dynamic` ou `static`) avec un nombre suffisant de processus enfants (`pm.max_children`) pour absorber les pics d'accès concurrents sans rejet de requête HTTP (erreur 502/504).
- **MySQL / MariaDB** : Augmenter la directive `max_connections` dans la configuration du serveur SQL (ex. `max_connections = 300` ou plus) pour éviter les erreurs `Too many connections` en cas de sollicitation massive.

### 4.4 Hachage des Mots de Passe (`security.yaml`)
- La configuration de sécurité utilise l'algorithme `auto` pour les utilisateurs (`App\Entity\User`).
- Cet algorithme sélectionne par défaut `Argon2id` ou `bcrypt`, qui sont volontairement conçus pour être gourmands en mémoire et en temps CPU afin de prévenir les attaques par force brute.
- **Impact de charge** : Lors de pics d'inscriptions ou de connexions massives simultanées, la consommation CPU du serveur augmentera proportionnellement. Un serveur avec un nombre de cœurs CPU suffisant est requis.

### 4.5 Génération de Documents PDF (`Dompdf`)
- L'application utilise la bibliothèque `dompdf/dompdf` pour l'impression des fiches d'inscription et des convocations (tests de sélection et visite médicale).
- `Dompdf` est un composant PHP synchrone très consommateur de mémoire RAM par document généré.
- Lors de la proclamation des résultats ou des convocations massives, les téléchargements simultanés peuvent saturer la mémoire du serveur. Il est recommandé de surveiller attentivement la mémoire allouée à PHP-FPM.

### 4.6 Stockage des Fichiers Téléversés
- Les documents candidats (pièces d'identité, diplômes, CMU) sont stockés directement sur le système de fichiers local du serveur (`public/uploads/...` ou répertoire média dédié).
- En cas de déploiement multi-serveurs avec équilibreur de charge (Load Balancer), un **système de stockage partagé** (volume réseau NFS ou stockage objet S3/compatible) est impératif pour que tous les nœuds puissent accéder aux fichiers soumis.

### 4.7 Recommandation de Tests de Charge
- **Statut** : Aucun test de charge n'a été préalablement exécuté sur cet environnement.
- **Recommandation** : Il est vivement recommandé d'exécuter un test de charge préventif (via des outils tels que k6, Locust ou Apache JMeter) avant l'ouverture officielle de la campagne de recrutement. Ce test devra simuler :
  1. Des créations massives de comptes candidats (`/register`) ;
  2. Des connexions simultanées (`/login`) ;
  3. Des dépôts de candidatures avec téléversement complet des trois fichiers obligatoires ;
  4. Des consultations concurrentes de résultats et vérifications de statut (`/verifier-statut`).