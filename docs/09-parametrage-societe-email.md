# Paramétrage de la société et des emails

**Routes :** `/admin/parametres/societe` et `/admin/parametres/email`  
**Permission :** `admin.settings` (incluse dans `ROLE_ADMIN`)

Ces deux écrans sont indépendants : l'identité de la société sert à l'affichage
et aux documents, tandis que les paramètres email servent uniquement à l'envoi
des rappels contractuels.

## 1. Identité de la société

Menu **Administration → Société**.

| Champ | Usage |
|---|---|
| Raison sociale | Nom affiché dans la barre latérale, la connexion, l'impression et les emails |
| Nom commercial | Nom court facultatif (enseigne) |
| Matricule fiscal | Identifiant fiscal affiché dans l'en-tête d'impression |
| Email de contact | Adresse de réponse (`Reply-To`) des rappels |
| Téléphone / second téléphone | Coordonnées affichées dans l'en-tête d'impression |
| Adresse, code postal, ville, pays | Adresse postale affichée dans l'en-tête d'impression |
| Site web | Adresse web facultative |
| Logo | Image affichée dans la barre latérale, la connexion, l'impression et les emails |

Le logo est stocké hors de la racine web, dans le répertoire défini par
`APP_STORAGE_DIR` (sous-répertoire `company/`). Il est servi publiquement par la
route `/company/logo`, sans authentification, car il doit rester visible sur
l'écran de connexion et dans les emails. Aucun chemin de fichier n'est exposé.

Formats acceptés : PNG, JPEG ou WebP, dans la limite de 2 Mo. Le remplacement
d'un logo supprime l'ancien fichier ; la case **Supprimer le logo** le retire
sans toucher au reste de la fiche.

## 2. Paramètres email (SMTP)

Menu **Administration → Email**.

| Champ | Description |
|---|---|
| Activer l'envoi automatique | Autorise l'envoi des rappels par la commande planifiée |
| Serveur SMTP | Nom d'hôte seul, sans `http://` ni port (ex. `smtp.example.tn`) |
| Port | 587 en STARTTLS, 465 en TLS implicite, 25 ou 1025 sans chiffrement |
| Chiffrement | Aucun, STARTTLS ou TLS implicite (`smtps`) |
| Utilisateur | Identifiant SMTP, facultatif si le serveur n'exige pas d'authentification |
| Mot de passe | Stocké chiffré ; jamais réaffiché après enregistrement |
| Email d'expédition | Adresse `From` des rappels, contrôlée par Symfony |
| Nom d'expédition | Nom affiché à côté de l'adresse `From` |

### Sécurité du mot de passe

Le mot de passe SMTP est chiffré avec AES-256-GCM par `SecretCipher`, au moyen
d'une clé dérivée de `APP_SECRET` (`kernel.secret`). La valeur chiffrée est
stockée en base (`mail_settings.password_crypted`) et n'est jamais renvoyée au
navigateur. Laisser le champ vide conserve le mot de passe existant ; cocher
**Supprimer le mot de passe enregistré** le supprime.

Pour un serveur SMTP interne sans authentification, laisser les champs
utilisateur et mot de passe vides.

### Transport

Le transport est construit à chaque envoi à partir des valeurs en base
(`Transport::fromDsn()`), et non depuis un `MAILER_DSN` statique. Toute
modification est donc prise en compte immédiatement, sans redémarrage.

Exemples de DSN produits :

| Chiffrement | DSN |
|---|---|
| Aucun | `smtp://utilisateur:mot-de-passe@smtp.example.tn:25?auto_tls=false` |
| STARTTLS | `smtp://utilisateur:mot-de-passe@smtp.example.tn:587?require_tls=true` |
| TLS implicite | `smtps://utilisateur:mot-de-passe@smtp.example.tn:465` |

Les identifiants et le mot de passe sont encodés dans l'URL ; le mot de passe
est déchiffré uniquement au moment de l'envoi.

### Email de test

Le bouton **Envoyer un email de test** utilise les paramètres **déjà
enregistrés**, même si l'envoi automatique est désactivé. Il permet de valider
la connectivité, l'authentification et le certificat TLS avant d'activer les
rappels. Le destinataire est contrôlé côté serveur et l'opération est auditée.

En développement, Mailpit capture les messages sur `http://localhost:8025`.
Configuration type : serveur `mailpit`, port `1025`, chiffrement **Aucun**,
sans authentification.

## 3. Rappels contractuels

Les alertes sont créées par `ContractNotificationService` (calendrier
contractuel). L'envoi des emails est assuré par `ContractReminderMailer` :

1. seules les notifications au statut **En attente** ou **En cours** sont
   retenues ;
