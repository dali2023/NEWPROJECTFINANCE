# Documentation technique

**Application :** ONE ERP Finance & Project Control  
**Version :** 1.1 — socle, module 15 et finances  
**Stack :** PHP 8.3.36, Symfony 7.4.21, PostgreSQL 16, Nginx, Docker Compose

## 1. Architecture

```
Navigateur
    │  HTTP (port 8090 en développement)
    ▼
Nginx ──► PHP-FPM (Symfony)
                │
                ├──► PostgreSQL 16 (données, séquences, audit)
                ├──► var/ (cache, journaux, sessions)
                └──► /var/www/storage (justificatifs, hors public)
```

| Conteneur | Rôle | Port hôte |
|---|---|---|
| `oneerp_nginx` | Reverse proxy, assets statiques, en-têtes de sécurité | 8090 |
| `oneerp_php` | PHP-FPM, console Symfony, PHPUnit | — |
| `oneerp_postgres` | Base `one_erp_finance` (+ `one_erp_finance_test`) | 5433 |
| `oneerp_mailpit` | Capture des courriels de développement | 8025 |
| Adminer (profil `tools`) | Inspection SQL | 8081 |

Les fichiers de l'application sont montés depuis `app/`. Le répertoire
`storage/` est monté sur `/var/www/storage`, en dehors de la racine web :
Nginx ne peut pas servir un justificatif directement.

## 2. Organisation du code

| Dossier | Contenu |
|---|---|
| `src/Controller/` | Contrôleurs HTTP ; un contrôleur par module, `Admin/` pour l'administration |
| `src/Entity/` | Entités Doctrine et traits (`UuidIdTrait`, `TimestampableTrait`, `SoftDeletableTrait`) |
| `src/Repository/` | Requêtes, filtres et pagination ; trait `PaginatesQueries` |
| `src/Security/` | `PermissionChecker`, voters `Project`, `Contract`, `FiscalYear`, `Permission` |
| `src/Service/` | `Money`, `ReferenceGenerator`, `AuditLogger`, `DocumentStorage`, `ContractBillingService`, `ContractNotificationService`, `MaintenanceScheduleService`, `InvoiceService`, `ExpenseService`, `TreasuryService`, `PartnerService` |
| `src/Form/` | Formulaires et `DecimalTransformer` |
| `src/EventSubscriber/` | Journalisation des refus d'accès |
| `src/Enum/` | Statuts et libellés métier |
| `templates/` | Twig, interface française |
| `migrations/` | Schéma versionné |
| `tests/` | Tests unitaires, fonctionnels et de sécurité |

## 3. Modèle de données

Les principes détaillés figurent dans [`02-modele-donnees.md`](02-modele-donnees.md).

- **Identifiants** : UUID v7 générés côté PHP (`Symfony\Uid`), stockés en
  `uuid`. Le tri chronologique reste possible sans séquence globale.
- **Montants** : `NUMERIC(18,3)` ; taux en `NUMERIC(9,4)`. Les getters renvoient
  des chaînes normalisées, jamais des flottants.
- **Références** : table `reference_sequences` (non mappée par Doctrine,
  exclue via `schema_filter`) mise à jour par
  `INSERT … ON CONFLICT … DO UPDATE … RETURNING`. La génération est atomique
  même en cas de créations concurrentes.
- **Horodatage** : `created_at` / `updated_at` en `TIMESTAMPTZ`.
- **Suppression logique** : `is_active` pour les référentiels ; les entités
  historiques ne sont jamais supprimées physiquement.
- **Audit** : table `audit_logs` en ajout seul (auteur, action, entité,
  `before_data` / `after_data` en JSON, IP, agent, résultat).

Contraintes notables de la migration initiale :

- index uniques insensibles à la casse sur les codes et courriels ;
- `CHECK` sur les montants positifs et sur `vat_rate BETWEEN 0 AND 100` ;
- `ON DELETE RESTRICT` sur les liens métier principaux (client et type de
  projet), `SET NULL` sur les auteurs et `CASCADE` sur les jalons du projet.

