# État d'avancement — ONE ERP Finance & Project Control

**Dernière mise à jour :** 25 octobre 2026  
**Périmètre livré :** socle et sécurité, référentiels, projets, module 15
(contrats et maintenance), facturation, charges, trésorerie, associés et
prospects  
**Version applicative :** 1.2.0

## 1. Synthèse

Le socle de l'application est opérationnel : authentification, rôles et
permissions en base, journal d'audit, exercices financiers, clients,
fournisseurs, types de projet, projets, contrats, budgets et jalons.

Le module 15 est livré : registre des contrats, fiche à neuf onglets, contrats
de maintenance, réceptions provisoires et définitives, réserves, retenues de
garantie, renouvellements explicites, calendrier contractuel, facturation à
réaliser et centre de notifications idempotent.

Les modules financiers facturation, charges, trésorerie et associés sont
également livrés, avec montants exacts en `bcmath`, rattachement des mouvements
de trésorerie aux documents et contrôles de séparation des tâches.

Le module Prospects est livré : fiches commerciales distinctes des clients,
import Excel/CSV en deux étapes avec correspondance explicite des colonnes,
détection et mise à jour des doublons, puis conversion transactionnelle en
client, projet au statut **Opportunité** et contrat **Brouillon**.

Les modules de répartition indirecte, immobilisations, résultat annuel et
distribution restent à développer ; l'export PDF reste également à livrer,
les exports Excel et l'impression étant disponibles.

| Indicateur | Valeur |
|---|---|
| Tests automatisés | **95 tests, 477 assertions — OK** |
| Contrôle du conteneur Symfony | `lint:container` OK |
| Syntaxe Twig | 81 fichiers valides |
| Schéma Doctrine | `doctrine:schema:validate` OK |
| Migrations | 4 migrations appliquées (dev et test) |
| Permissions | 53 permissions, 5 rôles par défaut |
| Couverture livrée | socle, module 15, facturation, charges, trésorerie, associés, prospects |

## 2. Fonctionnalités livrées

### 2.1. Socle et sécurité

- Connexion par identifiant **ou** adresse électronique, déconnexion.
- Comptes utilisateurs : création, modification, activation/désactivation,
  réinitialisation du mot de passe par un administrateur.
