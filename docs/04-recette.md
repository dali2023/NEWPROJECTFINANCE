# Dossier de recette — socle, module 15 et finances

**Application :** ONE ERP Finance & Project Control  
**Version testée :** 1.1.0 (socle, module 15, facturation, charges, trésorerie et associés)  
**Date :** 22 octobre 2026  
**Environnement :** Docker Compose — PHP 8.3.36, Symfony 7.4.21, PostgreSQL 16

## 1. Objet et méthode

Ce dossier présente les résultats des scénarios de recette du cahier des charges
v1.0 (§10) pour le périmètre livré : sécurité, référentiels, projets, contrats,
maintenance, réceptions, facturation, charges, trésorerie et associés.

Les scénarios dépendant des modules non livrés (répartition indirecte,
immobilisations, résultat annuel, distribution, exports PDF/XLSX et reporting)
sont signalés comme **non applicables** ou **partiels**. Ils ne sont pas déclarés
conformes par défaut.

Légende :

| Symbole | Signification |
|---|---|
| ✅ | Réussi — vérifié automatiquement et/ou en parcours HTTP |
| 🟡 | Partiel — service ou calcul vérifié, parcours complet à compléter |
| ⏳ | Non applicable — module non encore livré |

## 2. Synthèse

| Indicateur | Résultat |
|---|---|
| Tests automatisés | **41 tests, 189 assertions — OK** |
| Scénarios REC entièrement réussis | 24 (8 du socle + REC-021 à REC-036) |
| Scénarios REC partiels | 7 |
| Scénarios REC en attente de phase ultérieure | 5 |
| Anomalies bloquantes | 0 |
| Anomalies majeures | 0 |
| Syntaxe Twig | 64 fichiers valides |
| Conteneur Symfony | `lint:container` OK |
| Schéma Doctrine | `doctrine:schema:validate` OK |

Commande d'exécution :

```bash
docker compose exec -u application php php bin/phpunit
```

## 3. Tests fonctionnels (REC-001 à REC-020)

| ID | Scénario | Résultat attendu | Statut | Preuve / remarque |
|---|---|---|---|---|
| REC-001 | Créer un client | Client enregistré avec un code unique | ✅ | `ClientManagementTest::testCreateClientThenDeactivateIt` |
| REC-002 | Créer un projet | Projet créé avec budget et client | ✅ | `ProjectCreationTest::testAdminCanCreateProjectWithExactTotals` |
| REC-003 | Saisir une facture | Montant facturé mis à jour après validation | ✅ | `Module15AcceptanceTest` REC-023 : validation de facture et report sur l'échéance |
| REC-004 | Saisir un encaissement partiel | Reste à encaisser recalculé | ✅ | `FinanceAcceptanceTest` REC-027 : solde `7900.000` après paiement de `4000.000` |
| REC-005 | Imputer un encaissement à deux factures | Soldes des deux factures corrects | 🟡 | Règlement par facture livré ; l'imputation d'un même encaissement sur plusieurs factures n'est pas encore exposée |
| REC-006 | Saisir une dépense | Dépense visible dans les états selon son statut | ✅ | `FinanceAcceptanceTest` REC-025 ; écrans liste, création et fiche testés |
| REC-007 | Enregistrer deux paiements d'une dépense | Cumul payé et solde dû corrects | 🟡 | Paiement partiel et cumul portés par `ExpenseService` ; scénario à deux paiements à formaliser |
| REC-008 | Répartir une charge indirecte | Total des allocations = montant réparti | 🟡 | `Money::allocate()` validé ; écran de répartition non livré |
| REC-009 | Dépasser un budget | Écart et alerte affichés | ⏳ | Budget projet présent ; alertes budgétaires non livrées |
| REC-010 | Enregistrer une avance d'associé | Solde des avances augmente, sans produit | 🟡 | Opérations associés et validation livrées ; scénario d'apport à formaliser |
| REC-011 | Rembourser une avance | Solde dû diminue, sans charge d'exploitation | ✅ | `FinanceAcceptanceTest` : validation puis règlement, décaissement de trésorerie `PartnerRepayment` |
| REC-012 | Effectuer un transfert interne | Deux comptes évoluent, trésorerie consolidée inchangée | ⏳ | Neutralité validée dans `MoneyTest` ; transfert interne non exposé dans l'écran de trésorerie |
| REC-013 | Annuler une facture | Facture exclue des créances actives | 🟡 | Annulation réservée aux brouillons ; les factures validées doivent passer par un avoir |
| REC-014 | Consulter un exercice clôturé | Utilisateur non autorisé ne peut pas modifier | 🟡 | `FiscalYearTest` : clôture, réouverture motivée, audit ; garde à généraliser aux écritures financières |
| REC-015 | Consulter un projet non autorisé | Accès refusé | ✅ | `ProjectAccessTest::testUserCannotViewProjectOutsideHisScope` |
| REC-016 | Exporter un rapport | Fichier identique aux totaux de l'écran filtré | ⏳ | Exports PDF/XLSX non livrés |
| REC-017 | Répartir les bénéfices | Simulation sur la base de répartition définie | ⏳ | Module résultat/distribution non livré |
| REC-018 | Consulter l'audit | Opération et auteur retrouvés | ✅ | `AccessDeniedAuditTest`, `RolePermissionAuditTest`, `FiscalYearTest` |
| REC-019 | Restaurer une sauvegarde | Données et justificatifs récupérables | 🟡 | Procédure documentée (`07-sauvegarde-restauration.md`) ; exercice de reprise à planifier |
| REC-020 | Enregistrer une charge récurrente | Une seule échéance par période | ⏳ | Charges récurrentes non livrées |

