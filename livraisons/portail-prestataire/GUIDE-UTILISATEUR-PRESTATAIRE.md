# Guide du portail prestataire (version 2026.11.16)

Pour l'accueil, le personnel soignant, la facturation et la direction des établissements de soins (hôpitaux, centres de santé, cliniques, pharmacies, laboratoires) conventionnés avec des mutuelles MX-CONNECT.

Depuis le 16/11/2026, chaque établissement dispose d'un **espace prestataire** sur `https://m.araild.com/espace-prestataire`. Chaque personne y a **un seul compte**, valable pour toutes les mutuelles de l'établissement. Elle y a un rôle dans l'établissement : administrateur, médecin, infirmier, sage-femme, pharmacien, technicien de laboratoire, caissier, agent de facturation ou gestionnaire administratif.

## 1. Accéder à l'espace prestataire

### 1.1 Inscrire son établissement (première fois)
1. Ouvrez `https://m.araild.com/se-connecter`, carte **Établissement de soins**, puis **Inscrire mon établissement**.
2. Remplissez l'établissement (nom, type, n° d'agrément, pays, localité, contacts) et vos informations de responsable (e-mail, mot de passe de 10 caractères au moins, avec lettres et chiffres).
3. Cochez les mutuelles avec lesquelles vous souhaitez travailler (facultatif, possible plus tard), puis attestez que vous représentez l'établissement.
4. Activez la **double authentification** : scannez le QR code avec Google Authenticator ou Microsoft Authenticator, puis saisissez le code à 6 chiffres.

Votre établissement est **reconnu dès qu'une mutuelle accepte** votre demande d'affiliation. La mutuelle vérifie d'abord votre agrément. En cas de refus, le motif s'affiche dans **Conventions et tarifs**.

### 1.2 Établissement déjà conventionné par une mutuelle
La mutuelle peut ouvrir l'accès du responsable de l'établissement. Elle vous remet l'adresse, votre identifiant et un mot de passe provisoire. À la première connexion :
1. choisissez votre mot de passe ;
2. activez la double authentification.

### 1.3 Se connecter ensuite
E-mail et mot de passe, puis le code à 6 chiffres. Sur la page de connexion :
- **Mot de passe oublié ?** envoie un lien valable 60 minutes, si l'envoi d'e-mails est configuré sur le site ;
- sinon, l'administrateur de votre établissement réinitialise votre mot de passe.

Les anciens comptes « Prestataire de soins » créés par une mutuelle sur son adresse (`https://<mutuelle>.m.araild.com`) fonctionnent toujours.

## 2. Le tableau de bord
Il rassemble les chiffres **réels** de toutes vos mutuelles, actualisés toutes les 2 minutes :
- patients identifiés aujourd'hui et ce mois (droits vérifiés) ;
- prises en charge en attente, validées, rejetées ;
- consultations ;
- factures transmises, acceptées, rejetées ;
- délai moyen de paiement.

**Montants**, visibles selon votre rôle :
- à facturer, facturé, accepté, payé, restant dû, rejeté ;
- **une ligne par devise**, sans conversion : le XAF et le XOF ne sont jamais additionnés.

Une facture acceptée n'est pas une facture payée : « Payé » ne compte que les paiements enregistrés par la mutuelle.

**Graphiques** : évolution sur 12 mois, paiements reçus par mois, répartition par mutuelle, actes les plus fréquents.

**Alertes** :
- agrément ou document qui expire ;
- convention arrivant à échéance, échue ou suspendue ;
- factures non payées au-delà du délai de paiement fixé par la convention (seulement si la convention en fixe un) ;
- rejets récents ;
- paiements reçus ;
- nouvelle convention, ou demande d'affiliation refusée.

## 3. Ouvrir le portail d'une mutuelle
Menus **Vérifier les droits**, **Prises en charge** et **Mes factures** : choisissez la mutuelle de l'adhérent, puis **Ouvrir**.

Le portail de la mutuelle s'ouvre directement à votre nom, sans nouveau mot de passe ni nouveau code. Le lien d'entrée ne sert qu'une fois et expire au bout de 60 secondes. Les données des adhérents restent chez la mutuelle.

Pour revenir, utilisez **← Retour à l'espace prestataire**.

Ce que vous pouvez faire dans le portail dépend de votre rôle. Par exemple :
- le caissier vérifie les droits mais ne déclare pas de soins ;
- l'agent de facturation demande les paiements ;
- le médecin ne voit pas les factures.

La mutuelle peut aussi couper l'accès d'une personne à son portail. Dans ce cas, un message vous l'indique à l'ouverture.

## 4. Vérifier les droits d'un patient
Menu **Vérifier les droits**. Trois façons d'identifier l'adhérent :
- saisir le **code adhérent** imprimé sur la carte (ex. `MUT-000123`) ;
- **Scanner avec la caméra** le QR code de la carte (téléphone ou tablette) ;
- **Photo de la carte** : prenez le QR en photo, la lecture se fait dans le navigateur.