2. leur date de déclenchement doit être atteinte ;
3. le destinataire doit posséder une adresse email ;
4. l'email ne doit pas déjà avoir été envoyé ;
5. le nombre de tentatives doit être inférieur à 3.

Après succès, `email_sent_at` est renseigné et l'alerte n'est plus renvoyée.
En cas d'échec, l'erreur et l'horodatage sont enregistrés ; l'alerte est
réessayée aux exécutions suivantes, sans bloquer les autres. Après trois
échecs, elle est ignorée jusqu'à ce qu'une nouvelle configuration email soit
enregistrée avec l'envoi activé : les tentatives sont alors remises à zéro.

Le centre de notifications affiche l'état de l'email (envoyé, en attente,
échec avec le nombre de tentatives). L'action **Envoyer les rappels** permet un
envoi manuel immédiat, en plus de la planification.

### Planification (cron)

```bash
# Toutes les 15 minutes, dans le conteneur applicatif
*/15 * * * * cd /var/www/html && php bin/console app:contracts:send-reminders --no-interaction
```

Avec Docker Compose, la commande équivalente est :

```bash
docker compose exec -u application php php bin/console app:contracts:send-reminders
```

Options :

- `--limit=N` : nombre maximal de rappels traités par exécution (1 à 500,
  valeur par défaut 100).

La commande génère d'abord les alertes dues, puis envoie les rappels. Elle
retourne un code d'échec si l'envoi est désactivé ou si la configuration est
incomplète. Un résumé est écrit dans le journal d'audit, ainsi que dans les
journaux applicatifs en cas d'échec d'envoi.

## 4. Configuration serveur

| Variable | Rôle |
|---|---|
| `APP_SECRET` | Clé de chiffrement des mots de passe SMTP ; ne pas la changer sans ressaisir les mots de passe |
| `APP_STORAGE_DIR` | Répertoire de stockage hors racine web (logo et justificatifs) |
| `DEFAULT_URI` | URL publique utilisée pour les liens et le logo dans les emails |

En production, `DEFAULT_URI` doit être l'URL réellement accessible par les
destinataires (par exemple `https://erp.societe.tn`). Les emails ne peuvent pas
utiliser `http://localhost:8090`.

Le logo doit être accessible depuis l'extérieur pour s'afficher dans les
clients de messagerie ; si l'URL n'est pas publique, l'image est absente mais
le message reste lisible.

## 5. Permissions et audit

- Les deux écrans et l'action de test exigent `admin.settings`.
- Le bouton d'envoi manuel exige `contract.notify`.
- Les modifications de société et de paramètres email sont journalisées dans
  `audit_logs` (`update`), avec un instantané avant/après. Le mot de passe
  n'apparaît jamais, seul l'indicateur `passwordSet` est conservé.
- Les tests d'envoi et les exécutions planifiées sont également journalisés.

## 6. Dépannage

| Symptôme | Piste |
|---|---|
| « Configuration incomplète » | Renseigner le serveur, le port et l'email d'expédition, puis enregistrer |
| « Envoi des emails désactivé » | Cocher **Activer l'envoi automatique** |
| Connexion refusée | Vérifier l'hôte, le port et le pare-feu ; dans Docker, utiliser `mailpit` et non `localhost` |
| Erreur de certificat | Utiliser STARTTLS/TLS avec un certificat valide, ou « Aucun » uniquement sur un réseau interne |
| Authentification refusée | Vérifier l'utilisateur et le mot de passe ; laisser le champ vide conserve l'ancien mot de passe |
| Le logo ne s'affiche pas dans l'email | Vérifier `DEFAULT_URI` et l'accès public à `/company/logo` |
| Les rappels ne partent pas | Vérifier que l'alerte est active, que sa date est atteinte, que le destinataire a un email et que `email_attempts < 3` |
| Mot de passe illisible après redéploiement | `APP_SECRET` a changé : ressaisir le mot de passe SMTP |

## 7. Fichiers concernés

- `src/Entity/CompanySettings.php`, `src/Entity/MailSettings.php`
- `src/Controller/Admin/CompanySettingsController.php`,
  `src/Controller/Admin/MailSettingsController.php`,
  `src/Controller/CompanyLogoController.php`
- `src/Form/CompanySettingsFormType.php`, `src/Form/MailSettingsFormType.php`
- `src/Service/Settings/SecretCipher.php`,
  `src/Service/Settings/SettingsProvider.php`
- `src/Service/Mail/CompanyMailer.php`,
  `src/Service/Mail/ContractReminderMailer.php`
- `src/Command/SendContractRemindersCommand.php`
- `templates/admin/settings/`, `templates/emails/`
- `tests/Controller/SettingsTest.php`, `tests/Service/SecretCipherTest.php`
- Migration `Version20261022205916.php`
