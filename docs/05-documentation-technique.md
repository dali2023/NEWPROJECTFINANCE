# Documentation technique

**Application :** ONE ERP Finance & Project Control  
**Version :** 1.0 — phases 1 et 2  
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
| `src/Security/` | `PermissionChecker`, voters `Project`, `FiscalYear`, `Permission` |
| `src/Service/` | `Money`, `ReferenceGenerator`, `AuditLogger`, `DocumentStorage` |
| `src/Form/` | Formulaires et `DecimalTransformer` |
| `src/EventListener/` | Journalisation des refus d'accès |
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
modification, changement de statut, clôture/réouverture, modification de
permissions. `AccessDeniedListener` journalise les refus avec l'action `deny` et
le résultat `denied`, y compris le chemin demandé.

## 6. Requêtes et pagination

- Les filtres sont construits dans les repositories (`search()`), jamais par
  concaténation SQL.
- La pagination est centralisée dans `PaginatesQueries` ; la taille de page est
  bornée.
- Le tri utilise l'enum global `SortDirection` de Doctrine pour éviter les
  dépréciations.
- Les listes sont filtrées côté serveur selon le périmètre de l'utilisateur
  (`project.view_all` ou `project.view_own`).

## 7. Tests

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

`DatabaseTestCase` ouvre une transaction PostgreSQL dans `setUp()` et l'annule
dans `tearDown()`. Le noyau n'est pas redémarré entre les requêtes
(`disableReboot()`), afin que les écritures HTTP partagent la connexion du test.
La base de test reste ainsi propre d'une exécution à l'autre.

## 8. Configuration et exploitation

- `app/.env` : valeurs de développement (commité, sans secret de production).
- `app/.env.test` : base dédiée, `MAILER_DSN=null://null`.
- `.env` (racine) : ports et identifiants Docker Compose, ignoré par Git ;
  modèle dans `.env.example`.
- `APP_ENV`, `DATABASE_URL` et `MAILER_DSN` ne sont **pas** exportés par
  Compose : Symfony charge les fichiers `.env` attendus.
- `schema_filter` exclut `reference_sequences` du schéma Doctrine pour éviter
  sa suppression accidentelle par `doctrine:schema:update`.
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

Commandes de contrôle :

```bash
docker compose exec -u application php php bin/console lint:container
docker compose exec -u application php php bin/console lint:twig templates
docker compose exec -u application php php bin/console doctrine:schema:validate
docker compose exec -u application php php bin/console doctrine:migrations:status
```

## 9. Points d'extension pour les phases 3 à 6

- Les entités `Invoice`, `Payment`, `Expense`, `TreasuryAccount`,
  `PartnerAdvance`, `AllocationKey` s'appuieront sur `Money` et sur
  `reference_sequences` (`FAC-`, `ENC-`, `DEP-`, …).
- Le `DocumentStorage` est prêt pour les justificatifs : stockage hors public,
  nommage par UUID, contrôle de type et de taille à ajouter dans le formulaire.
- Le `FiscalYearVoter` devra être appliqué à chaque écriture financière
  (facture, dépense, trésorerie, avance).
- Les exports PDF/XLSX seront ajoutés en phase 6 ; ils devront réutiliser les
  mêmes filtres et les mêmes services de calcul que les écrans, afin de garantir
  l'identité des totaux (REC-016).
- Les notifications pourront utiliser le transport Mailer déjà configuré.

## 10. Dette technique connue

1. Pas de pipeline CI : les contrôles sont exécutés manuellement via Docker.
2. Pas d'analyse statique (PHPStan) ni de formateur automatique à ce stade.
3. `config/reference.php` est généré par Symfony et peut être régénéré après
   ajout de bundles.
4. Les modules financiers et les exports restent à développer ; leurs écrans
   sont des emplacements explicitement marqués « à venir ».