### 3.1. Tables livrées pour le module 15 et les finances

La migration `Version20261022021317.php` ajoute les tables suivantes :

| Domaine | Tables |
|---|---|
| Contrats | `contracts`, `contract_milestones`, `contract_milestone_histories`, `contract_documents`, `maintenance_contracts`, `contract_receipts`, `contract_reservations`, `contract_renewals`, `retention_guarantees` |
| Alertes | `contract_notifications` (clé unique d'idempotence) |
| Facturation | `invoices`, `invoice_lines`, `payments` |
| Charges | `expenses`, `expense_payments` |
| Trésorerie | `bank_accounts`, `cash_movements` |
| Associés | `partners`, `partner_transactions` |

Les montants restent en `NUMERIC(18,3)`, les taux en `NUMERIC(9,4)`. Les
mouvements de trésorerie portent `source_type` et `source_id` afin de retrouver
la facture, la charge ou l'opération d'associé à l'origine du flux.

## 4. Calcul financier exact

`App\Service\Money` encapsule **bcmath** :

| Méthode | Rôle |
|---|---|
| `zero()`, `normalize()`, `round()` | Valeur nulle et mise à l'échelle |
| `add()`, `sub()`, `mul()`, `div()`, `sum()` | Opérations à échelle fixe, sans flottant |
| `percentOf()` | Applique un taux exprimé en pourcentage |
| `allocate()` | Répartit un montant selon des poids, somme des parts = total |
| `format()` | Affichage `1 234,567 TND` |

Règles :

- échelle **3** pour les montants, **4** pour les taux ;
- arrondi demi vers le haut (`HALF_UP`), déterministe ;
- `allocate()` distribue d'abord la part entière, puis les millimes restants
  selon l'ordre des poids : aucune perte ni création de centime ;
- la TVA est stockée en pourcentage (`19.0000`) et convertie dans
  `percentOf()` ; le TTC est recalculé à chaque écriture.

Le `DecimalTransformer` fait la passerelle entre les chaînes de la base et les
champs de formulaire, en imposant l'échelle attendue.

## 5. Sécurité

### 5.1. Authentification

- Formulaire de connexion Symfony Security, identifiant **ou** courriel.
- Mots de passe hachés ; jamais affichés ni journalisés.
- Comptes désactivés refusés à la connexion.
- Session serveur, déconnexion explicite.

### 5.2. Autorisation

Deux niveaux complémentaires :

1. **Permissions unitaires en base** (`PermissionChecker`), accordées aux rôles.
   L'administrateur possède le joker `ROLE_ADMIN`, mais les règles métier de
   séparation des tâches restent dans les contrôleurs.
2. **Voters Symfony** pour les décisions contextuelles :
   - `ProjectVoter` : voir, modifier, valider, clôturer un projet ;
   - `ContractVoter` : voir, modifier, valider, renouveler, facturer ou
     notifier un contrat selon `contract.*` ;
   - `FiscalYearVoter` : écrire sur un exercice ouvert ;
   - `PermissionVoter` : attributs d'administration.

Les routes sensibles sont protégées par `#[IsGranted]` et les contrôleurs
revérifient les permissions métier. Le masquage d'un bouton dans Twig n'est
jamais considéré comme une protection.

### 5.3. CSRF

Tous les formulaires et actions POST utilisent un jeton CSRF. En développement,
Symfony 7.4 applique des jetons « mêmes-origines » pour `authenticate` et
`logout` ; en test, `stateless_token_ids: []` rétablit des jetons de session
pour BrowserKit.

### 5.4. Audit

`AuditLogger` écrit une ligne pour chaque opération sensible : création,
modification, changement de statut, clôture/réouverture, validation de facture,
approbation de charge, renouvellement de contrat, libération de retenue et
modification de permissions. `AccessDeniedListener` journalise les refus avec
l'action `deny` et le résultat `denied`, y compris le chemin demandé.

## 6. Requêtes et pagination

- Les filtres sont construits dans les repositories (`search()`), jamais par
  concaténation SQL.
- La pagination est centralisée dans `PaginatesQueries` ; la taille de page est
  bornée.
- Le tri utilise l'enum global `SortDirection` de Doctrine pour éviter les
  dépréciations.
- Les listes sont filtrées côté serveur selon le périmètre de l'utilisateur
  (`project.view_all` ou `project.view_own`).

## 7. Exports et impression

- `App\Service\Export\SpreadsheetExporter` génère les classeurs `.xlsx` avec
  PhpSpreadsheet. Il centralise le titre, le contexte et la date, les en-têtes
  figés et filtrables, les largeurs de colonnes, les formats numériques, la
  ligne de totaux (calculée avec `bcadd`) et la mise en page A4.
- `App\Service\Export\Column` décrit chaque colonne (`text`, `money`, `rate`,
  `decimal`, `integer`, `date`, `dateTime`, `boolean`). Les montants restent des
  nombres Excel au format `#,##0.000` ; les références sont écrites comme texte
  pour préserver les zéros de tête.
- Chaque contrôleur de liste expose une route `..._export` qui rejoue les mêmes
  filtres que `index()` et appelle `download()`. Le contrôleur reste responsable
  du contrôle de permission et du périmètre (projets et contrats).
- Le classeur est écrit dans un fichier temporaire puis renvoyé dans une
  `Response` (`Content-Disposition: attachment`), ce qui facilite les tests et
  évite les dépendances à `php://output`.
- Les boutons **Exporter Excel** et **Imprimer** proviennent de
  `templates/partials/_page_actions.html.twig` ; le bouton d'impression est
  aussi présent dans le bandeau supérieur. `app.js` traite `[data-print]`.
- Le rendu papier est défini par le bloc `@media print` d'`app.css` :
  navigation et filtres masqués, en-têtes de tableau répétés, lignes non
  coupées, en-tête société/date injecté par `base.html.twig`.
- Les exports sont plafonnés à 10 000 lignes par requête ; au-delà, affiner les
  filtres.

## 8. Tests

```bash
docker compose exec -u application php php bin/phpunit
```

- `MoneyTest` : arithmétique, TVA, arrondis, allocation, formatage (tests A à E).
- `ProjectCreationTest` : création, totaux exacts, garde-fou de validation.
- `ProjectAccessTest` : cloisonnement `project.view_own` et audit du refus.
- `AccessDeniedAuditTest` : refus d'une route d'administration, redirection
  anonyme (test F).
- `FiscalYearTest` : clôture, réouverture motivée, audit.
- `RolePermissionAuditTest` : journalisation d'un changement de permissions.
- `ClientManagementTest` : création et désactivation d'un client (REC-001).
- `Module15AcceptanceTest` : REC-021 à REC-036 (contrats, facturation,
  réceptions, réserves, renouvellement, retenues, calendrier, report,
  maintenance, alertes).
- `FinanceAcceptanceTest` : REC-025 à REC-027, paiement de charge après
  approbation et règlement d'une opération d'associé.
- `SpreadsheetExporterTest` : validité du classeur `.xlsx`, en-têtes, totaux et
  formats.
- `ExportPrintTest` : les quinze routes d'export répondent en `.xlsx` et les
  actions d'export/impression sont présentes.

Résultat de référence : **77 tests, 307 assertions — OK**.

`DatabaseTestCase` ouvre une transaction PostgreSQL dans `setUp()` et l'annule
dans `tearDown()`. Le noyau n'est pas redémarré entre les requêtes
(`disableReboot()`), afin que les écritures HTTP partagent la connexion du test.
La base de test reste ainsi propre d'une exécution à l'autre.

## 9. Configuration et exploitation

- `app/.env` : valeurs de développement (commité, sans secret de production).
- `app/.env.test` : base dédiée, `MAILER_DSN=null://null`.
- `.env` (racine) : ports et identifiants Docker Compose, ignoré par Git ;
  modèle dans `.env.example`.
- `APP_ENV`, `DATABASE_URL` et `MAILER_DSN` ne sont **pas** exportés par
  Compose : Symfony charge les fichiers `.env` attendus.
- `schema_filter` exclut `reference_sequences` du schéma Doctrine pour éviter
  sa suppression accidentelle par `doctrine:schema:update`.
- `config/packages/contract_alerts.yaml` centralise les délais d'alerte
  contractuelle ; le détail figure dans
  [`08-parametrage-alertes.md`](08-parametrage-alertes.md).
- `vendor/` et `var/` sont des volumes Docker nommés (`vendor`, `app_var`) :
  les fichiers chauds restent dans la VM et ne traversent plus le montage
  Windows à chaque requête. Le script d'entrée
  `docker/php/entrypoint/50-app-permissions.sh` attribue `var/` à
  l'utilisateur `application` (uid 1000) après le montage.
- Les commandes console et les tests s'exécutent avec `-u application` ; sinon
  les fichiers créés dans `var/` appartiennent à root et PHP-FPM renvoie 500.
- `docker compose down -v` supprime la base, `vendor` et `app_var` : à réserver
  à une remise à zéro complète.
- Nginx applique des délais FastCGI de 120 s pour absorber le premier appel à
  froid (compilation du cache Symfony).
- Les exports Excel nécessitent l'extension `zip` (présente dans l'image PHP) et
  la dépendance `phpoffice/phpspreadsheet` déclarée dans `app/composer.json`.

