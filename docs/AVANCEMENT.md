# État d'avancement — ONE ERP Finance & Project Control

**Dernière mise à jour :** 21 octobre 2026  
**Périmètre livré :** phases 1 et 2 (socle, sécurité, référentiels, projets et
contrats)  
**Version applicative :** 1.0.0 — phase 1–2

## 1. Synthèse

Le socle de l'application est opérationnel : authentification, rôles et
permissions en base, journal d'audit, exercices financiers, clients,
fournisseurs, types de projet, projets, contrats, budgets et jalons.

Les modules de facturation, charges, trésorerie, associés et résultat annuel
(phases 3 à 5) ne sont pas encore développés. Le tableau de bord et la
navigation les affichent explicitement comme « à venir » plutôt que de
présenter des montants nuls ou faux.

| Indicateur | Valeur |
|---|---|
| Tests automatisés | 23 tests, 98 assertions — **OK** |
| Contrôle du conteneur Symfony | `lint:container` OK |
| Syntaxe Twig | 31 fichiers valides |
| Migrations | 1 migration initiale appliquée (dev et test) |
| Couverture fonctionnelle phase 1–2 | complète |

## 2. Fonctionnalités livrées

### 2.1. Socle et sécurité (phase 1)

- Connexion par identifiant **ou** adresse électronique, déconnexion.
- Comptes utilisateurs : création, modification, activation/désactivation,
  réinitialisation du mot de passe par un administrateur.
- Rôles système et permissions unitaires stockés en base (41 permissions,
  5 rôles par défaut).
- Contrôle des permissions **côté serveur** (voters Symfony), et non seulement
  masquage des boutons.
- Journal d'audit horodaté : auteur, action, entité, valeurs avant/après,
  adresse IP, résultat.
- Journalisation des accès refusés (`deny`, résultat `denied`).
- Protection CSRF de tous les formulaires et actions POST.
- Séparation des tâches : l'administrateur technique ne dispose pas, par
  défaut, des permissions de validation financière.
- Exercices financiers : création, modification, clôture, réouverture motivée
  et journalisée.
- Verrouillage des écritures sur exercice clôturé (voter dédié).

### 2.2. Référentiels et projets (phase 2)

- Clients : fiche complète, coordonnées, conditions de paiement, activation /
  désactivation, recherche et pagination.
- Fournisseurs : fiche, catégorie de prestation, conditions de paiement,
  activation / désactivation.
- Types de projet : libellé, code, ordre d'affichage, activation.
- Projets :
  - référence automatique `PRJ-AAAA-NNNN` (compteur atomique en base) ;
  - client, type, responsable, statut du cycle de vie ;
  - contrat : référence, appel d'offres, dates, devise, conditions de paiement ;
  - montants HT, taux de TVA, TVA et TTC recalculés au millime ;
  - budget prévisionnel des charges et probabilité de conclusion ;
  - échéancier contractuel (jalons : libellé, date, montant HT, statut) ;
  - historique des opérations du projet.
- Règles d'habilitation :
  - `project.view_all` : tous les projets ;
  - `project.view_own` : uniquement les projets dont l'utilisateur est
    responsable ou membre ;
  - `project.manage` : création et modification ;
  - `project.validate` : passage au statut contractuel « Gagné / contrat
    signé » ou au-delà ;
  - `project.close` : clôture.
- Tableau de bord : contrats signés, nombre de projets, répartition par statut,
  derniers projets accessibles à l'utilisateur.

### 2.3. Exploitation

- Environnement Docker Compose reproductible (PHP-FPM, Nginx, PostgreSQL,
  Mailpit, Adminer optionnel).
- Jeu de démonstration idempotent `app:demo:seed` (comptes, rôles, clients,
  fournisseurs, types, exercices, projets et jalons).
- Migration initiale complète, index fonctionnels insensibles à la casse,
  contraintes `CHECK` sur les montants.
- Tests unitaires et fonctionnels exécutables en une commande.

## 3. Décisions techniques

