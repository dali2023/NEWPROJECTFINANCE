# ONE ERP — Finance & Project Control

Application web interne de pilotage financier (projets, facturation, charges,
trésorerie, associés), conforme au cahier des charges fonctionnel et technique
v1.0 du 21/10/2026.

> Outil de gestion et d'aide à la décision. Il ne remplace ni la comptabilité
> générale officielle ni les déclarations fiscales.

**Périmètre livré :** socle et sécurité, référentiels (clients, fournisseurs,
types de projet), projets, module 15 (contrats, maintenance, réceptions,
renouvellements, retenues, calendrier et alertes), facturation, charges,
trésorerie et associés. Les modules de répartition indirecte, immobilisations,
résultat annuel, distribution et reporting sont annoncés comme « à venir ».

## Pile technique

| Composant | Choix | Justification |
|---|---|---|
| Langage | PHP 8.3 | Version supportée, compatible avec la LTS Symfony 7.4 |
| Framework | Symfony **7.4 LTS** | La branche 7.5 n'existe pas ; 7.4 est la LTS courante (Symfony 8 exige PHP 8.4) |
| Base de données | PostgreSQL 16 | Types `NUMERIC` exacts, contraintes `CHECK`, index fonctionnels |
| Serveur web | Nginx + PHP-FPM | Séparation application / assets |
| ORM | Doctrine ORM 3 + Migrations | Schéma versionné et reproductible |
| Front | Twig + CSS maison, JavaScript léger | Interface française responsive, sans dépendance lourde |
| Auth | Symfony Security (form login) | Rôles et permissions granulaires en base |
| Exports | PDF / XLSX | Prévus en phase 6 (non livrés à ce stade) |
| Conteneurisation | Docker Compose | Environnements dev, recette et production identiques |

## Démarrage

Prérequis : Docker Desktop avec moteur Linux.

```bash
cp .env.example .env          # Windows : copy .env.example .env
docker compose up -d --build
docker compose exec php composer install
docker compose restart php
docker compose exec -u application php php bin/console doctrine:migrations:migrate -n
docker compose exec -u application php php bin/console app:demo:seed
```

`vendor/` et `var/` sont conservés dans des volumes Docker : sur les volumes
partagés Windows, les lire depuis l'hôte coûte plusieurs dizaines de secondes
par requête. `composer install` initialise le volume `vendor` ; après un
`composer update`, relancer la commande puis `docker compose restart php`. Les
commandes console et les tests s'exécutent avec `-u application` pour conserver
les droits sur `var/`. **Ne pas utiliser `docker compose down -v`** : cela
supprimerait la base, le vendor et le cache. La toute première requête après une
réinitialisation du cache peut prendre quelques dizaines de secondes.

