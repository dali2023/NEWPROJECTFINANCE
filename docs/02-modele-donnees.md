# Modèle de données — ONE ERP Finance & Project Control

Implémentation du §5 du cahier des charges v1.0. Cible : **PostgreSQL 16**.

## Principes

1. Clé primaire `id` : `UUID` (généré par l'application) — facilite les fusions
   et évite l'énumération des identifiants.
2. Références métier uniques par type de document (`code`, `reference`).
3. Montants : `NUMERIC(18,3)` — jamais de flottant binaire (§5.1).
4. Taux et pourcentages : `NUMERIC(9,4)` (ex. 19.0000 pour 19 %).
5. Toutes les tables portent `created_at`, `created_by` ; les tables de
   documents portent en plus `updated_at`, `updated_by`.
6. Suppression logique : `deleted_at` pour les référentiels utilisés,
   `status` pour les documents (jamais de suppression physique après
   validation).
7. Horodatage en `TIMESTAMPTZ`, fuseau `Africa/Tunis`.
8. Contraintes d'intégrité portées par la base **et** par le domaine.

## Tables

### Référentiel et sécurité

| Table | Clés / colonnes notables | Contraintes |
|---|---|---|
| `users` | `id`, `username` UQ, `email` UQ, `password_hash`, `full_name`, `is_active`, `last_login_at`, `created_at` | `username` unique, insensible à la casse |
| `roles` | `id`, `code` UQ (`ROLE_FINANCE`…), `label`, `description`, `is_system` | rôles système non supprimables |
| `permissions` | `id`, `code` UQ (`project.manage`…), `label`, `module` | |
| `role_permissions` | `role_id`, `permission_id` | PK composite |
| `user_roles` | `user_id`, `role_id` | PK composite (rôles cumulables) |
| `fiscal_years` | `id`, `label` UQ, `start_date`, `end_date` | `status` ∈ {`open`, `closing`, `closed`} ; `start_date < end_date` ; pas de chevauchement |
| `audit_logs` | `id`, `user_id`, `action`, `entity`, `entity_id`, `occurred_at`, `before_data`, `after_data`, `result`, `ip_address` | index `(entity, entity_id)`, `(occurred_at)` |
| `documents` | `id`, `name`, `storage_path` UQ, `mime_type`, `size_bytes`, `owner_type`, `owner_id`, `uploaded_at`, `uploaded_by` | stockage hors `public/` |

### Tiers

| Table | Colonnes | Contraintes |
|---|---|---|
| `clients` | `id`, `code` UQ, `name`, `client_type` ∈ {`public`, `private`, `association`, `other`}, `tax_identifier`, `address`, `city`, `email`, `phone`, `payment_terms_days`, `is_active`, `notes` | suppression logique uniquement |
| `suppliers` | `id`, `code` UQ, `name`, `tax_identifier`, `address`, `email`, `phone`, `service_category`, `payment_terms_days`, `is_active` | idem |

### Projets et contrats

| Table | Colonnes | Contraintes |
|---|---|---|
| `project_types` | `id`, `code` UQ, `label`, `is_active` | modifiable par l'administrateur (§4.3) |
| `projects` | `id`, `code` UQ, `name`, `client_id`, `project_type_id`, `manager_user_id`, `contract_reference`, `tender_reference`, `status`, `signed_on`, `start_date`, `end_date`, `planned_delivery_date`, `currency_code`, `contract_amount_ht`, `vat_rate`, `vat_amount`, `contract_amount_ttc`, `budget_amount`, `payment_terms`, `probability`, `notes`, `created_at`, `created_by` | `status` ∈ 9 états (§4.3) ; montants ≥ 0 ; `currency_code` ISO-4217 |
| `project_milestones` | `id`, `project_id`, `label`, `planned_date`, `amount_ht`, `status`, `description`, `position` | unicité `(project_id, position)` |

### Facturation, encaissements (module M04)

| Table | Colonnes | Contraintes |
|---|---|---|
| `customer_invoices` | `id`, `invoice_reference` UQ, `client_id`, `project_id`, `milestone_id`, `invoice_date`, `due_date`, `amount_ht`, `vat_amount`, `amount_ttc`, `status`, `invoice_kind` (`invoice` \| `advance` \| `credit_note`), `document_id`, `notes` | `status` ∈ {`draft`, `validated`, `partially_paid`, `paid`, `cancelled`} |
| `receipts` | `id`, `reference` UQ, `client_id`, `project_id`, `treasury_account_id`, `receipt_date`, `amount`, `payment_method`, `status`, `external_reference`, `document_id`, `notes` | `status` ∈ {`draft`, `confirmed`, `cancelled`} |
| `customer_invoice_allocations` | `id`, `receipt_id`, `invoice_id`, `allocated_amount`, `created_at`, `created_by` | `0 < allocated_amount ≤ solde` (contrôle applicatif transactionnel) ; UQ `(receipt_id, invoice_id)` |

### Charges (module M05)

| Table | Colonnes | Contraintes |
|---|---|---|
| `expense_categories` | `id`, `code` UQ, `name`, `family`, `default_allocation_type`, `is_recurring_eligible`, `is_active` | |
| `recurring_expenses` | `id`, `label`, `expense_category_id`, `periodicity` ∈ {`monthly`, `quarterly`, `half_yearly`, `yearly`}, `amount_ht`, `vat_rate`, `supplier_id`, `project_id`, `next_due_date`, `end_date`, `is_active`, `created_by` | génération idempotente (§REC-020) |
| `expenses` | `id`, `reference` UQ, `supplier_id`, `expense_category_id`, `project_id` (nullable = frais généraux), `campaign_id`, `tender_id`, `asset_id`, `recurring_expense_id`, `expense_date`, `due_date`, `label`, `amount_ht`, `vat_rate`, `vat_amount`, `amount_ttc`, `currency_code`, `paid_amount` (dénormalisé, transactionnel), `status`, `is_committed`, `is_budgeted`, `created_by`, `approved_by`, `approved_at` | `status` ∈ {`draft`, `submitted`, `approved`, `rejected`, `partially_paid`, `paid`, `cancelled`} ; `sum(expense_payments) ≤ amount_ttc` |
| `expense_payments` | `id`, `expense_id`, `treasury_account_id`, `payment_date`, `amount`, `payment_method`, `reference`, `document_id`, `created_by` | `amount > 0` |

### Charges indirectes (module M07)

| Table | Colonnes | Contraintes |
|---|---|---|
| `allocation_rules` | `id`, `name`, `allocation_method` ∈ {`fixed_rate`, `revenue_share`, `time_share`, `equal_split`, `manual`}, `valid_from`, `valid_to`, `status`, `created_by`, `approved_by` | |
| `expense_allocations` | `id`, `expense_id` (ou `source_type`/`source_id` pour une charge non individualisée), `project_id`, `allocation_rule_id`, `allocation_base`, `allocation_rate`, `allocated_amount`, `version`, `status`, `allocated_at` | Σ allocations validées ≤ montant à répartir, écart d'arrondi imputé de façon déterministe au dernier projet |

### Marketing et appels d'offres (module M06)

| Table | Colonnes | Contraintes |
|---|---|---|
| `marketing_campaigns` | `id`, `code` UQ, `name`, `campaign_type`, `start_date`, `end_date`, `budget_amount`, `responsible_user_id`, `status`, `objective`, `notes` | |
| `campaign_projects` | `campaign_id`, `project_id` | PK composite ; évite le double comptage du CA attribué |
| `tenders` | `id`, `reference` UQ, `client_id`, `title`, `submission_deadline`, `estimated_budget`, `bid_bond_amount`, `bid_bond_status` ∈ {`none`, `deposited`, `refunded`, `lost`}, `status`, `result`, `project_id` | caution suivie comme montant récupérable (§4.6) |

### Immobilisations (module M08)

| Table | Colonnes | Contraintes |
|---|---|---|
| `assets` | `id`, `asset_code` UQ, `name`, `asset_type` ∈ {`vehicle`, `it_equipment`, `furniture`, `other`}, `supplier_id`, `purchase_date`, `purchase_cost`, `serial_number`, `location`, `assigned_user_id`, `useful_life_months`, `residual_value`, `status` ∈ {`active`, `in_repair`, `disposed`, `retired`}, `project_id`, `is_leased`, `lease_monthly_amount`, `lease_end_date`, `warranty_end_date` | amortissement de gestion uniquement (§4.8) |
| `asset_expenses` | `asset_id`, `expense_id` | PK composite (carburant, entretien, assurance) |

### Trésorerie (module M09)

| Table | Colonnes | Contraintes |
|---|---|---|
| `treasury_accounts` | `id`, `name`, `account_type` ∈ {`bank`, `cash`, `other`}, `currency_code`, `opening_balance`, `opening_date`, `bank_details`, `status`, `is_active` | |
| `treasury_transactions` | `id`, `reference` UQ, `treasury_account_id`, `transaction_date`, `transaction_type`, `direction` ∈ {`in`, `out`}, `amount`, `payment_method`, `source_type`, `source_id`, `transfer_group_id`, `status`, `document_id`, `created_by` | transfert interne = 2 lignes partageant `transfer_group_id` ; neutralisé dans le solde consolidé (Test D) |

### Capital et associés (module M10)

| Table | Colonnes | Contraintes |
|---|---|---|
| `partners` | `id`, `code` UQ, `name`, `partner_type`, `entry_date`, `exit_date`, `capital_percentage` NUMERIC(9,4), `subscribed_capital`, `paid_capital`, `status`, `email`, `phone`, `notes` | Σ pourcentages = 100 % lorsque le capital est intégralement représenté |
| `partner_transactions` | `id`, `partner_id`, `transaction_type` ∈ {`capital_contribution`, `capital_release`, `current_account_advance`, `advance_repayment`, `interest`, `dividend_proposed`, `dividend_decided`, `dividend_paid`}, `transaction_date`, `amount`, `treasury_account_id`, `reference`, `document_id`, `status`, `approved_by` | avance ≠ produit ; remboursement ≠ charge (§6.13, Test C) |

### Résultat et distributions (module M11)

| Table | Colonnes | Contraintes |
|---|---|---|
| `annual_results` | `id`, `fiscal_year_id` UQ, `management_revenue`, `direct_expenses`, `allocated_indirect_expenses`, `general_expenses`, `adjustments`, `management_result`, `status` ∈ {`draft`, `prepared`, `validated`, `locked`}, `prepared_by`, `validated_by`, `validated_at` | distinct du résultat comptable officiel (§4.11) |
| `annual_result_adjustments` | `id`, `annual_result_id`, `label`, `amount`, `reason`, `created_by` | justification obligatoire |
| `profit_distributions` | `id`, `fiscal_year_id`, `total_amount`, `decision_reference`, `decision_date`, `basis_method`, `status` ∈ {`simulated`, `decided`, `paid`}, `approved_by` | une distribution définitive exige une décision validée |
| `profit_distribution_lines` | `id`, `distribution_id`, `partner_id`, `allocation_basis`, `percentage`, `amount`, `paid_amount`, `status` | Σ montants = `total_amount` |

### Prévisions de trésorerie (module M09)

| Table | Colonnes | Contraintes |
|---|---|---|
| `cash_forecasts` | `id`, `fiscal_year_id`, `label`, `expected_date`, `direction`, `amount`, `certainty` ∈ {`confirmed`, `probable`, `hypothesis`}, `source_type`, `source_id`, `project_id`, `notes` | §6.15 : niveau de certitude obligatoire |

## Relations essentielles (rappel §5.3)

```
clients 1---n projects 1---n project_milestones
clients 1---n customer_invoices 1---n customer_invoice_allocations n---1 receipts
projects 1---n receipts
projects 1---n expenses n---n assets (asset_expenses)
expenses 1---n expense_payments n---1 treasury_accounts
expenses 1---n expense_allocations n---1 projects
expenses n---1 allocation_rules
marketing_campaigns n---n projects ; marketing_campaigns 1---n expenses
tenders n---1 clients ; tenders 1---0..1 projects
partners 1---n partner_transactions
fiscal_years 1---1 annual_results 1---n profit_distributions 1---n profit_distribution_lines
users n---n roles n---n permissions ; documents / audit_logs = traçabilité transversale
```

## Contraintes d'intégrité (§5.4) — implémentation

| # | Contrainte | Où |
|---|---|---|
| 1 | Montants positifs ou nuls | `CHECK` sur toutes les colonnes de montant |
| 2 | Références métier uniques | index `UNIQUE` par type + générateur applicatif |
| 3 | Paiement validé non modifiable | garde de domaine (`ExpensePaymentGuard`) + statut |
| 4 | Pas de double imputation | UQ `(receipt_id, invoice_id)`, UQ `(expense_id, treasury_transaction_id)` |
| 5 | Facture annulée hors créances | filtres de repository (`status != cancelled`) |
| 6 | Dépense annulée hors charges | idem |
| 7 | Allocations bornées au montant source | contrôle transactionnel + `CHECK allocated_amount >= 0` |
| 8 | Σ pourcentages capital = 100 % | contrôle applicatif au niveau du référentiel associés |
| 9 | Exercice clôturé non modifiable | `FiscalYearGuard` + voter `FiscalYearVoter` |
| 10 | Sauvegarde / restauration | procédure `docs/07-sauvegarde-restauration.md` |
