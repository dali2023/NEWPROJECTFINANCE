# Dossier de recette — phase 1–2

**Application :** ONE ERP Finance & Project Control  
**Version testée :** 1.0.0 (phases 1 et 2)  
**Date :** 21 octobre 2026  
**Environnement :** Docker Compose — PHP 8.3.36, Symfony 7.4.21, PostgreSQL 16

## 1. Objet et méthode

Ce dossier présente les résultats des scénarios de recette du cahier des charges
v1.0 (§10) pour le périmètre livré : sécurité, référentiels, projets et contrats.

Les scénarios qui dépendent des modules de facturation, charges, trésorerie,
associés ou reporting (phases 3 à 6) sont signalés comme **non applicables** à
ce stade. Ils ne sont pas déclarés conformes par défaut : le calcul concerné est
testé isolément lorsqu'il appartient déjà au socle, et le scénario complet sera
exécuté avec le module correspondant.

Légende :

| Symbole | Signification |
|---|---|
| ✅ | Réussi — vérifié automatiquement |
| 🟡 | Partiel — socle ou calcul vérifié, parcours complet à venir |
| ⏳ | Non applicable — module non encore livré |

## 2. Synthèse

| Indicateur | Résultat |
|---|---|
| Tests automatisés | **23 tests, 98 assertions — OK** |
| Scénarios REC entièrement réussis | 4 (REC-001, REC-002, REC-015, REC-018) |
| Scénarios REC partiels | 6 |
| Scénarios REC en attente de phase ultérieure | 10 |
| Anomalies bloquantes | 0 |
| Anomalies majeures | 0 |

Commande d'exécution :

```bash
docker compose exec -u application php php bin/phpunit
```

## 3. Tests fonctionnels (REC-001 à REC-020)

| ID | Scénario | Résultat attendu | Statut | Preuve / remarque |
|---|---|---|---|---|
| REC-001 | Créer un client | Client enregistré avec un code unique | ✅ | `ClientManagementTest::testCreateClientThenDeactivateIt` |
| REC-002 | Créer un projet | Projet créé avec budget et client | ✅ | `ProjectCreationTest::testAdminCanCreateProjectWithExactTotals` |
| REC-003 | Saisir une facture | Montant facturé mis à jour après validation | ⏳ | Module facturation — phase 3 |
| REC-004 | Saisir un encaissement partiel | Reste à encaisser recalculé | 🟡 | Arithmétique validée (test A) ; parcours facture — phase 3 |
| REC-005 | Imputer un encaissement à deux factures | Soldes des deux factures corrects | ⏳ | Module encaissements — phase 3 |
| REC-006 | Saisir une dépense | Dépense visible dans les états selon son statut | ⏳ | Module charges — phase 3 |
| REC-007 | Enregistrer deux paiements d'une dépense | Cumul payé et solde dû corrects | ⏳ | Module charges — phase 3 |
| REC-008 | Répartir une charge indirecte | Total des allocations = montant réparti | 🟡 | Moteur `Money::allocate()` validé (test B) ; écran — phase 4 |
| REC-009 | Dépasser un budget | Écart et alerte affichés | ⏳ | Budget projet présent ; alertes — phase 3/4 |
| REC-010 | Enregistrer une avance d'associé | Solde des avances augmente, sans produit | 🟡 | Arithmétique validée (test C) ; module associés — phase 5 |
| REC-011 | Rembourser une avance | Solde dû diminue, sans charge d'exploitation | 🟡 | Arithmétique validée (test C) ; module associés — phase 5 |
| REC-012 | Effectuer un transfert interne | Deux comptes évoluent, trésorerie consolidée inchangée | 🟡 | Neutralité validée (test D) ; module trésorerie — phase 4 |
| REC-013 | Annuler une facture | Facture exclue des créances actives | ⏳ | Module facturation — phase 3 |
| REC-014 | Consulter un exercice clôturé | Utilisateur non autorisé ne peut pas modifier | 🟡 | `FiscalYearTest` : clôture, réouverture motivée, audit ; écritures financières — phase 3 |
| REC-015 | Consulter un projet non autorisé | Accès refusé | ✅ | `ProjectAccessTest::testUserCannotViewProjectOutsideHisScope` |
| REC-016 | Exporter un rapport | Fichier identique aux totaux de l'écran filtré | ⏳ | Exports PDF/XLSX — phase 6 |
| REC-017 | Répartir les bénéfices | Simulation sur la base de répartition définie | ⏳ | Module résultat/distribution — phase 5 |
| REC-018 | Consulter l'audit | Opération et auteur retrouvés | ✅ | `AccessDeniedAuditTest`, `RolePermissionAuditTest`, `FiscalYearTest` |
| REC-019 | Restaurer une sauvegarde | Données et justificatifs récupérables | 🟡 | Procédure documentée (`07-sauvegarde-restauration.md`) ; démonstration de restauration à planifier |
| REC-020 | Enregistrer une charge récurrente | Une seule échéance par période | ⏳ | Module charges fixes — phase 4 |

## 4. Tests de cohérence financière (A à F)

