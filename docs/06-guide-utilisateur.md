# Guide d'utilisation

**Application :** ONE ERP Finance & Project Control  
**Version :** 1.1 — socle, module 15 et finances  
**Public :** administrateurs, direction, responsables financiers, commerciaux et
chefs de projet

## 1. Se connecter

1. Ouvrir `http://localhost:8090` (adresse de développement).
2. Saisir l'identifiant **ou** l'adresse électronique, puis le mot de passe.
3. Cliquer sur **Se connecter**.

Comptes de démonstration (mot de passe `Demo1234!`) :

| Identifiant | Profil |
|---|---|
| `admin` | Administrateur |
| `direction` | Direction générale |
| `finance` | Responsable financier |
| `commercial` | Responsable commercial |
| `chefprojet` | Chef de projet |

En cas d'oubli, contacter un administrateur : la réinitialisation est effectuée
depuis la fiche utilisateur. Toute connexion est journalisée.

## 2. Se repérer

Le bandeau latéral regroupe :

- **Pilotage** : tableau de bord.
- **Projets et tiers** : projets, clients, fournisseurs.
- **Contrats** : registre des contrats, calendrier, facturation à réaliser,
  notifications.
- **Finance** : factures, charges, trésorerie, associés.
- **Administration** : exercices, types de projet, utilisateurs, rôles, audit,
  société et paramètres email, selon vos permissions.

Le bandeau supérieur affiche votre nom, le bouton **Imprimer** et le bouton
**Déconnexion**. Les montants sont exprimés en dinars tunisiens (TND).

## 3. Tableau de bord

Le tableau de bord présente :

- le montant des contrats signés (hors opportunités et projets perdus) ;
- le nombre de projets suivis ;
- la répartition des projets par statut ;
- les projets récents auxquels vous avez accès ;
- les prochaines échéances et les tiers actifs.

Les indicateurs de facturation, d'encaissement, de charges et de trésorerie
reposent sur les données des modules livrés. Les indicateurs de résultat,
distribution et reporting restent à activer avec les phases suivantes.

## 4. Gérer les clients

### 4.1. Consulter et rechercher

Menu **Clients** : la liste affiche le code, le nom, le type, le contact, la
ville et le statut. Utilisez la recherche et les filtres, puis **Filtrer**.

### 4.2. Créer un client

1. Cliquer sur **Nouveau client**.
2. Renseigner le code (unique), le nom, le type (entreprise, organisme public,
   association, autre), les coordonnées et les conditions de paiement.
3. Enregistrer.

Le client est actif par défaut. Il peut être désactivé depuis sa fiche ; les
projets existants restent consultables.

### 4.3. Modifier

Ouvrir la fiche puis **Modifier**. Toute modification est journalisée.

## 5. Gérer les fournisseurs

Menu **Fournisseurs** : création et modification sur le même principe. La
catégorie de prestation permet de regrouper les achats (informatique, conseil,
comptabilité, etc.). Les fournisseurs inactifs ne sont plus proposés dans les
nouveaux documents.

## 6. Gérer les projets

### 6.1. Liste des projets

La liste présente la référence, le nom, le client, le type, le responsable, le
montant contractuel, le budget, le statut et les actions.

Filtres disponibles : recherche libre, statut, client, type, responsable. Un
utilisateur qui ne dispose que de `project.view_own` ne voit que les projets
dont il est responsable ou membre.

### 6.2. Créer un projet

1. Menu **Projets** → **Nouveau projet**.
2. **Identification** : intitulé, client, type, responsable, statut.
3. **Contrat et calendrier** : référence du contrat ou du marché, référence de
   l'appel d'offres, dates, devise, conditions de paiement.
4. **Montants** : montant HT, taux de TVA en pourcentage, budget prévisionnel
   des charges, probabilité de conclusion.
5. **Échéancier** : ajouter les jalons (libellé, date prévue, montant HT,
   statut). La somme des jalons peut différer du montant contractuel ; l'écart
   est affiché.
6. **Commentaires**, puis **Créer le projet**.

La référence `PRJ-AAAA-NNNN` est générée automatiquement et ne doit pas être
modifiée. La TVA et le TTC sont recalculés à l'enregistrement, au millime.

### 6.3. Statuts et validation contractuelle

| Statut | Usage |
|---|---|
| Brouillon | Saisie en cours |
| En préparation commerciale | Montage de l'offre |
| Opportunité | Proposition envoyée, non signée |
| Gagné / contrat signé | Contrat obtenu |
| En cours d'exécution | Projet en production |
| En attente / suspendu | Projet gelé temporairement |
| Livré | Prestations réalisées |
| Clôturé | Projet terminé et verrouillé |
| Abandonné / perdu | Opportunité non concrétisée |

