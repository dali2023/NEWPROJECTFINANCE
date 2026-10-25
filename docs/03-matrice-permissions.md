# Matrice des rôles et permissions

Source : cahier des charges v1.0, §3 (Utilisateurs et habilitations) et §14.

## Rôles

| Code | Libellé | Usage |
|---|---|---|
| `ROLE_ADMIN` | Administrateur | Utilisateurs, référentiels, règles, audit |
| `ROLE_DIRECTION` | Direction générale | Lecture globale, validations, clôtures, simulations |
| `ROLE_FINANCE` | Responsable financier | Encaissements, décaissements, charges, trésorerie |
| `ROLE_COMMERCIAL` | Responsable commercial | Tiers, opportunités, appels d'offres, projets, budget |
| `ROLE_PROJECT_MANAGER` | Chef de projet | Lecture de ses projets, propositions de dépense |

Les rôles sont cumulables. L'administrateur technique **ne dispose pas** par
défaut du droit de valider les opérations financières (§3.1).

## Permissions unitaires

Format : `domaine.action`. Elles sont stockées en base (`permissions`) et
associées aux rôles (`role_permissions`), ce qui permet d'ajuster les droits
sans redéploiement.

| Permission | Description |
|---|---|
| `admin.users` | Gérer les comptes et les rôles |
| `admin.referentials` | Gérer les référentiels (catégories, types, exercices) |
| `admin.allocation_rules` | Gérer les règles de répartition |
| `admin.audit` | Consulter le journal d'audit |
| `admin.settings` | Paramétrer l'identité de la société et le serveur SMTP des rappels |
| `fiscal_year.close` | Clôturer un exercice |
| `fiscal_year.reopen` | Réouvrir un exercice clôturé (sensible) |
| `client.view` / `client.manage` | Consulter / gérer les clients |
| `supplier.view` / `supplier.manage` | Consulter / gérer les fournisseurs |
| `prospect.view` | Consulter les prospects |
| `prospect.manage` | Créer et modifier un prospect |
| `prospect.import` | Importer des prospects depuis Excel ou CSV |
| `prospect.convert` | Convertir un prospect en client, projet et contrat brouillon |
| `project.view_all` | Consulter tous les projets |
| `project.view_own` | Consulter les projets dont on est responsable |
| `project.manage` | Créer et modifier un projet |
| `project.validate` | Valider le statut contractuel d'un projet |
| `project.close` | Clôturer un projet |
| `contract.view_all` / `contract.view_own` | Consulter tous les contrats / ses contrats |
| `contract.manage` | Créer et modifier contrats, avenants, échéances, réceptions et documents |
| `contract.validate` | Valider, signer ou clôturer un contrat (sensible) |
| `contract.renew` | Décider d'un renouvellement (sensible) |
| `contract.bill` | Facturer une échéance contractuelle |
| `contract.notify` | Générer et traiter les alertes contractuelles |
| `invoice.view` / `invoice.manage` | Consulter / gérer les factures |
| `invoice.cancel` | Annuler une facture brouillon (sensible) |
| `receipt.view` / `receipt.manage` | Consulter / gérer les encaissements |
| `expense.view` / `expense.propose` | Consulter / proposer une dépense |
| `expense.manage` | Gérer les dépenses et leurs paiements |
| `expense.approve` | Valider ou rejeter une dépense |
| `marketing.view` / `marketing.manage` | Campagnes, appels d'offres |
| `allocation.run` | Exécuter et valider une répartition |
| `asset.view` / `asset.manage` | Immobilisations et véhicules |
| `treasury.view` / `treasury.manage` | Comptes et mouvements de trésorerie |
| `partner.view` / `partner.manage` | Associés (données sensibles) |
| `partner.validate` | Valider apports, avances et remboursements |
| `annual_result.view` / `annual_result.prepare` | Résultat annuel |
| `annual_result.validate` | Valider une clôture de gestion |
| `distribution.simulate` | Simuler une distribution |
| `distribution.decide` | Enregistrer une distribution décidée |
| `report.view` / `report.export` | Rapports et exports |

## Attribution par défaut

