# Rapport de migration DAIKIN -> PACTE

## 1. Modifications par phase

### Etape 1 (critique)
- Cree : `src/Config/PacteConfig.php`, `src/Service/EligibiliteService.php`, `src/Command/SeedPacteCommand.php` (`app:seed-pacte`), `migrations/Version20261017034519.php`.
- Entites : `Candidature` (copie de `UPDATE/Candidature.php`, `creneauEntretien`, notes d'epreuves en base, `session` NOT NULL + RESTRICT, unicite `(user_id, session_id)` et `numero`) ; `SessionRecrutement` (`dateReferenceAge`, `ageMin`, `ageMax`, unicite `(annee, numero_vague)`).
- `CandidatureController::new` : session via `findActive()`, une candidature par utilisateur/session, eligibilite (nationalite CI, age a la date de reference de la session), association (etablissement, metier) active, numero `PA` + AA + VV + ID (>= 4 chiffres) dans une transaction courte, upload hors transaction, suppression candidature + dossier en cas d'echec. `nbrplace` jamais lu.
- `Constant` : 3 pieces (`fpiece`, `fniveau`, `fautre`) obligatoires, `PASSEPORT` ajoute. `CandidatureType`, `UserType` (CountryType, CI par defaut sans ecraser une valeur existante), `SessionRecrutementType` (3 champs).
- Renommage `vague` -> `creneauEntretien` propage (controleur, formulaire, templates) ; `VAGUE1_PERCENT` -> `CRENEAU1_PERCENT`.
- `EtablissementsActifsConfig` (IDs 1,2,3, garde `1 = 0`), `HomeController` (ouverture liee a une session reellement ouverte, partenaires vides), `SessionRecrutementController` (D13).

### Etape 2
- **SessionContext (D4)** : `src/Service/SessionContext.php` (parametre `?session=` -> session PHP -> `findActive()` -> derniere session), `src/EventSubscriber/SessionContextSubscriber.php`, selecteur dans `sidebar.html.twig` (ADMIN/ENT/JURY), global Twig `session_context`.
- **Filtrage par session (B)** : un filtre SQL Doctrine `session_filter` (`src/Doctrine/SessionFilter.php`, declare dans `config/packages/doctrine.yaml`) restreint `Candidature` (`session_id`) et `Admis` (via `candidature_id`) a la session consultee pour tout utilisateur non candidat (personnel). Il s'applique aussi aux jointures, sous-requetes DQL, `find()` et `count()`, donc a tous les `getRepository(Candidature|Admis::class)` des controleurs sans les modifier un par un. Il n'est pas active pour les candidats ni les visiteurs anonymes (verification de statut, convocations publiques). Les controles de role/etablissement existants sont inchanges ; le filtre s'y ajoute. `ProgrammationEntretienController` : logique inchangee.
  - Compteurs de `SessionRecrutementController` (liste, suppression) calcules en suspendant le filtre.
  - Statistique publique de la page d'accueil : candidatures de la session ouverte.
- **Dashboard** : admis par etablissement (resultat = 2, session consultee, sans comparaison a `nbrplace`) ; part feminine sur 4 etapes distinctes (candidatures, `etustatut = 2`, `decisionJury = 2`, `resultat = 2`) comparee a 40 %, sans blocage. Admin : toutes etablissements ; ENT/JURY : leur etablissement. Statistiques existantes inchangees.
- **Nettoyage** : `MetierMappingConfig` supprime (zero usage, verifie par grep dans src/config/templates/bin) ; PDF : en-tetes/pieds/titres "PACTE", logos `image/pacte/logo-pacte.png` et `logo-ministere.png` avec secours texte « PACTE », aucune photo ; libelle « CeFA-FC » retire (`UserType`, `session_recrutement/form`) ; titres de pages "| PACTE".
- **Droits (phase 7)** : correction d'un defaut existant : `/candidature/print?numero=...` (fiche/convocations) etait accessible a tout ROLE_CANDIDAT/ROLE_ENT/ROLE_JURY pour n'importe quel numero. Controle ajoute : admin/com = tout ; ENT/JURY = leur etablissement ; candidat = sa candidature.

## 2. Ecarts par rapport a CLAUDE.md
- Filtre de session centralise (filtre Doctrine) au lieu de modifier chaque requete des controleurs : meme effet, un seul point de controle, mais effet de bord voulu : un membre du personnel ne peut ouvrir (`find`, `voir`, `edit`, media) une candidature d'une autre session qu'en selectionnant cette session.
- « Admis par etablissement » du dashboard = candidatures `resultat = 2` (pas la table `Admis`, qui represente les inscriptions definitives).
- Migration : delta §4 seulement (voir §4) ; elle ne cree pas le schema de base.
- `public/maintenance.html` non modifie (toujours « DAIKIN CeFA-FC »), `config/packages/routing.yaml` (`default_uri`) et `twig.yaml` (`webhost`) preexistants non modifies (le document interdit d'en ajouter).

## 3. A COMPLETER
Developpe de PACTE ; developpe de MENAET / CAB-MDET ; numero du communique ; infoline ; coordonnees/e-mail de contact (`PacteConfig` a `null`) ; localite du Lycee des Metiers de l'Energie Renouvelable ; logos officiels (`public/image/pacte/logo-pacte.png`, `logo-ministere.png`) ; partenaires (liste vide) ; texte de `public/maintenance.html`.

## 4. Commandes non executees / limites
- Migration **non executee**. Elle ne contient que le delta §4 (generee contre la base locale `daip_pacte`, vide mais au schema d'origine). Les 10 migrations d'origine ne creent pas le schema complet : sur une base totalement vide utiliser `doctrine:schema:create`. `session_id NOT NULL` et les colonnes d'age sans valeur par defaut exigent des tables vides.
- `app:seed-pacte`, le depot de candidature de bout en bout, le filtre de session et le selecteur n'ont **pas** ete executes (pas de base synchronisee) : verifies seulement par lint, validation du mapping et chargement du conteneur.
- Aucun test automatise, aucun test de charge.
- `doctrine:schema:validate` : mapping OK ; base « not in sync » tant que la migration n'est pas appliquee.

## 5. Archive d'origine
`public/` et `migrations/` presents dans le depot de travail. Les logos PACTE ne sont pas fournis.

## 6. Occurrences heritees laissees
`public/maintenance.html` (DAIKIN), `twig.yaml` `webhost`, `routing.yaml` `default_uri` (daikin-recrutement.local), classes `Constant::$diplome/$niveauetude` (listes generiques), colonnes `fphoto`/`fextrait` (conservees, inutilisees).

## 7. Limites connues non traitees
- Le numero de candidature sert de jeton d'acces aux pages publiques de convocation (`CandidatureController` ~lignes 612 et 661, routes `/candidature/imprimer-convocation*` et `/verifier-statut`) : il est sequentiel (`PA26010001`...) donc devinable.
- Fichiers herites non touches : `temp_original.php`, `error_log`, `php.ini.bak`, `maintenance.lock`.
- Hachage des mots de passe (`config/packages/security.yaml`) : `algorithm: auto` sans cout configure (hors environnement de test) : Symfony choisit l'algorithme le plus fort disponible (argon2id/bcrypt) avec les couts par defaut, volontairement couteux en CPU/memoire ; des inscriptions et connexions simultanees massives peuvent saturer PHP-FPM. Non modifie. A tester en charge avant l'ouverture.
- Production (§9) : utiliser `APP_ENV=prod`, `composer install --no-dev -o`, `php bin/console cache:warmup --env=prod`, OPcache ; controler `upload_max_filesize`, `post_max_size`, `max_file_uploads` (3 pieces de 2 Mo max par candidat) ; PHP-FPM (processus) et MySQL `max_connections` ; dompdf tres gourmand en memoire pour les convocations en masse ; fichiers sur disque local (stockage partage requis en multi-serveurs) ; test de charge recommande (connexion, inscription, depot avec 3 fichiers) : **non realise**.
- Incident : lors de l'etape 1, 10 templates ont ete vides par erreur puis restaures depuis l'archive d'origine (`compose.override.zip`) ; toute modification faite sur ces fichiers entre l'archive et l'incident par un autre intervenant a pu etre perdue (une, dans `verifier_statut/verifier.html.twig`, a ete refaite).

## 8. Resultats reels des verifications (fin etape 2)
- `php -l` sur src et config : aucune erreur.
- `lint:twig` : 65 fichiers valides. `lint:yaml` : 24 fichiers valides.
- `doctrine:schema:validate` : mapping OK ; base non synchronisee (migration non appliquee).
- `debug:router` : 102 lignes (inchange par rapport a la fin de l'etape 1).
- Greps `getVague|setVague|VAGUE1_PERCENT`, `fphoto|fextrait` (hors entite), `fdiplome|fcmu`, `MetierMappingConfig|PFA SOUBRE|CFPT ALEPE|CEFA_FC_ID`, `NoteEpreuve` (hors entite), `quota|plafond|places restantes|places pourvues` : aucun resultat. Les occurrences de `nbrplace` sont celles du seed, de l'affichage informatif (accueil, `MetierController`) et un commentaire ; aucune comparaison ni plafonnement.