- Rôles système et permissions unitaires stockés en base (53 permissions,
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

### 2.2. Référentiels et projets

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

### 2.3. Module 15 — contrats et maintenance

- Registre des contrats avec recherche, filtres et pagination.
- Fiche contrat à neuf onglets : synthèse, échéances, maintenance, réceptions,
  réserves, retenues, documents, renouvellements et historique.
- Contrats de maintenance récurrents : périodicité, montant périodique, dates,
  responsable commercial et génération idempotente des échéances.
- Réceptions provisoires et définitives : date prévue, date effective, PV,
  validation et statut des réserves.
- Réserves : suivi individuel, responsable, date de levée prévue et levée
  effective.
- Retenues de garantie : montant, éligibilité, libérations partielles et
  historisation.
- Renouvellement **explicite** : décision, nouveau contrat, clôture de
  l'ancien, génération facultative des échéances.
- Calendrier contractuel : vue agenda groupée par jour et vue mensuelle
  navigable, échéances à venir, expirations, jalons et renouvellements.
- Facturation à réaliser : agrégation des échéances prêtes, en retard et
  bloquées, avec création de facture brouillon.
- Centre de notifications : alertes par type, sévérité, statut et destinataire,
  prise en charge, résolution et classement sans objet.
- Alertes paramétrables dans `config/packages/contract_alerts.yaml` et
  idempotentes (voir [`08-parametrage-alertes.md`](08-parametrage-alertes.md)).
- Envoi des rappels par email, manuel ou planifié, avec suivi des tentatives et
  des erreurs dans le centre de notifications.

### 2.4. Facturation (M04)

- Factures client : brouillon, validation, envoi, règlement partiel ou total,
  annulation limitée aux brouillons.
- Lignes de facture avec montants HT, taux de TVA, TVA et TTC exacts.
- Création d'un brouillon depuis une échéance contractuelle ; le report sur
  l'échéance n'a lieu qu'à la validation de la facture.
- Paiements rattachés à une facture, avec génération d'un encaissement de
  trésorerie et mise à jour du solde.
- Garde-fou : une facture brouillon ou annulée ne peut pas être encaissée.

### 2.5. Charges (M05)

- Charges fournisseurs ou frais généraux, avec ou sans projet/contrat.
- Cycle de vie : brouillon, soumise, approuvée, rejetée, partiellement payée,
  payée, annulée.
- Séparation des tâches : l'auteur d'une dépense ne peut pas l'approuver.
- Paiements de charge avec décaissement de trésorerie et cumul payé/solde dû.
- Paiement autorisé uniquement après approbation.

### 2.6. Trésorerie (M09)

- Comptes bancaires et caisses, solde d'ouverture, devise et activation.
- Mouvements d'encaissement et de décaissement rattachés à leur source
  (facture, charge, opération d'associé).
- Solde recalculé à partir du solde d'ouverture et des mouvements ; jamais saisi
  manuellement.
- Rapprochement d'un mouvement et consultation des encaissements/décaissements.

### 2.7. Associés (M10)

- Fiches associés : code, nom, type, pourcentage de détention, capital, statut.
- Opérations : apport en capital, apport en compte courant, remboursement,
  dividende et autre opération.
- Validation distincte du règlement ; un règlement non validé est refusé.
- Règlement avec génération d'un mouvement de trésorerie de sens et de montant
  cohérents.

### 2.8. Exploitation

- Environnement Docker Compose reproductible (PHP-FPM, Nginx, PostgreSQL,
  Mailpit, Adminer optionnel).
- Jeu de démonstration idempotent `app:demo:seed` : 53 permissions, 5 rôles,
  5 utilisateurs, référentiels, projets, contrats, opérations de maintenance,
  factures, charges, comptes et opérations d'associés.
- Quatre migrations appliquées en développement et en test.
- Tests isolés par transaction PostgreSQL, sans réinitialisation manuelle de la
  base entre deux exécutions.

### 2.9. Exports et impression

- Export Excel (`.xlsx`, PhpSpreadsheet) sur toutes les listes : clients,
  fournisseurs, prospects, projets, contrats, facturation à réaliser, alertes,
  factures, charges, trésorerie, associés, utilisateurs, rôles, exercices, types
  de projet et journal d'audit.
- Les exports reprennent les filtres actifs et le périmètre de droits de
  l'utilisateur (projets et contrats limités à son périmètre).
- Classeur structuré : titre, contexte et date, en-têtes figés et filtrables,
  colonnes dimensionnées, formats de nombres exacts, ligne de totaux et mise en
  page A4 prête à imprimer.
- Impression de tous les écrans de liste et de détail via le bouton
  **Imprimer** ; feuille de style papier dédiée (navigation masquée, en-têtes de
  tableau répétés, lignes non coupées, A4).

### 2.10. Paramétrage société et emails

- **Administration → Société** : raison sociale, nom commercial, matricule
  fiscal, email, téléphones, adresse et logo. Ces données alimentent la barre
  latérale, l'écran de connexion, l'en-tête d'impression et les emails.
- Logo stocké hors de `public/`, servi par `/company/logo`, remplaçable et
  supprimable sans perte de la fiche.
- **Administration → Email** : serveur SMTP, port, chiffrement (aucun,
  STARTTLS, TLS implicite), identifiants, adresse et nom d'expédition.
- Mot de passe SMTP chiffré en AES-256-GCM avec une clé dérivée de
  `APP_SECRET` ; jamais réaffiché, conservation si le champ reste vide.
- Email de test utilisant les paramètres enregistrés, même si l'envoi
  automatique est désactivé.
- Rappels contractuels envoyés par `app:contracts:send-reminders` ou par
  l'action **Envoyer les rappels**. Les échecs sont enregistrés et réessayés
  trois fois ; une nouvelle configuration active remet le compteur à zéro.
- Guide dédié : [`09-parametrage-societe-email.md`](09-parametrage-societe-email.md).

### 2.11. Prospects

- Fiche prospect autonome : raison sociale, contact, coordonnées, identifiant
  fiscal, source, statut, responsable, montant estimé HT, probabilité, date de
  conclusion prévue, prochaine action et commentaires. Référence `PRO-*`
  générée automatiquement, suppression logique et journal d'audit.
- Cycle de vie : **Nouveau**, **Qualifié**, **En discussion**, **Gagné — à
  convertir**, **Converti**, **Perdu**. Un prospect perdu ou converti ne peut
  plus être modifié.
- Import Excel/CSV en deux étapes : dépôt et analyse, puis correspondance
  explicite des colonnes avec les champs. Aperçu des trois premières lignes,
  limites de 10 Mo et 5 000 lignes, stockage temporaire privé.
- Doublons mis à jour, jamais dupliqués : email en priorité, puis raison sociale
  + ville. Les champs vides du fichier ne remplacent pas les valeurs existantes ;
  les prospects déjà convertis sont ignorés. Les lignes invalides sont signalées
  avec leur numéro et leur motif, les lignes valides étant enregistrées dans une
  transaction unique.
- Conversion transactionnelle : création d'un client, d'un projet au statut
  **Opportunité** et d'un contrat **Brouillon**, avec verrou pessimiste contre
  les conversions concurrentes. Les trois références sont uniques et les liens
  restent visibles sur la fiche prospect.
- Permissions dédiées : `prospect.view`, `prospect.manage`, `prospect.import` et
  `prospect.convert` ; la conversion vérifie en plus `client.manage`,
  `project.manage` et `contract.manage`.
- Export Excel et impression de la liste ; guide utilisateur complété.

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
OK (95 tests, 477 assertions)
```

Les tests couvrent notamment :

- l'arithmétique exacte, la TVA, les arrondis et la répartition (tests A à E du
  cahier des charges) ;
- l'encaissement partiel, le solde et le mouvement de trésorerie (test A) ;
- le refus d'accès et sa journalisation (test F) ;
- la création d'un projet avec calcul TVA/TTC ;
- le refus de validation contractuelle sans permission ;
- le cloisonnement des projets pour un utilisateur limité à `project.view_own` ;
- la clôture et la réouverture motivée d'un exercice ;
- la journalisation d'un changement de permissions ;
- les scénarios REC-021 à REC-036 du module 15 ;
- la séparation des tâches sur les charges et les associés ;
- l'approbation et le paiement partiel d'une charge ;
- la validation et le règlement d'une opération d'associé ;
- la génération d'un classeur Excel valide (en-têtes, totaux, formats) et la
  disponibilité des seize routes d'export ;
- la présence des actions d'export et d'impression sur les écrans ;
- le paramétrage société avec logo, le chiffrement et la conservation du mot de
  passe SMTP, les modes de chiffrement du transport, l'email de test, le refus
  d'envoi lorsque les emails sont désactivés et le rendu des emails de rappel ;
- les prospects : création et modification, import avec correspondance explicite,
  mise à jour des doublons par email puis nom + ville, lignes invalides,
  conversion transactionnelle en client/projet/contrat et permissions serveur.

Le détail des scénarios figure dans [`04-recette.md`](04-recette.md).

## 5. Limites connues

1. **Répartition indirecte, immobilisations, résultat annuel, distribution et
   reporting non livrés.** Les écrans correspondants restent absents ou
   explicitement marqués comme à venir.
2. **Exports PDF non livrés.** Les exports Excel (`.xlsx`) et l'impression
   navigateur sont disponibles sur toutes les listes et les fiches ; seule la
   génération PDF serveur reste à livrer.
3. **Rappels planifiés par cron externe.** Les emails contractuels sont
   envoyés, mais la périodicité dépend d'un cron ou d'un ordonnanceur
   d'infrastructure ; aucun démon interne n'est fourni. En développement,
   Mailpit capture les messages.
4. **Imputation multi-factures non livrée.** Un encaissement est rattaché à une
   facture.
5. **Transfert interne de trésorerie non exposé.** La neutralité est vérifiée
   arithmétiquement, mais l'écran de transfert reste à livrer.
6. **Charges récurrentes non livrées.** La génération idempotente d'échéances
   existe pour la maintenance, pas encore pour les charges fixes.
7. **Exercice clôturé** : le garde-fou est testé sur l'administration des
   exercices ; son application à chaque écriture financière reste à généraliser.
8. **Sauvegarde** : la procédure est documentée et peut être exécutée
   manuellement ; la planification automatique relève de l'infrastructure.
9. **Recette REC-005, REC-007, REC-008, REC-010, REC-013, REC-014, REC-019** :
   partiels, voir [`04-recette.md`](04-recette.md).

## 6. Prochaines étapes

| Phase | Contenu |
|---|---|
| Suivante | Imputation d'un encaissement sur plusieurs factures, transferts internes, charges récurrentes. |
| Suivante | Application systématique du garde-fou exercice clôturé aux écritures financières. |
| Suivante | Charges indirectes, clés de répartition, immobilisations. |
| Suivante | Résultat annuel, simulation et décision de distribution. |
| Phase finale | Exports PDF, rapports, tableau de bord financier complet, recette globale. |

## 7. Historique

| Date | Événement |
|---|---|
| 21/10/2026 | Initialisation du projet, socle Symfony et Docker. |
| 21/10/2026 | Modèle de données, sécurité, référentiels, projets, tests et documentation du socle. |
| 22/10/2026 | Module 15 : contrats, maintenance, réceptions, renouvellements, retenues, calendrier, facturation à réaliser et alertes. |
| 22/10/2026 | Finances : facturation, charges, trésorerie, associés, migration, jeu de démonstration et recette REC-021 à REC-036. |
| 22/10/2026 | Calendrier contractuel : agenda par jour et vue mensuelle navigable, jalons inclus. |
| 22/10/2026 | Exports Excel sur toutes les listes et impression de tous les écrans, avec prise en compte des filtres et du périmètre de droits. |
| 22/10/2026 | Paramétrage société (identité, logo, coordonnées), paramètres SMTP chiffrés, email de test et envoi planifié des rappels contractuels. |
| 25/10/2026 | Prospects : fiches autonomes, import Excel/CSV avec mapping explicite, doublons, conversion transactionnelle en client/projet/contrat, permissions et documentation. |