| Permission | ADMIN | DIRECTION | FINANCE | COMMERCIAL | CHEF PROJET |
|---|:--:|:--:|:--:|:--:|:--:|
| `admin.users`, `admin.referentials`, `admin.allocation_rules`, `admin.audit`, `admin.settings` | ✓ | – | – | – | – |
| `fiscal_year.close` | – | ✓ | ✓ | – | – |
| `fiscal_year.reopen` | ✓ | – | – | – | – |
| `client.*`, `supplier.*` | ✓ | vue | vue | ✓ | – |
| `prospect.view` | ✓ | ✓ | ✓ | ✓ | – |
| `prospect.manage`, `prospect.import`, `prospect.convert` | ✓ | – | – | ✓ | – |
| `project.view_all` | ✓ | ✓ | ✓ | ✓ | – |
| `project.view_own` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `project.manage` | – | – | – | ✓ | – |
| `project.validate` | – | ✓ | – | – | – |
| `project.close` | – | ✓ | ✓ | – | – |
| `contract.view_all`, `contract.view_own` | vue | ✓ | ✓ | ✓ | vue propres |
| `contract.manage` | – | – | – | ✓ | – |
| `contract.validate` | – | ✓ | – | – | – |
| `contract.renew` | – | ✓ | – | ✓ | – |
| `contract.bill` | – | – | ✓ | – | – |
| `contract.notify` | – | ✓ | ✓ | ✓ | ✓ |
| `invoice.view`, `receipt.view` | vue | vue | ✓ | projet | projet |
| `invoice.manage`, `receipt.manage` | – | – | ✓ | – | – |
| `invoice.cancel` | – | ✓ | ✓ | – | – |
| `expense.view` | ✓ | ✓ | ✓ | ✓ | ses projets |
| `expense.propose` | – | – | ✓ | ✓ | ✓ |
| `expense.manage` | – | – | ✓ | – | – |
| `expense.approve` | – | ✓ | selon seuil | – | – |
| `marketing.*` | – | vue | vue | ✓ | – |
| `allocation.run` | – | ✓ | ✓ | – | – |
| `asset.*` | – | vue | ✓ | – | – |
| `treasury.view` | – | ✓ | ✓ | – | – |
| `treasury.manage` | – | – | ✓ | – | – |
| `partner.view`, `partner.manage`, `partner.validate` | – | ✓ | ✓ | – | – |
| `annual_result.*` | – | ✓ | ✓ (prepare) | – | – |
| `distribution.simulate` | – | ✓ | ✓ | – | – |
| `distribution.decide` | – | ✓ | – | – | – |
| `report.view` | ✓ | ✓ | ✓ | ✓ | ses projets |
| `report.export` | ✓ | ✓ | ✓ | – | – |

## Règles transverses

1. Validation côté serveur uniquement : une permission absente renvoie 403,
   jamais un simple masquage d'interface.
2. Séparation des tâches : l'utilisateur ne peut pas approuver sa propre
   dépense (§3.6, REC-010 → contrôle applicatif).
3. Portée projet : `project.view_own` limite strictement aux projets dont
   l'utilisateur est responsable ou membre.
4. Données associés (capital, avances, distributions) : réservées aux
   utilisateurs disposant de `partner.view`.
5. Toute opération sensible (validation, annulation, clôture, réouverture,
   renouvellement, facturation d'échéance, accès refusé) écrit une ligne dans
   `audit_logs` (§5.2 Z, REC-018, Test F).
6. Exercice clôturé : toute écriture ordinaire est refusée (§5.4, REC-014),
   seuls `fiscal_year.reopen` puis une correction tracée sont possibles.
7. **Renouvellement explicite** : aucun contrat n'est renouvelé par simple
   absence de résiliation. Une décision `contract.renew` crée le nouveau contrat
   et clôt l'ancien.
8. **Facturation contractuelle** : une échéance doit être au statut
   `ready_to_invoice` ou `partially_invoiced` pour produire une facture. Le
   statut `fully_invoiced` n'est posé qu'à la validation de la facture.
9. **Séparation des tâches** : l'auteur d'une charge ne peut pas l'approuver ;
   une facture brouillon ne peut pas être encaissée ; une opération d'associé non
   validée ne peut pas être réglée.
10. **Paramètres sensibles** : `admin.settings` donne accès aux coordonnées de
    la société et au mot de passe SMTP. Le mot de passe est chiffré, jamais
    réaffiché, et son changement est journalisé sans sa valeur.
11. **Conversion d'un prospect** : l'opération exige `prospect.convert`,
    `client.manage`, `project.manage` et `contract.manage`. Elle crée les trois
    fiches dans une transaction unique et journalise chaque création ainsi que
    la conversion. Le prospect converti ne peut plus être modifié.