Le passage au statut **Gagné / contrat signé** (ou au-delà) exige la permission
`project.validate`. Sans cette permission, le formulaire affiche un refus et
aucune donnée n'est enregistrée. Les statuts « Opportunité », « Brouillon » et
« En préparation commerciale » ne comptent pas dans les contrats signés.

### 6.4. Fiche projet

Les onglets disponibles :

- **Synthèse** : informations générales, client, responsable, montants,
  budget et alerte sur l'écart éventuel avec l'échéancier.
- **Contrat et échéancier** : références, dates, conditions et jalons.
- **Budget et rentabilité** : budget prévisionnel, consommation, facturation,
  charges et indicateurs de marge issus des modules financiers.
- **Facturation**, **Charges** et **Documents** : opérations rattachées au
  projet, selon vos permissions.
- **Historique** : opérations journalisées sur le projet.

Les actions **Modifier** et **Clôturer** dépendent de vos permissions et du
statut du projet. Un projet clôturé n'est plus modifiable.

## 7. Contrats, facturation, charges, trésorerie et associés

### 7.1. Registre et fiche contrat

Menu **Contrats** : la liste présente la référence, le client, le projet, la
période, le montant, le statut et le reste à facturer. Utilisez la recherche et
les filtres, puis ouvrez la fiche.

La fiche contient neuf onglets :

| Onglet | Contenu |
|---|---|
| Synthèse | Parties, dates, montants, statut et indicateurs |
| Conditions | Mode de renouvellement, préavis, conditions particulières |
| Maintenance | Contrats de maintenance et génération des échéances |
| Échéances | Jalons contractuels, statut de réalisation et de facturation |
| Réceptions et réserves | Réceptions provisoires/définitives et réserves |
| Renouvellements | Décisions et contrats issus du renouvellement |
| Retenues de garantie | Montants, éligibilité et libérations |
| Documents | Dépôt et téléchargement des justificatifs |
| Historique | Opérations tracées sur le contrat |

Les boutons **Modifier**, **Renouveler**, **Alertes** et les actions d'onglet
sont affichés selon vos permissions et le statut du contrat.

### 7.2. Échéances et facturation

Une échéance possède deux statuts distincts :

- **réalisation** : à venir, échéance atteinte, réalisée, suspendue, annulée ;
- **facturation** : non facturable, à vérifier, prête à facturer, facture émise,
  partiellement facturée, facturée intégralement, bloquée.

Une échéance ne devient **facturée** qu'après validation de la facture. Le menu
**Contrats → Facturation à réaliser** regroupe les échéances prêtes, en retard et
bloquées. Le bouton de création de facture n'est proposé que pour une échéance
prête ou partiellement facturée.

### 7.3. Maintenance, réceptions et renouvellement

- **Maintenance** : créer un contrat de maintenance avec sa périodicité, ses
  dates et son montant. L'action **Générer les échéances** produit les périodes
  manquantes ; une seconde exécution ne crée pas de doublon.
- **Réceptions** : enregistrer la date prévue, la date effective, le PV et le
  statut. Une réception définitive doit être validée par un utilisateur habilité.
- **Réserves** : ajouter une réserve à une réception, lui affecter un
  responsable et une date de levée, puis la lever individuellement. Le statut
  global passe à « partiellement levées » ou « levées ».
- **Renouvellement** : aucune reconduction n'est automatique. Une décision
  **Acceptée** crée un nouveau contrat, relie les deux fiches et clôt le contrat
  arrivé à échéance. La décision et ses dates sont historisées.
- **Retenues** : enregistrer le montant et la date d'éligibilité, puis libérer
  tout ou partie. Le reste à libérer est recalculé.

### 7.4. Calendrier et alertes

Menu **Contrats → Calendrier** : échéances contractuelles, expirations,
réceptions, fins de garantie, jalons et renouvellements sur un horizon
configurable.

Deux affichages sont disponibles :

- **Agenda** : échéances regroupées par jour, avec le nombre d'échéances, les
  badges « Aujourd'hui »/« Demain », le contrat, le client et le statut. Un clic
  ouvre la fiche du contrat.
- **Vue mensuelle** : grille du mois (lundi → dimanche), navigation mois
  précédent/suivant, retour à aujourd'hui, pastilles colorées par type
  d'échéance et légende. La liste des échéances du mois est affichée sous la
  grille.

Les filtres par type d'échéance et par horizon (3 à 24 mois) s'appliquent aux
deux vues. La vue mensuelle est bornée au mois courant et à l'horizon choisi ;
les flèches sont désactivées aux bornes.

Menu **Contrats → Notifications** : alertes générées selon les délais
paramétrés. Une alerte peut être prise en charge, résolue ou classée sans objet.
La génération est idempotente : relancer l'action ne crée pas de doublon. Voir
[`08-parametrage-alertes.md`](08-parametrage-alertes.md).