## 4. Tests de cohérence financière (A à F)

| Test | Attendu | Statut | Preuve |
|---|---|---|---|
| A — Encaissement partiel | 10 000 − 4 000 = 6 000 | ✅ | `FinanceAcceptanceTest` REC-027 : reste `7900.000` TTC, dont `6000.000` HT ; `MoneyTest` |
| B — Répartition indirecte | 3 000 → 1 500 / 900 / 600, total 3 000 | ✅ | `MoneyTest::testIndirectAllocationProducesExactShares` |
| C — Avance d'associé | 12 000 − 3 000 = 9 000 | 🟡 | `MoneyTest` ; opérations associés livrées, scénario d'avance à formaliser |
| D — Transfert interne | 2 000 neutre sur la trésorerie consolidée | 🟡 | `MoneyTest` ; écran de transfert interne non livré |
| E — Résultat d'un projet | 40 000 − 18 000 − 4 000 = 18 000 ; marge directe 22 000 | 🟡 | `MoneyTest` ; charges et facturation livrées, reporting de résultat à compléter |
| F — Contrôle des permissions | Accès refusé et journalisé | ✅ | `AccessDeniedAuditTest` + `ProjectAccessTest` |

## 5. Tests du module 15 et des finances (REC-021 à REC-036)

| ID | Scénario | Statut | Preuve et vérification |
|---|---|---|---|
| REC-021 | Cycle de vie d'un contrat | ✅ | `Module15AcceptanceTest::testRec021…` — totaux contractuels `100000.000`, reste à facturer exact |
| REC-022 | Échéance prête non facturée automatiquement | ✅ | `testRec022…` — la création d'un brouillon ne modifie ni le statut de facturation ni le facturé |
| REC-023 | Validation d'une facture issue d'une échéance | ✅ | `testRec023…` — report de la référence, statut `fully_invoiced`, facturé `10000.000` |
| REC-024 | Garde-fou de facturation | ✅ | `testRec024…` — action POST refusée si l'échéance n'est pas `ready_to_invoice` ou `partially_invoiced` |
| REC-025 | Séparation des tâches sur les charges | ✅ | `FinanceAcceptanceTest::testRec025…` — l'auteur ne peut pas approuver sa propre dépense |
| REC-026 | Encaissement d'une facture brouillon | ✅ | `testRec026…` — règlement refusé avant validation |
| REC-027 | Encaissement partiel et trésorerie | ✅ | `testRec027…` — statut `partially_paid`, solde `7900.000`, mouvement `client_receipt` de `4000.000` |
| REC-028 | Réceptions et réserves | ✅ | `testRec028…` — levée individuelle, statuts `partially_lifted` puis `lifted` |
| REC-029 | Renouvellement explicite accepté | ✅ | `testRec029…` — nouveau contrat `85000.000`, ancien contrat clôturé, décision historisée |
| REC-030 | Retenue de garantie | ✅ | `testRec030…` — libération partielle `4000.000`, reste `6000.000`, puis statut `released` |
| REC-031 | Calendrier contractuel | ✅ | `testRec031…` — l'échéance à 45 jours apparaît dans l'horizon de 6 mois |
| REC-032 | Report d'échéance | ✅ | `testRec032…` — nouvelle date conservée et historique avec motif et auteur |
| REC-033 | Génération des échéances de maintenance | ✅ | `testRec033…` — 12 échéances mensuelles, seconde exécution : 0 doublon |
| REC-034 | Délais d'alerte paramétrables | ✅ | `testRec034…` — contrat à 40 jours : alertes J-90 et J-60, pas J-30 ni J-7 |
| REC-035 | Idempotence des alertes | ✅ | `testRec035…` — seconde génération : 0 alerte, total inchangé |
| REC-036 | Facturation à réaliser | ✅ | `testRec036…` — prêtes `15000.000`, en retard `10000.000`, bloquées exclues |

