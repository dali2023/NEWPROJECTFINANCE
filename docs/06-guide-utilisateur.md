# Guide d'utilisation

**Application :** ONE ERP Finance & Project Control  
**Version :** 1.0 — phases 1 et 2  
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
- **Finance — phases 3 à 5** : facturation, charges, trésorerie, associés.
  Ces entrées sont affichées en « à venir » et ne sont pas cliquables.
- **Administration** : exercices, types de projet, utilisateurs, rôles, audit,
  selon vos permissions.

Le bandeau supérieur affiche votre nom et le bouton **Déconnexion**. Les
montants sont exprimés en dinars tunisiens (TND).

## 3. Tableau de bord

Le tableau de bord présente :

- le montant des contrats signés (hors opportunités et projets perdus) ;
- le nombre de projets suivis ;
- la répartition des projets par statut ;
- les projets récents auxquels vous avez accès ;
- les prochaines échéances et les tiers actifs.

Les indicateurs de facturation, d'encaissement, de charges et de trésorerie
seront activés avec les phases suivantes. Ils ne sont pas affichés à zéro pour
éviter toute interprétation erronée.

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
- **Budget et rentabilité** : budget prévisionnel, consommation et indicateurs
  de marge (activés avec les modules financiers).
- **Facturation**, **Charges** et **Documents** : emplacements réservés aux
  phases 3 et 4, affichés explicitement comme « à venir ».
- **Historique** : opérations journalisées sur le projet.

Les actions **Modifier** et **Clôturer** dépendent de vos permissions et du
statut du projet. Un projet clôturé n'est plus modifiable.

## 7. Administrer

### 7.1. Exercices financiers

Menu **Administration → Exercices**.

- **Créer** un exercice avec un libellé et une période. Deux exercices ne
  peuvent pas se chevaucher.
- **Clôturer** un exercice : il passe en lecture seule.
- **Réouvrir** un exercice clôturé : permission dédiée et motif d'au moins
  10 caractères obligatoires. L'opération est journalisée.

### 7.2. Types de projet

Créer, renommer, ordonner ou désactiver les types proposés dans les fiches
projet. Un type utilisé par un projet existant ne doit pas être supprimé.

### 7.3. Utilisateurs

Menu **Administration → Utilisateurs**.

- Créer un compte : nom complet, identifiant, courriel, rôles, mot de passe.
- Modifier les informations et les rôles.
- Désactiver un compte : l'utilisateur ne peut plus se connecter, mais son
  historique est conservé.

Le mot de passe doit contenir au moins 8 caractères. Il n'est jamais affiché.

### 7.4. Rôles et permissions

Menu **Administration → Rôles**. La matrice présente les permissions par module.
Cocher ou décocher puis **Enregistrer les permissions**. Toute modification est
journalisée avec la liste avant/après.

L'administrateur technique dispose d'un accès global, mais les règles de
séparation des tâches restent appliquées : il ne peut pas, par défaut, valider
les opérations financières à la place des responsables.

### 7.5. Journal d'audit

Menu **Administration → Audit**. Filtrez par utilisateur, action, entité,
module, résultat ou période. Chaque ligne indique l'auteur, l'action,
l'horodatage, l'adresse IP et, le cas échéant, les valeurs avant/après.

Les refus d'accès apparaissent avec l'action `deny` et le résultat `denied`.

## 8. Bonnes pratiques

- Vérifier le client et le type avant de créer un projet.
- Ne pas utiliser le champ « commentaires » pour des données sensibles : le
  journal d'audit et les commentaires sont visibles selon les permissions.
- Clôturer un projet dès la fin des prestations.
- Clôturer l'exercice après validation des opérations de l'année.
- Signaler immédiatement toute anomalie de montant ou d'accès.

## 9. Montants et arrondis

- Les montants sont stockés en décimal exact (échelle 3, soit le millime).
- Le taux de TVA est saisi en pourcentage (ex. `19`).
- Les arrondis sont effectués demi vers le haut, de façon déterministe.
- Aucun calcul ne passe par un nombre flottant binaire.

En cas d'écart apparent, vérifier la saisie du taux et des montants HT avant de
contacter le support.

## 10. Problèmes fréquents

| Symptôme | Cause probable | Action |
|---|---|---|
| « Identifiants invalides » | Mot de passe ou identifiant erroné | Vérifier la saisie ; contacter l'administrateur |
| Page « Accès refusé » | Permission manquante | Demander l'ajustement du rôle |
| Bouton absent | Permission ou statut incompatible | Vérifier la matrice des rôles |
| « Jeton CSRF invalide » | Page restée ouverte trop longtemps | Recharger la page et recommencer |
| Exercice non modifiable | Exercice clôturé | Demander une réouverture motivée |
| Projet non visible | Hors périmètre `project.view_own` | Demander à être ajouté comme membre |

## 11. Support

Fournir au support : l'identifiant utilisé, l'écran concerné, l'heure, le
message exact et, si possible, la référence du projet ou du client. Les
opérations sensibles sont tracées dans le journal d'audit, ce qui facilite le
diagnostic.