| Test | Attendu | Statut | Preuve |
|---|---|---|---|
| A — Encaissement partiel | 10 000 − 4 000 = 6 000 | 🟡 | `MoneyTest::testPartialReceiptLeavesExpectedBalance` ; module facture à livrer |
| B — Répartition indirecte | 3 000 → 1 500 / 900 / 600, total 3 000 | ✅ | `MoneyTest::testIndirectAllocationProducesExactShares` |
| C — Avance d'associé | 12 000 − 3 000 = 9 000 | 🟡 | `MoneyTest::testPartnerAdvanceRemainingBalance` ; module associés à livrer |
| D — Transfert interne | 2 000 neutre sur la trésorerie consolidée | 🟡 | `MoneyTest::testInternalTransferIsNeutralOnTotalTreasury` ; module trésorerie à livrer |
| E — Résultat d'un projet | 40 000 − 18 000 − 4 000 = 18 000 ; marge directe 22 000 | 🟡 | `MoneyTest::testProjectResultIsExact` ; modules charges à livrer |
| F — Contrôle des permissions | Accès refusé et journalisé | ✅ | `AccessDeniedAuditTest` + `ProjectAccessTest` |

Les tests B et F sont **intégralement satisfaits**. Les tests A, C, D et E sont
satisfaits au niveau de l'arithmétique exacte ; leur parcours métier complet
sera rejoué lors de la livraison des modules correspondants.

## 5. Détail des preuves automatisées

### 5.1. REC-001 — Création et désactivation d'un client

1. Connexion en administrateur.
2. Création d'un client avec code unique, type, coordonnées et conditions de
   paiement.
3. Redirection vers la fiche créée ; le client est présent en base.
4. Désactivation depuis la fiche ; le statut passe à « inactif ».
5. Une ligne d'audit `update / Client / success` conserve l'auteur et le motif.

### 5.2. REC-002 — Création d'un projet

1. Saisie d'un contrat de 40 000 TND HT, TVA 19 %, budget 18 000 TND.
2. La référence `PRJ-AAAA-NNNN` est générée automatiquement.
3. Contrôles en base :
   - HT = `40000.000` ;
   - TVA = `7600.000` ;
   - TTC = `47600.000` ;
   - budget = `18000.000`.
4. Un second test vérifie qu'un utilisateur sans `project.validate` ne peut pas
   créer un projet au statut « Gagné / contrat signé » : le formulaire est
   refusé et aucune ligne n'est enregistrée.

### 5.3. REC-014 — Exercice clôturé

1. Clôture d'un exercice ouvert : statut `closed`, date et auteur conservés,
   ligne d'audit `close`.
2. Tentative de réouverture avec un motif de moins de 10 caractères : refus,
   statut inchangé.
3. Réouverture avec motif valide : statut `open`, ligne d'audit `reopen`
   contenant le motif.

### 5.4. REC-015 — Projet hors périmètre

1. Un utilisateur dispose uniquement de `project.view_own`.
2. Il tente d'ouvrir un projet dont il n'est ni responsable ni membre.
3. Réponse HTTP **403** ; ligne d'audit `deny / Route / denied` avec l'URL
   demandée.

### 5.5. REC-018 — Journal d'audit

Vérifié sur plusieurs opérations :

- création et désactivation d'un client ;
- création d'un projet ;
- clôture et réouverture d'un exercice ;
- modification des permissions d'un rôle ;
- refus d'accès à une route d'administration ;
- refus d'accès à un projet hors périmètre.

Chaque ligne comporte l'auteur, l'action, l'entité, l'horodatage et le résultat.

## 6. Vérifications manuelles complémentaires

Réalisées dans un navigateur et par requêtes HTTP authentifiées :

| Vérification | Résultat |
|---|---|
| Connexion / déconnexion | OK |
| Tableau de bord | OK |
| Liste, création, modification et onglets d'un projet | OK |
| Liste, création, modification et désactivation d'un client | OK |
| Liste et création d'un fournisseur | OK |
| Exercices : liste, création, clôture, réouverture | OK |
| Types de projet : liste et création | OK |
| Utilisateurs : liste et création | OK |
| Rôles : liste et matrice de permissions | OK |
| Journal d'audit | OK |
| Refus d'accès administrateur pour un chef de projet | 403 + journalisé |

## 7. Réserves et limites

1. Les scénarios ⏳ ne peuvent pas être prononcés conformes avant la livraison
   des modules financiers des phases 3 à 6.
2. Les exports PDF/XLSX ne sont pas encore disponibles ; aucun export partiel
   n'est proposé.
3. La restauration de sauvegarde est documentée mais n'a pas encore fait l'objet
   d'un exercice formalisé sur un environnement vierge.
4. Les montants sont exacts au millime ; l'arrondi commercial est appliqué demi
   vers le haut de façon déterministe.

## 8. Conclusion de la recette phase 1–2

Le périmètre sécurité, référentiels, projets et contrats est **réceptionnable**
sous réserve de la validation formelle par la direction et du maintien des
scénarios en attente dans le plan de recette global.

| Rôle | Nom | Date | Visa |
|---|---|---|---|
| Responsable technique | | | |
| Responsable financier | | | |
| Direction générale | | | |