| URL | Service |
|---|---|
| http://localhost:8090 | Application |
| http://localhost:8025 | Mailpit (courriels de test) |
| http://localhost:8081 | Adminer (`docker compose --profile tools up -d`) |
| localhost:5433 | PostgreSQL (depuis l'hôte) |

Les ports sont configurables dans `.env` (`HTTP_PORT`, `POSTGRES_PORT`,
`MAILPIT_UI_PORT`, `ADMINER_PORT`).

Comptes de démonstration créés par `app:demo:seed` (mot de passe `Demo1234!`) :

| Identifiant | Rôle |
|---|---|
| `admin` | Administrateur |
| `direction` | Direction générale |
| `finance` | Responsable financier |
| `commercial` | Responsable commercial |
| `chefprojet` | Chef de projet |

Le jeu de démonstration comprend 48 permissions, 5 rôles, 5 utilisateurs,
5 types de projet, les exercices 2025 (clôturé) et 2026 (ouvert), 4 clients,
3 fournisseurs, 5 projets et leurs jalons, 3 contrats avec leurs échéances,
2 contrats de maintenance, des factures, charges, comptes bancaires et
opérations d'associés. La commande est idempotente ; `--reset` réinitialise
les données de démonstration et `--password` change le mot de passe initial.

## Tests

```bash
# Suite complète (base one_erp_finance_test)
docker compose exec -u application php php bin/phpunit

# Un fichier ou un test précis
docker compose exec -u application php php bin/phpunit tests/Service/MoneyTest.php
docker compose exec -u application php php bin/phpunit --filter testIndirectAllocation
```

Résultat attendu : **OK (41 tests, 189 assertions)**.

Les tests fonctionnels s'exécutent dans une transaction PostgreSQL annulée à la
fin de chaque test : la base de test reste propre entre deux exécutions. Après
une modification de la migration initiale, recréer le schéma de test :

```bash
docker compose exec -u application php php bin/console doctrine:schema:drop --full-database --force --env=test
docker compose exec -u application php php bin/console doctrine:migrations:migrate -n --env=test
```

Contrôles statiques utiles :

```bash
docker compose exec -u application php php bin/console lint:container
docker compose exec -u application php php bin/console lint:twig templates
```

## Commandes courantes

```bash
docker compose exec -u application php php bin/console cache:clear
docker compose exec -u application php php bin/console doctrine:migrations:diff
docker compose exec -u application php php bin/console doctrine:migrations:migrate -n
docker compose exec -u application php php bin/console doctrine:migrations:status
docker compose exec -u application php php bin/console app:demo:seed --reset
docker compose exec -u application php php bin/console app:contracts:send-reminders
docker compose logs -f php nginx
docker compose restart nginx
```

## Arborescence

```
.
├── app/                     application Symfony
│   ├── src/
│   │   ├── Controller/      contrôleurs HTTP (un par module Mxx)
│   │   ├── Entity/          entités Doctrine (§5.2)
│   │   ├── Repository/      requêtes et pagination
│   │   ├── Security/        voters, permissions, authentification
│   │   ├── Service/         calcul exact, allocation, audit, références
│   │   ├── Form/            formulaires Symfony
│   │   ├── Command/         jeu de démonstration
│   │   └── EventSubscriber/ journalisation des refus d'accès
│   ├── templates/           Twig (interface française)
│   ├── migrations/          schéma versionné
│   └── tests/               tests fonctionnels et de cohérence financière
├── docker/                  Dockerfile PHP, php.ini, configuration Nginx
├── docs/                    documentation fonctionnelle et technique
├── storage/                 justificatifs et logo hors répertoire public
└── docker-compose.yml
```

## Conventions

- Montants : `NUMERIC(18,3)` en base, chaînes et `bcmath` en PHP — jamais de
  `float` (§5.1). Échelle 3 pour les montants (millimes), 4 pour les taux.
- TVA : taux stocké en **pourcentage** (`19.0000`), converti dans
  `Money::percentOf()` ; le TTC est recalculé à chaque enregistrement.
- Arrondis : demi vers le haut, déterministes ; la répartition d'un montant
  garantit que la somme des parts égale le total.
- Devise par défaut : TND, paramétrable par projet.
- Identifiants : UUID v7. Références métier préfixées (`PRJ-2026-0001`) et
  générées par une séquence atomique en base.
- Toute opération sensible écrit une ligne dans `audit_logs` (auteur, action,
  entité, avant/après, IP, résultat). Les refus d'accès sont journalisés.
- Suppression logique (`is_active`) pour les référentiels déjà utilisés.
- Contrôle des permissions côté serveur (voters), en complément du masquage des
  boutons dans l'interface.

## Documentation

| Document | Contenu |
|---|---|
| [`docs/AVANCEMENT.md`](docs/AVANCEMENT.md) | État d'avancement, décisions techniques, limites, feuille de route |
| [`docs/02-modele-donnees.md`](docs/02-modele-donnees.md) | Modèle de données et règles de gestion |
| [`docs/03-matrice-permissions.md`](docs/03-matrice-permissions.md) | Rôles, permissions et séparation des tâches |
| [`docs/04-recette.md`](docs/04-recette.md) | Recette REC-001 à REC-036 et tests financiers A à F |
| [`docs/05-documentation-technique.md`](docs/05-documentation-technique.md) | Architecture, calcul exact, sécurité, tests et points d'extension |
| [`docs/06-guide-utilisateur.md`](docs/06-guide-utilisateur.md) | Guide utilisateur par profil |
| [`docs/07-sauvegarde-restauration.md`](docs/07-sauvegarde-restauration.md) | Sauvegarde, restauration, RPO/RTO et test de reprise |
| [`docs/08-parametrage-alertes.md`](docs/08-parametrage-alertes.md) | Délais, destinataires et idempotence des alertes contractuelles |
| [`docs/09-parametrage-societe-email.md`](docs/09-parametrage-societe-email.md) | Société, logo, SMTP chiffré, email de test et rappels planifiés |

## Sécurité

- Mots de passe hachés (algorithme configuré par Symfony), jamais affichés.
- Protection CSRF de tous les formulaires et actions POST.
- Sessions serveur, déconnexion explicite.
- Permissions vérifiées côté serveur ; les routes d'administration sont
  protégées par `#[IsGranted]`.
- Journal d'audit consultable dans l'administration.
- Justificatifs et logo stockés hors du répertoire public, jamais servis
  directement par Nginx ; le logo passe par une route publique dédiée, nécessaire
  à l'écran de connexion et aux emails.
- Mot de passe SMTP chiffré en AES-256-GCM avec une clé dérivée de `APP_SECRET`,
  jamais réaffiché.

## Limites connues

1. Répartition indirecte, immobilisations, résultat annuel, distribution et
   reporting non livrés.
2. Exports PDF serveur non livrés ; les exports Excel et l'impression navigateur
   sont disponibles.
3. Les rappels contractuels sont envoyés par email, mais leur périodicité dépend
   d'un cron externe ; en développement, Mailpit capture les messages.
4. Imputation d'un encaissement sur plusieurs factures, transferts internes de
   trésorerie et charges récurrentes non encore exposés.
5. Le garde-fou « exercice clôturé » est vérifié sur l'administration des
   exercices ; son application à chaque écriture financière reste à généraliser.
6. La procédure de sauvegarde est documentée et testable manuellement ; sa
   planification relève de l'infrastructure.
7. Les scénarios partiels ou en attente sont signalés dans
   `docs/04-recette.md` et ne sont pas déclarés conformes par défaut.