Résultat :
- **Droits ouverts** : vous pouvez déclarer une prise en charge pendant **12 heures**.
- **Droits non ouverts**, avec le motif : adhérent non actif, cotisation en retard, période d'observation, aucune garantie active, acte non couvert ou établissement hors réseau. Orientez alors le patient vers la mutuelle.

Seul le verdict s'affiche : aucune donnée médicale. Chaque vérification est enregistrée à votre nom.

## 5. Déclarer une prise en charge
Après une vérification positive, cliquez sur **Déclarer une prise en charge**, puis renseignez :
- la personne soignée (l'adhérent ou une personne à sa charge) ;
- l'acte ;
- la date des soins (aujourd'hui ou dans les 30 derniers jours) ;
- le montant total.

N'indiquez aucun diagnostic. La part de la mutuelle et la part du patient sont calculées selon la garantie de l'adhérent et affichées après l'enregistrement.

**Déclaration peut-être en double.** Si votre établissement a déjà déclaré le même acte, pour la même personne et à la même date, un avertissement s'affiche et rien n'est enregistré. S'il s'agit vraiment d'un second acte, cochez la case de confirmation puis cliquez sur **Déclarer quand même**. La mutuelle en est informée pour contrôle.

## 6. Suivre ses prises en charge
Menu **Prises en charge** (dans le portail : **Mes prises en charge**). Filtres : en attente de validation, validées, rejetées.
- Une prise en charge **rejetée** affiche le **motif** donné par la mutuelle.
- Les rejets antérieurs au 15/11/2026 n'ont pas de motif enregistré : contactez la mutuelle.

## 7. Demander le paiement
Menu **Mes factures**.
1. Les prises en charge **validées** apparaissent dans « à facturer ». Le montant correspond à la part de la mutuelle.
2. Indiquez votre n° de facture et joignez si possible la facture en PDF (5 Mo au maximum), puis cliquez sur **Demander le paiement**.
3. Suivez l'état de la facture : transmise, en traitement, mise en paiement, payée (avec sa date) ou rejetée (avec le motif).

Une seule demande est possible par prise en charge.

La mutuelle paie **hors de la plateforme** : MX-CONNECT ne détient aucun fonds. Une facture n'est « payée » que lorsque la mutuelle a enregistré le paiement.

## 8. Convention échue ou suspendue
- **Convention arrivée à échéance** : vous ne pouvez plus vérifier de droits ni déclarer de soins. Vous pouvez encore demander le paiement des soins déjà validés.
- **Convention suspendue par la mutuelle**, ou **établissement désactivé** : seule la consultation de votre historique reste possible.

## 9. Gérer l'établissement (administrateur, gestionnaire administratif)
- **Profil de l'établissement** : nom, type, agrément et date de validité, spécialités, adresse, coordonnées GPS (lien OpenStreetMap), téléphones, e-mail et horaires. Ces informations sont visibles par vos mutuelles.
- **Coordonnées bancaires** (administrateur seulement) : banque, titulaire, RIB, SWIFT, Mobile Money. Elles sont stockées chiffrées. Les mutuelles paient hors de la plateforme.
- **Agréments et documents** : PDF de 5 Mo au plus (agrément, convention signée, assurance, registre de commerce, attestation fiscale, RIB…), avec une date d'expiration et la mutuelle concernée. Un document retiré reste dans l'historique.
- **Conventions et tarifs** :
  - pour chaque mutuelle : situation de la convention, n°, dates, délais de transmission et de paiement, conditions de facturation, garanties qui acceptent l'établissement, et catalogue des actes avec le tarif conventionnel (« Non défini » si la mutuelle n'en a pas fixé) ;
  - demander une affiliation à une autre mutuelle, ou retirer une demande.

## 10. Personnel et droits (administrateur)
**Personnel et utilisateurs** → **Ajouter une personne** : nom, e-mail (identifiant), rôle et service.
- Un mot de passe provisoire s'affiche **une seule fois** : transmettez-le à la personne.
- Si la personne a déjà un compte prestataire (dans un autre établissement), elle est simplement rattachée et garde son mot de passe.

**Rôle et droits** : chaque rôle a des droits par défaut, rappelés dans le tableau en bas de page. Vous pouvez cocher ou décocher des droits pour chaque personne.
- « Gérer le personnel » reste réservé au rôle Administrateur.
- L'établissement garde toujours au moins un administrateur actif.

**Désactiver** une personne coupe immédiatement son accès, y compris dans les portails des mutuelles déjà ouverts.

**Réinitialiser** remet un mot de passe provisoire et oblige à refaire la double authentification (téléphone perdu, par exemple). Ce n'est pas possible pour un compte aussi utilisé dans un autre établissement : la personne utilise alors « Mot de passe oublié ».

## 11. Bonnes pratiques
- Ne partagez jamais votre compte : chaque action est enregistrée à votre nom, dans l'espace prestataire comme dans chaque mutuelle.
- Déconnectez-vous sur un poste partagé.
- Sans connexion Internet, la page « Pas de connexion » s'affiche. Aucune vérification de droits n'est possible hors ligne. Un acte urgent ne doit jamais être retardé pour cette raison : soignez d'abord, puis déclarez dès le retour de la connexion ou contactez la mutuelle.