### 7.5. Factures

Menu **Factures** :

1. **Nouvelle facture** : client, projet/contrat, dates, lignes et taux de TVA.
2. **Valider** : la facture devient non modifiable ; si elle provient d'une
   échéance, la référence et le montant sont reportés sur celle-ci.
3. **Envoyer** : marque la facture comme envoyée.
4. **Régler** : saisir le montant, la date, le moyen et éventuellement le compte
   de trésorerie. Un encaissement partiel met la facture à « partiellement
   réglée ».
5. **Annuler** : réservé aux brouillons. Une facture validée doit être corrigée
   par un avoir.

Une facture brouillon ou annulée ne peut pas être encaissée.

### 7.6. Charges, trésorerie et associés

- **Charges** : créer une dépense, la soumettre, puis l'approuver avec un autre
  utilisateur. L'auteur ne peut jamais approuver sa propre dépense. Le paiement
  n'est possible qu'après approbation ; chaque paiement met à jour le cumul payé
  et le solde dû, et crée un décaissement de trésorerie.
- **Trésorerie** : gérer les comptes bancaires et caisses, consulter les
  mouvements et rapprocher un mouvement. Le solde est calculé à partir du solde
  d'ouverture et des mouvements, jamais saisi à la main.
- **Associés** : créer une fiche, enregistrer un apport, un remboursement ou un
  dividende, valider l'opération, puis la régler. Une opération non validée ne
  peut pas être réglée ; le règlement crée le mouvement de trésorerie
  correspondant.

## 8. Administrer

### 8.1. Exercices financiers

Menu **Administration → Exercices**.

- **Créer** un exercice avec un libellé et une période. Deux exercices ne
  peuvent pas se chevaucher.
- **Clôturer** un exercice : il passe en lecture seule.
- **Réouvrir** un exercice clôturé : permission dédiée et motif d'au moins
  10 caractères obligatoires. L'opération est journalisée.

### 8.2. Types de projet

Créer, renommer, ordonner ou désactiver les types proposés dans les fiches
projet. Un type utilisé par un projet existant ne doit pas être supprimé.

### 8.3. Utilisateurs

Menu **Administration → Utilisateurs**.

- Créer un compte : nom complet, identifiant, courriel, rôles, mot de passe.
- Modifier les informations et les rôles.
- Désactiver un compte : l'utilisateur ne peut plus se connecter, mais son
  historique est conservé.

Le mot de passe doit contenir au moins 8 caractères. Il n'est jamais affiché.

### 8.4. Rôles et permissions

Menu **Administration → Rôles**. La matrice présente les permissions par module.
Cocher ou décocher puis **Enregistrer les permissions**. Toute modification est
journalisée avec la liste avant/après.

L'administrateur technique dispose d'un accès global, mais les règles de
séparation des tâches restent appliquées : il ne peut pas, par défaut, valider
les opérations financières à la place des responsables.

### 8.5. Journal d'audit

Menu **Administration → Audit**. Filtrez par utilisateur, action, entité,
module, résultat ou période. Chaque ligne indique l'auteur, l'action,
l'horodatage, l'adresse IP et, le cas échéant, les valeurs avant/après.

Les refus d'accès apparaissent avec l'action `deny` et le résultat `denied`.

### 8.6. Société et paramètres email

Deux écrans distincts, réservés à la permission `admin.settings` :

**Administration → Société** renseigne la raison sociale, le nom commercial, le
matricule fiscal, l'email de contact, les téléphones, l'adresse et le logo. Ces
informations alimentent la barre latérale, l'écran de connexion, l'en-tête
d'impression et les emails de rappel. Le logo est stocké hors du répertoire
public ; il est servi par une route dédiée et peut être supprimé sans effacer
la fiche.

**Administration → Email** configure le serveur SMTP des rappels contractuels :
hôte, port, chiffrement, identifiants, adresse et nom d'expédition. Le mot de
passe est chiffré et n'est jamais réaffiché ; laisser le champ vide conserve la
valeur existante. Le bouton **Envoyer un email de test** valide la
configuration avec les paramètres déjà enregistrés, même si l'envoi automatique
est désactivé.

Pour activer les rappels :

1. renseigner et enregistrer les paramètres SMTP ;
2. envoyer un email de test et vérifier sa réception ;
3. cocher **Activer l'envoi automatique** puis enregistrer ;
4. planifier la commande `app:contracts:send-reminders` (toutes les 15 minutes
   par exemple) ou utiliser **Envoyer les rappels** dans le centre de
   notifications.

