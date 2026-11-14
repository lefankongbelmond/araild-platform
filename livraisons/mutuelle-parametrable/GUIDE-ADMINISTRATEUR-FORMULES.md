# Guide de l'administrateur : formules, garanties, plafonds, carences, dérogations

MX-CONNECT, version 2026.11.18. Ce guide s'adresse à l'administrateur de la mutuelle, et au contrôleur pour les dérogations.

## 1. Où trouver le catalogue

Dans le menu **Paramètres** :

- **Catalogue des formules** ;
- **Dérogations**.

Sur la fiche d'un adhérent, le lien **Dérogations** ouvre les dérogations de cet adhérent.

## 2. Préparer les catégories de soins (une seule fois)

1. Ouvrez **Catalogue des formules → Catégories de soins**.
2. Chaque acte doit être rattaché à une catégorie (Consultation médicale, Pharmacie, Hospitalisation…). MX-CONNECT propose un rattachement pour les actes de la liste de référence. Ces propositions apparaissent avec la mention « proposé ».
3. Vérifiez la liste, corrigez si besoin, puis cliquez sur **Enregistrer le rattachement**. Rien n'est enregistré avant ce clic.
4. Vous pouvez ajouter vos propres catégories. Une catégorie ne porte aucun taux.

Un acte non rattaché n'est couvert que par une garantie « Toutes les prestations ».

## 3. Créer une formule

1. Cliquez sur **Nouvelle formule** : nom, cotisation, périodicité (mensuelle, trimestrielle, semestrielle, annuelle, personnalisée), date d'effet. Le taux unique demandé à cette étape ne sert qu'aux formules **sans** garanties détaillées.
2. Ouvrez la formule dans le catalogue et remplissez la **fiche** : statut, catégorie, population cible, âges, nombre maximal de bénéficiaires, période de vente.

   Seules les formules au statut **Active** sont proposées aux nouvelles souscriptions. Dans la mutuelle de test, les formules **En test** le sont aussi.

## 4. Ajouter les garanties

**+ Ajouter une garantie** :

- **Nom** : par exemple « Consultations ».
- **Catégorie de soins** : une catégorie, ou « Toutes les prestations ».
- **Bénéficiaires concernés** : tous, adulte, enfant de 0 à 5 ans, enfant de 6 à 17 ans, élève, senior, nouveau conjoint.

  Vous pouvez prévoir un taux différent pour les enfants : créez une 2e garantie « Consultations » avec « Enfant de 6 à 17 ans ». Le logiciel retient automatiquement la plus précise.
- **Taux de prise en charge** : obligatoire. Aucun taux n'est appliqué par défaut.
- **Ticket modérateur** : à titre indicatif.
- **Carence en cas d'accident** : laissez vide pour appliquer la carence habituelle.
- **Urgence sans carence** : cochée par défaut.
- **Base de calcul limitée au tarif conventionnel** : seulement si l'acte a un tarif.

Dès qu'une formule a une garantie détaillée, MX-CONNECT applique le calcul détaillé à cette version.

## 5. Ajouter les plafonds

**+ Ajouter un plafond** :

- **Montant maximal** (part de la mutuelle), **nombre maximal d'actes**, ou les deux.
- **Période** : par acte, par jour, par mois, par année civile.
- **Pour** : chaque bénéficiaire, ou toute la famille.
- **Garanties concernées** :
  - aucune cochée : toutes les garanties ;
  - une seule : plafond de cette garantie ;
  - plusieurs : **plafond partagé**.

Les prestations saisies, contrôlées et validées consomment le plafond. Une prestation rejetée le libère. Au-delà du plafond, le surplus est indiqué « non couvert » et reste à la charge du patient. Le logiciel refuse la prise en charge quand le plafond est épuisé, sauf dérogation accordée.

## 6. Ajouter les délais de carence

**+ Ajouter ou modifier un délai** : garantie (ou toutes), catégorie de bénéficiaire, nombre de jours.

Le délai court :

- depuis la date d'effet de la souscription ;
- pour un ayant droit, depuis son arrivée dans l'adhésion ;
- avec le **renouvellement continu** (dans « Paramètres de la version »), depuis la première adhésion. Un adhérent déjà couvert ne refait donc pas de carence.

En **urgence**, la carence ne s'applique pas aux garanties qui l'exemptent.

## 7. Vérifier avec le simulateur

En bas de la fiche, le **Simulateur** calcule une prise en charge sans adhérent et sans rien enregistrer. Indiquez :

- l'acte et le montant ;
- l'âge du bénéficiaire ;
- l'ancienneté de la couverture ;
- éventuellement ce qui a déjà été consommé ;
- élève, nouveau conjoint, urgence ou accident.

Le résultat donne :

- le statut (DROITS ACTIFS, DÉLAI DE CARENCE EN COURS, PLAFOND ÉPUISÉ…) ;
- la garantie appliquée ;
- les montants : éligible, part de la mutuelle, ticket modérateur, non couvert, part du patient ;
- les plafonds restants.

## 8. Modifier une formule déjà utilisée

Si la version a déjà servi à une prestation, chaque formulaire demande une **date d'effet**, et éventuellement une note.

La modification ouvre une **nouvelle version** à cette date. Les soins déjà réalisés gardent leur calcul. Les plafonds de l'année continuent de se consommer, car les garanties gardent leur identité.

## 9. Dérogations (contrôle « 4 yeux »)

1. **Demander** (gestionnaire des prestations ou administrateur) : n° d'adhérent, bénéficiaire, type, catégorie ou acte, dates, motif et justification.

   Les trois types :
   - dispense de carence ;
   - plafond supplémentaire, avec un montant ;
   - acte exclu accordé.

   N'indiquez aucun diagnostic.
2. **Décider** (contrôleur ou administrateur) : **Accorder**, ou **Refuser** avec un motif. La personne qui a demandé ne peut pas décider.
3. **Retirer** une dérogation accordée, avec un motif : elle cesse de s'appliquer aux soins suivants.

Une dérogation ne modifie jamais la formule. La prestation qui l'utilise en garde la trace.

## 10. Mutuelle de test

Le super-administrateur peut marquer une mutuelle comme **MUTUELLE TEST**. Le bandeau « Environnement de test » s'affiche alors partout.

Dans cette mutuelle seulement, le bouton **Charger les formules d'exemple** crée ESSENTIEL, FAMILLE, SCOLAIRE, CONFORT, SENIOR et PERSONNALISÉE, marquées « Exemple ». Seules les valeurs données en exemple sont reprises :

- FAMILLE est complète (taux, carences, plafond pharmacie) ;
- les autres formules sont en brouillon, à compléter ;
- aucune cotisation n'est fixée.