Commandes de contrôle :

```bash
docker compose exec -u application php php bin/console lint:container
docker compose exec -u application php php bin/console lint:twig templates
docker compose exec -u application php php bin/console doctrine:schema:validate
docker compose exec -u application php php bin/console doctrine:migrations:status
```

## 10. Points d'extension

Les services livrés (`ContractBillingService`, `ContractNotificationService`,
`MaintenanceScheduleService`, `InvoiceService`, `ExpenseService`,
`TreasuryService`, `PartnerService`) s'appuient tous sur `Money`, sur
`reference_sequences` et, pour les flux financiers, sur `TreasuryService`.

Points restant à raccorder :

- **Imputation multi-factures** : ajouter une table d'allocation entre paiement
  et factures, en conservant l'unicité `(payment_id, invoice_id)`.
- **Transferts internes** : deux mouvements partageant un identifiant de
  transfert, neutralisés dans le solde consolidé.
- **Charges récurrentes** : génération idempotente calquée sur
  `MaintenanceScheduleService`, avec une clé `(contrat, période)`.
- **Exercice clôturé** : appliquer le `FiscalYearVoter` à chaque écriture
  financière, en plus des contrôles d'administration.
- **Répartition indirecte, immobilisations, résultat annuel et distribution** :
  réutiliser `Money::allocate()` et les mêmes règles d'audit.
- **Exports PDF** : réutiliser les filtres et services de calcul des écrans afin
  de garantir l'identité des totaux (REC-016) ; les exports Excel et
  l'impression sont déjà livrés.
- **Envoi de courriels** : brancher le transport Mailer sur les notifications
  existantes, sans changer leur clé d'idempotence.

## 11. Dette technique connue

1. Pas de pipeline CI : les contrôles sont exécutés manuellement via Docker.
2. Pas d'analyse statique (PHPStan) ni de formateur automatique à ce stade.
3. `config/reference.php` est généré par Symfony et peut être régénéré après
   ajout de bundles.
4. L'imputation multi-factures, les transferts internes, les charges récurrentes
   et les modules de reporting/résultat restent à développer.
5. Les alertes ne sont pas encore envoyées par courriel.
6. Le garde-fou exercice clôturé doit être étendu à toutes les écritures
   financières.
7. Les exports et le tableau de bord financier complet restent à livrer.