Le centre de notifications indique, pour chaque alerte, si l'email est envoyé,
en attente ou en échec. Un échec est réessayé aux exécutions suivantes, dans la
limite de trois tentatives. Une nouvelle configuration email activée remet le
compteur à zéro.

Le détail des champs, du chiffrement, de la planification et du dépannage figure
dans [`09-parametrage-societe-email.md`](09-parametrage-societe-email.md).

## 9. Exporter et imprimer

Chaque Ǹcran de liste (clients, fournisseurs, projets, contrats, facturation,
alertes, factures, charges, trǸsorerie, associǸs, utilisateurs, r��les,
exercices, types de projet, audit) propose deux actions en haut �� droite :

- **Exporter Excel** tǸlǸcharge un vǸritable classeur `.xlsx` contenant les
  donnǸes filtrǸes de la liste.
- **Imprimer** ouvre la bo��te de dialogue d'impression du navigateur. Le bouton
  **Imprimer** du bandeau supǸrieur est Ǹgalement disponible sur les fiches
  dǸtaillǸes.

### 9.1. Contenu d'un export Excel

Le classeur reprend le titre de l'Ǹcran, les filtres actifs et la date
d'Ǹdition. La ligne de titres est figǸe et filtrable, les colonnes sont
dimensionnǸes et les montants sont de vrais nombres au format `# ##0,000` :
ils peuvent Ǧtre triǸs et additionnǸs dans Excel. Une ligne **TOTAL** est ajoutǸe
pour les colonnes de montants. Les dates sont au format `jj/mm/aaaa` et les
rǸfǸrences restent du texte (les zǸros de tǦte sont conservǸs).

L'export respecte les filtres affichǸs et votre pǸrim��tre de droits : un
utilisateur limitǸ �� ses propres projets ou contrats n'exporte que ceux-ci.
Les listes sont plafonnǸes �� 10 000 lignes par export ; affinez les filtres
pour les grands volumes.

### 9.2. Rendu de l'impression

L'impression masque la navigation, les filtres et les boutons d'action. Les
tableaux conservent leur en-tǦte sur chaque page, les lignes ne sont pas
coupǸes et la mise en page est calibrǸe pour du A4. Pour les exports Excel, la
mise en page du classeur est Ǹgalement prǸparǸe (A4, paysage au-del�� de six
colonnes, ajustement �� la largeur, en-tǦte rǸpǸtǸ).

## 10. Bonnes pratiques

- Vérifier le client et le type avant de créer un projet.
- Ne pas utiliser le champ « commentaires » pour des données sensibles : le
  journal d'audit et les commentaires sont visibles selon les permissions.
- Clôturer un projet dès la fin des prestations.
- Vérifier les échéances contractuelles dans le calendrier avant la fin du
  préavis de renouvellement.
- Ne jamais valider une facture ou une charge sans contrôle des pièces
  justificatives et du montant TTC.
- Clôturer l'exercice après validation des opérations de l'année.
- Signaler immédiatement toute anomalie de montant ou d'accès.

## 11. Montants et arrondis

- Les montants sont stockés en décimal exact (échelle 3, soit le millime).
- Le taux de TVA est saisi en pourcentage (ex. `19`).
- Les arrondis sont effectués demi vers le haut, de façon déterministe.
- Aucun calcul ne passe par un nombre flottant binaire.

En cas d'écart apparent, vérifier la saisie du taux et des montants HT avant de
contacter le support.

## 12. Problèmes fréquents

| Symptôme | Cause probable | Action |
|---|---|---|
| « Identifiants invalides » | Mot de passe ou identifiant erroné | Vérifier la saisie ; contacter l'administrateur |
| Page « Accès refusé » | Permission manquante | Demander l'ajustement du rôle |
| Bouton absent | Permission ou statut incompatible | Vérifier la matrice des rôles |
| « Jeton CSRF invalide » | Page restée ouverte trop longtemps | Recharger la page et recommencer |
| Exercice non modifiable | Exercice clôturé | Demander une réouverture motivée |
| Projet non visible | Hors périmètre `project.view_own` | Demander à être ajouté comme membre |
| Bouton « Facturer » absent | Échéance non prête ou non facturable | Vérifier les statuts de réalisation et de facturation |
| Paiement de charge refusé | Charge non approuvée ou auteur = approbateur | Faire approuver par un autre utilisateur |
| Règlement d'associé refusé | Opération non validée | Valider l'opération avant le règlement |
| Aucune alerte générée | Dates ou responsable absents, ou délai non atteint | Compléter la fiche et vérifier le paramétrage |

## 13. Support

Fournir au support : l'identifiant utilisé, l'écran concerné, l'heure, le
message exact et, si possible, la référence du projet ou du client. Les
opérations sensibles sont tracées dans le journal d'audit, ce qui facilite le
diagnostic.