| Sujet | Décision | Motif |
|---|---|---|
| Version Symfony | **7.4 LTS** (et non 7.5) | La branche 7.5 n'existe pas ; 7.4 est la LTS courante. Symfony 8 exige PHP 8.4. |
| PHP | 8.3 | Version supportée, compatible Symfony 7.4 LTS. |
| Base | PostgreSQL 16 | Types `NUMERIC` exacts, contraintes et index fonctionnels. |
| Montants | chaînes + **bcmath** | Aucun flottant binaire, échelle 3 (millimes) et 4 (taux). |
| TVA | taux en **pourcentage** (`19.0000`) | Saisie naturelle dans l'interface ; conversion interne dans `Money::percentOf()`. |
| Identifiants | UUID v7 | Tri temporel, pas de fuite de volumétrie. |
| Références | table `reference_sequences` | `INSERT … ON CONFLICT … RETURNING` atomique, pas de doublon concurrent. |
| CSRF | protection Symfony active | Jetons de session pour les formulaires ; mêmes-origines pour `authenticate`/`logout` (défaut Symfony 7.4). |
| Conteneur PHP | image `webdevops/php-dev:8.3` | Extensions précompilées (pdo_pgsql, intl, bcmath, zip, gd), démarrage rapide. Les extensions d'observabilité de l'image (Excimer, Tideways, OpenTelemetry) ont été retirées : leur combinaison provoquait un segfault PHP à l'arrêt. |
| Ports | application **8090** | 8080 déjà occupé sur le poste de développement. |

## 4. Tests

```bash
docker compose exec -u application php php bin/phpunit
```

Résultat attendu :

```
OK (23 tests, 98 assertions)
```

Les tests couvrent notamment :

- l'arithmétique exacte, la TVA, les arrondis et la répartition (tests B, C, D,
  E du cahier des charges) ;
- l'encaissement partiel au niveau arithmétique (test A) — le module facture
  correspondant relève de la phase 3 ;
- le refus d'accès et sa journalisation (test F) ;
- la création d'un projet avec calcul TVA/TTC ;
- le refus de validation contractuelle sans permission ;
- le cloisonnement des projets pour un utilisateur limité à `project.view_own` ;
- la clôture et la réouverture motivée d'un exercice ;
- la journalisation d'un changement de permissions.

Le détail des scénarios REC-001 à REC-020 figure dans
[`04-recette.md`](04-recette.md).

## 5. Limites connues de la phase 1–2

1. **Facturation, encaissements, charges, trésorerie, associés et résultat
   annuel non développés.** Les écrans correspondants sont marqués « à venir ».
2. **Exports PDF/XLSX non livrés.** Les bibliothèques seront ajoutées avec les
   modules de reporting (phase 4). Aucun export partiel n'est proposé.
3. **Notifications et courriels** : seul le transport Mailpit est configuré ;
   aucun courriel métier n'est encore envoyé.
4. **Documents** : le service de stockage hors répertoire public est en place,
   mais l'interface d'upload sera livrée avec les justificatifs (phase 3).
5. **Recette REC-003 à REC-013, REC-016, REC-017 et REC-020** : non exécutables
   tant que les modules financiers correspondants ne sont pas livrés.
6. **Sauvegarde** : la procédure est documentée et peut être exécutée
   manuellement ; la planification automatique relève de l'infrastructure.

## 6. Prochaines étapes

| Phase | Contenu |
|---|---|
| 3 | Factures, encaissements partiels et imputations, dépenses et paiements, justificatifs. |
| 4 | Charges fixes et indirectes, clés de répartition, marketing et appels d'offres, immobilisations, trésorerie, prévisions. |
| 5 | Associés, avances et remboursements, résultat annuel, simulation de distribution. |
| 6 | Exports PDF/XLSX, rapports, alertes, tableau de bord financier complet, recette globale. |

## 7. Historique

| Date | Événement |
|---|---|
| 21/10/2026 | Initialisation du projet, socle Symfony et Docker. |
| 21/10/2026 | Modèle de données, sécurité, référentiels, projets, tests et documentation de la phase 1–2. |
