# Paramétrage des alertes contractuelles

**Module :** 15 — contrats et maintenance  
**Fichier de configuration :** `app/config/packages/contract_alerts.yaml`  
**Service :** `App\Service\ContractNotificationService`  
**Configuration :** `App\Service\ContractAlertConfig`

## 1. Principe

Les alertes contractuelles sont calculées à partir d'une date d'événement
(expiration, réception prévue, échéance de facturation, etc.) et d'une liste de
délais exprimés en jours. Chaque couple **événement + délai + destinataire**
produit au plus une notification.

Le paramétrage ne crée aucun engagement automatique : il déclenche uniquement
des alertes consultables dans le centre de notifications. Un renouvellement, une
facturation ou une libération de retenue restent des actions explicites soumises
à permission.

## 2. Fichier de configuration

```yaml
parameters:
    app.contract_alerts:
        contract_expiration: [90, 60, 30, 7]
        maintenance_renewal: [90, 60, 30]
        provisional_receipt: [30, 7, 0]
        final_receipt: [60, 30, 7, 0]
        billing_due: [15, 7, 0, -1]
        reservation_lifting: [15, 7, 0]
        warranty_end: [60, 30, 7]
        retention_release: [0, -1]
```

Les clés correspondent aux valeurs de l'enum
`App\Enum\ContractNotificationType` :

| Clé | Événement | Destinataire par défaut |
|---|---|---|
| `contract_expiration` | Fin contractuelle du contrat | Responsable du contrat, sinon créateur |
| `maintenance_renewal` | Fin d'un contrat de maintenance non renouvelé | Responsable commercial de la maintenance, sinon responsable du contrat |
| `provisional_receipt` | Date prévue d'une réception provisoire non réalisée | Responsable du contrat, sinon créateur |
| `final_receipt` | Date prévue d'une réception définitive non validée | Responsable du contrat, sinon créateur |
| `billing_due` | Échéance de facturation avec un reste à facturer | Responsable de l'échéance, sinon responsable du contrat |
| `reservation_lifting` | Date prévue de levée d'une réserve ouverte | Responsable de la réserve, sinon responsable du contrat |
| `warranty_end` | Fin de garantie calculée à partir d'une réception | Responsable du contrat, sinon créateur |
| `retention_release` | Date d'éligibilité d'une retenue non libérée | Responsable du contrat, sinon créateur |

Si une clé est absente ou vide, le service utilise les délais par défaut de
l'enum correspondant.

## 3. Sémantique des délais

| Valeur | Déclenchement |
|---|---|
| `90` | 90 jours **avant** la date de l'événement (`J-90`) |
| `30` | 30 jours avant (`J-30`) |
| `0` | Le jour de l'événement |
| `-1` | 1 jour **après** l'événement (`J+1`) |
| `-7` | 7 jours après l'événement |

Règles de génération :

1. Une alerte avec un délai positif ou nul n'est pas créée après la date de
   l'événement.
2. Une alerte avec un délai négatif n'est créée qu'après la date de l'événement.
3. La date de déclenchement calculée doit être inférieure ou égale à la date du
   jour pour que l'alerte soit créée.
4. Les alertes négatives sont marquées **critiques** ; les autres reçoivent une
   sévérité fonction de leur proximité (`forDelay()`).

Exemple : un contrat dont la fin est fixée au **31 décembre 2026**, avec les
délais `[90, 60, 30, 7]`, produit quatre alertes aux dates suivantes :

- 2 octobre 2026 (`J-90`) ;
- 1er novembre 2026 (`J-60`) ;
- 1er décembre 2026 (`J-30`) ;
- 24 décembre 2026 (`J-7`).

Une exécution le 22 octobre 2026 ne crée que l'alerte `J-90` ; les trois autres
seront créées lors des exécutions ultérieures.

## 4. Idempotence

La clé unique d'une notification est construite à partir de :

- l'identifiant du contrat ;
- le type d'événement ;
- la date de l'événement (`Y-m-d`) ;
- le délai en jours ;
- l'identifiant du destinataire ;
- les identifiants de l'échéance, de la réception, de la réserve et de la
  retenue lorsqu'ils existent.

Deux exécutions successives ne créent donc pas de doublon (REC-035). Si une
échéance est reportée, sa nouvelle date produit une nouvelle clé et une nouvelle
alerte, sans supprimer l'historique de l'ancienne (REC-032).

## 5. Cycle de vie d'une alerte

```text
pending ──► acknowledged ──► resolved
    └──────────────────────► dismissed
```

- **En attente** : alerte générée, non prise en charge.
- **Prise en charge** : un utilisateur habilité l'a lue et s'en occupe.
- **Résolue** : l'action attendue a été réalisée ou la situation est traitée.
- **Sans objet** : l'alerte ne nécessite pas d'action (justification
  enregistrée).

Les transitions sont disponibles dans le centre de notifications
(`/contrats/notifications`) et sont journalisées.

## 6. Génération

### Depuis l'interface

1. Ouvrir **Contrats → Notifications**.
2. Cliquer sur **Générer les alertes**.
3. Filtrer par type, statut, sévérité ou destinataire.

L'action exige la permission `contract.notify` et un jeton CSRF.

### Depuis le code

```php
$created = $notificationService->generateForContract($contract);
// ou, pour tous les contrats actifs :
$count = $notificationService->generateAll();
```

Les deux méthodes sont idempotentes. `generateForContract()` accepte une date
de référence facultative, utile pour les tests ou un rattrapage :

```php
$today = new \DateTimeImmutable('2026-10-22');
$created = $notificationService->generateForContract($contract, $today);
```

Il n'existe pas encore de commande console planifiée pour la génération : elle
est déclenchée depuis l'écran ou par un intégrateur appelant le service.

## 7. Modification du paramétrage

1. Modifier `app/config/packages/contract_alerts.yaml`.
2. Vider le cache Symfony :

   ```bash
   docker compose exec -u application php php bin/console cache:clear
   ```

3. Recharger l'écran de notifications.

Les valeurs déjà générées ne sont pas recalculées. Si un délai est supprimé, les
notifications existantes restent consultables ; si un délai est ajouté, les
prochaines générations créent les alertes correspondantes si leur date de
déclenchement est atteinte.

## 8. Bonnes pratiques

- Conserver des délais cohérents avec les préavis contractuels et les délais
  légaux ; le paramétrage ne les remplace pas.
- Ne pas supprimer un délai sans vérifier les alertes déjà créées.
- Tester toute modification avec un contrat de recette daté et
  `generateForContract($contrat, $date)`.
- Vérifier que le responsable du contrat ou de la maintenance est renseigné :
  sans destinataire, aucune alerte n'est créée.
- Les alertes ne déclenchent ni facture, ni renouvellement, ni mouvement de
  trésorerie ; ces actions restent explicites et contrôlées par les permissions.
