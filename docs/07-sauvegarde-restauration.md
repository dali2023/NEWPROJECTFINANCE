# Sauvegarde et restauration

**Application :** ONE ERP Finance & Project Control  
**Cible :** PostgreSQL 16 + justificatifs et logo stockés hors du répertoire public<br>
**Objectifs :** RPO 24 h, RTO 8 h ouvrées (cahier des charges §9.3)

## 1. Périmètre

| Élément | Emplacement | Sauvegarde |
|---|---|---|
| Base de données | volume Docker `pgdata` / base `one_erp_finance` | `pg_dump` quotidien |
| Justificatifs et logo | répertoire `storage/` (monté sur `/var/www/storage`) | archive quotidienne |
| Configuration | `app/.env`, `.env`, secrets Symfony | coffre-fort de secrets, hors sauvegarde applicative |
| Code source | dépôt Git | dépôt distant + étiquette de version |
| Journaux | `app/var/log/` | non sauvegardés (rotation locale) |

Les exports générés et les fichiers temporaires ne sont pas sauvegardés.

## 2. Fréquences et rétention

| Sauvegarde | Fréquence | Conservation |
|---|---|---|
| Base complète | quotidienne (00 h 30) | 30 jours |
| Base complète | mensuelle (1er du mois) | 12 mois |
| `storage/` | quotidienne | 30 jours |
| Configuration / secrets | à chaque changement | historique chiffré |

Les sauvegardes doivent être copiées **hors du serveur** (stockage réseau ou
objet chiffré). Une sauvegarde présente uniquement sur le serveur ne protège ni
d'une panne matérielle ni d'un rançongiciel.

## 3. Sauvegarde manuelle

### 3.1. Linux / macOS

```bash
mkdir -p backups
DATE=$(date +%Y%m%d-%H%M)

# Base de données (format personnalisé, compressé)
docker compose exec -T postgres \
  pg_dump -U one_erp -d one_erp_finance -Fc -f /tmp/one_erp_$DATE.dump
docker cp oneerp_postgres:/tmp/one_erp_$DATE.dump ./backups/
docker compose exec -T postgres rm -f /tmp/one_erp_$DATE.dump

# Justificatifs
tar czf backups/storage_$DATE.tar.gz storage/

# Empreintes pour contrôle d'intégrité
sha256sum backups/one_erp_$DATE.dump backups/storage_$DATE.tar.gz \
  > backups/one_erp_$DATE.sha256
```

### 3.2. Windows PowerShell

```powershell
New-Item -ItemType Directory -Force .\backups | Out-Null
$date = Get-Date -Format "yyyyMMdd-HHmm"

docker compose exec -T postgres pg_dump -U one_erp -d one_erp_finance -Fc -f /tmp/one_erp_$date.dump
docker cp oneerp_postgres:/tmp/one_erp_$date.dump .\backups\
docker compose exec -T postgres rm -f /tmp/one_erp_$date.dump

Compress-Archive -Path .\storage\* -DestinationPath .\backups\storage_$date.zip -Force
Get-FileHash .\backups\one_erp_$date.dump -Algorithm SHA256 | Out-File .\backups\one_erp_$date.sha256
```

> Le mot de passe PostgreSQL de développement est `one_erp_dev`. En production,
> utiliser un rôle dédié à la sauvegarde et un fichier `~/.pgpass` ou une
> variable d'environnement, jamais un mot de passe en clair dans l'historique du
> shell.

## 4. Vérification d'une sauvegarde

```bash
# Le fichier doit être lisible et contenir la table projects
docker compose exec -T postgres pg_restore -l /tmp/one_erp_AAAAAMMJJ-HHMM.dump | grep -E "TABLE DATA (projects|users|audit_logs)"
```

Contrôles minimaux :

- taille du fichier non nulle et stable par rapport à la veille ;
- empreinte SHA-256 conforme ;
- liste des tables attendue ;
- test de restauration trimestriel (section 6).

## 5. Restauration complète

> **Avant toute restauration :** sauvegarder l'état courant, prévenir les
> utilisateurs et fermer l'accès applicatif (`docker compose stop nginx`).

### 5.1. Restaurer la base