### 5.1. Vérifications complémentaires des finances

Deux scénarios supplémentaires, non numérotés dans le cahier des charges,
couvrent les parcours de trésorerie :

- `FinanceAcceptanceTest::testApprovedExpensePaymentCreatesOutflowAndUpdatesBalance` :
  approbation par un autre utilisateur, paiement partiel de `500.000`, solde de
  charge `690.000`, décaissement `expense_payment` et solde de compte `99500.000`.
- `FinanceAcceptanceTest::testPartnerSettlementRequiresValidationAndCreatesTreasuryOutflow` :
  règlement refusé avant validation, puis règlement de `3000.000`, statut `paid`,
  décaissement `partner_repayment` et solde de compte `97000.000`.

## 6. Vérifications manuelles complémentaires

Réalisées dans un navigateur et par requêtes HTTP authentifiées :

| Vérification | Résultat |
|---|---|
| Connexion / déconnexion | OK |
| Tableau de bord | OK |
| Liste, création, modification et onglets d'un projet | OK |
| Liste, création et désactivation d'un client | OK |
| Liste et création d'un fournisseur | OK |
| Exercices : liste, création, clôture, réouverture | OK |
| Types de projet, utilisateurs et rôles | OK |
| Journal d'audit | OK |
| Registre des contrats et fiche à 9 onglets | OK |
| Contrats de maintenance et génération d'échéances | OK |
| Réceptions provisoires/définitives et réserves | OK |
| Renouvellement explicite | OK |
| Retenues de garantie | OK |
| Facturation à réaliser et création de brouillon | OK |
| Calendrier contractuel et centre de notifications | OK |
| Factures : liste, brouillon, validation, encaissement | OK |
| Charges : liste, création, approbation, paiement | OK |
| Trésorerie : comptes, mouvements, soldes | OK |
| Associés : fiche, opérations, validation et règlement | OK |

## 7. Réserves et limites

1. Les scénarios ⏳ ne peuvent pas être prononcés conformes avant la livraison
   des modules de répartition, d'immobilisations, de résultat/distribution et de
   reporting.
2. Les exports PDF/XLSX ne sont pas disponibles ; aucun export partiel n'est
   proposé.
3. Un encaissement est rattaché à une facture ; l'imputation d'un règlement
   unique sur plusieurs factures reste à livrer.
4. Les alertes contractuelles sont générées et consultables dans l'application ;
   l'envoi de courriels métier n'est pas encore activé.
5. La restauration de sauvegarde est documentée mais n'a pas encore fait l'objet
   d'un exercice formalisé sur un environnement vierge.
6. Le garde-fou « exercice clôturé » est testé sur l'administration des
   exercices ; son application à chaque écriture financière reste à généraliser.
7. Les montants sont exacts au millime ; l'arrondi commercial est appliqué demi
   vers le haut de façon déterministe.

## 8. Conclusion

Le périmètre socle, module 15 et finances (facturation, charges, trésorerie et
associés) est **réceptionnable** sous réserve de la validation formelle par la
direction et du maintien des scénarios partiels ou en attente dans le plan de
recette global.

| Rôle | Nom | Date | Visa |
|---|---|---|---|
| Responsable technique | | | |
| Responsable financier | | | |
| Direction générale | | | |