```bash
# 1. Copier la sauvegarde dans le conteneur
docker cp ./backups/one_erp_20261021-0030.dump oneerp_postgres:/tmp/restore.dump

# 2. Fermer les connexions et recréer une base vide
docker compose exec -T postgres psql -U one_erp -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'one_erp_finance' AND pid <> pg_backend_pid();"
docker compose exec -T postgres dropdb -U one_erp --if-exists one_erp_finance
docker compose exec -T postgres createdb -U one_erp -O one_erp one_erp_finance

# 3. Restaurer
docker compose exec -T postgres pg_restore -U one_erp -d one_erp_finance \
  --no-owner --no-privileges /tmp/restore.dump

# 4. Vérifier l'état des migrations
docker compose exec -u application php php bin/console doctrine:migrations:status
```

`doctrine:migrations:status` doit indiquer **« Already at latest version »**. Si
la sauvegarde précède une migration, exécuter ensuite :

```bash
docker compose exec -u application php php bin/console doctrine:migrations:migrate -n
```

### 5.2. Restaurer les justificatifs

```bash
# Linux / macOS
tar xzf backups/storage_20261021-0030.tar.gz

# Windows PowerShell
Expand-Archive -Path .\backups\storage_20261021-0030.zip -DestinationPath . -Force
```

Vérifier les droits : les fichiers doivent rester lisibles par l'utilisateur
des workers PHP et **ne jamais** être exposés directement par Nginx.

### 5.3. Redémarrer et contrôler

```bash
docker compose start nginx
docker compose exec -u application php php bin/console cache:clear
```

Contrôles de reprise :

| Contrôle | Attendu |
|---|---|
| Connexion avec un compte de démonstration / réel | OK |
| Tableau de bord | montants et projets cohérents |
| Liste des projets | nombre attendu, références intactes |
| Fiche projet et historique | jalons et audit présents |
| Création d'un client de test | OK puis suppression/désactivation |
| Journal d'audit | opérations antérieures à la sauvegarde présentes |
| Justificatif | téléchargement/ouverture OK |

## 6. Test de restauration trimestriel

Ne jamais restaurer directement en production pour un simple test. Utiliser une
base dédiée :

```bash
docker compose exec -T postgres createdb -U one_erp -O one_erp one_erp_finance_restore_test
docker compose exec -T postgres pg_restore -U one_erp -d one_erp_finance_restore_test \
  --no-owner --no-privileges /tmp/restore.dump
docker compose exec -T postgres psql -U one_erp -d one_erp_finance_restore_test -c \
  "SELECT count(*) AS projets FROM projects; SELECT count(*) AS audits FROM audit_logs;"
docker compose exec -T postgres dropdb -U one_erp one_erp_finance_restore_test
```

Consigner dans le registre d'exploitation : date, opérateur, sauvegarde testée,
durée de restauration, écarts constatés.

## 7. Automatisation

### 7.1. Cron (Linux)

```cron
30 0 * * * cd /opt/one-erp && ./scripts/backup.sh >> /var/log/one-erp-backup.log 2>&1
```

Le script doit :

1. créer le dump et l'archive ;
2. vérifier les codes de sortie (`set -euo pipefail`) ;
3. calculer les empreintes ;
4. copier vers le stockage hors site ;
5. supprimer les fichiers de plus de 30 jours ;
6. envoyer une alerte en cas d'échec.

### 7.2. Planificateur de tâches (Windows)

Créer une tâche quotidienne à 00 h 30 exécutant un script PowerShell équivalent,
avec compte de service, journalisation et alerte en cas d'échec.

## 8. Sécurité des sauvegardes

- Chiffrer les archives avant tout transfert hors site (age, GPG ou stockage
  objet chiffré).
- Restreindre l'accès au répertoire `backups/` au compte d'exploitation.
- Ne jamais versionner une sauvegarde ni un fichier `.env` dans Git.
- Tester la restauration des **secrets** séparément : une base restaurée sans
  `APP_SECRET` ni mots de passe de messagerie n'est pas exploitable.
- Journaliser les accès aux sauvegardes et les restaurations.

## 9. Procédure d'incident

1. Isoler le serveur si l'intégrité est suspectée.
2. Identifier la dernière sauvegarde valide (date, empreinte, test).
3. Restaurer sur un environnement de recette et contrôler.
4. Restaurer en production selon la section 5.
5. Vérifier les journaux d'audit et informer la direction.
6. Documenter la cause, l'impact et les mesures correctives.

## 10. Registre des tests de restauration

| Date | Sauvegarde | Opérateur | Durée | Écarts | Visa |
|---|---|---|---|---|---|
| | | | | | |
| | | | | | |
