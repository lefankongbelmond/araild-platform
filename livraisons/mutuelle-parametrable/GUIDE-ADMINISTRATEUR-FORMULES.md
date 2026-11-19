# Guide de l'administrateur : formules, garanties, plafonds, carences, dérogations

MX-CONNECT, version 2026.11.24. Ce guide s'adresse à l'administrateur de la mutuelle, et au contrôleur pour les dérogations.

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

## 11. Accords préalables (depuis le 19/11/2026)

1. **Paramétrer.** Dans une garantie (Catalogue → formule → Modifier), cochez « Exiger un accord préalable ». Choisissez :
   - l'examen : administratif, ou médical (médecin-conseil) ;
   - éventuellement un montant : l'accord n'est exigé qu'au-delà.
2. **Recevoir les demandes.** **Gestion des adhérents → Accords préalables**. Pour chaque demande s'affichent :
   - les contrôles automatiques : droits, garantie, carence, plafonds ;
   - la justification médicale, seulement pour les personnes habilitées.
3. **Décider** : une autre personne que celle qui a saisi la demande. Pour un examen médical, seul le médecin-conseil décide.
   - **Accorder** : un numéro AUT-… est attribué. Vous pouvez limiter la part de la mutuelle et fixer une date de validité.
   - **Rejeter** : le motif est obligatoire et communiqué au prestataire.
4. **Utilisation.** Le prestataire voit la décision dans son portail. L'accord sert une seule fois, pour son établissement.
5. **Urgences.** Un prestataire peut soigner en urgence sans accord : les soins ne sont jamais bloqués. Une demande « Urgence » arrive alors dans la liste. La prise en charge ne peut être validée qu'après cet examen.

Pour ajouter un **médecin-conseil** : Paramètres → Équipe et rôles → rôle « Médecin-conseil ».

## 12. Pharmacie

- **Politique pharmaceutique** (Gestion des adhérents → Pharmacie → Politique pharmaceutique) :
  - validité d'une ordonnance ;
  - délai de surveillance des délivrances rapprochées ;
  - validité d'un accord préalable.

  Un réglage vide n'est pas appliqué.
- **Médicaments pris en charge** :
  - « Liste » : médicament de la liste positive ;
  - « Exclu » : jamais pris en charge ;
  - « Générique » ;
  - quantité maximale par ordonnance.

  Le surplus et les médicaments hors liste restent à la charge du patient. La prescription n'est jamais modifiée.
- **Plafonds de pharmacie** : dans chaque formule, avec les plafonds. « Par acte » correspond à une ordonnance délivrée.
- **Registre des délivrances** : ordonnance, médicaments, lignes non prises en charge, état de la prise en charge.

Une même ordonnance ne peut jamais être facturée deux fois, même dans une autre pharmacie. Les prix au-dessus du prix de référence et les délivrances rapprochées créent des alertes à examiner.

## 13. Tableau de bord de pilotage (depuis le 21/11/2026)

Menu **Pilotage → Tableau de bord**. Accès : administrateur, contrôleur, gestionnaire des prestations, trésorier, auditeur.

1. **Choisissez la période** (ce mois-ci, le mois dernier, ce trimestre, cette année, l'année dernière ou des dates au choix), puis les filtres utiles : formule, établissement, antenne, type de soins, statut de traitement. Cliquez sur **Afficher**.
2. **Lisez les cartes** :
   - adhérents : actifs, suspendus, expirés ;
   - cotisations attendues et taux de recouvrement ;
   - prise en charge et tickets modérateurs ;
   - taux de sinistralité.
3. **Graphique mois par mois** : cotisations encaissées et part de la mutuelle. Survolez ou touchez un mois pour voir ses montants.
4. **Consommation** : par formule, par établissement et, pour les personnes habilitées aux données de santé, par garantie et en pharmacie.
5. **Plafonds consommés** : nombre de familles ou de bénéficiaires ayant atteint 80 % ou la totalité d'un plafond annuel.
6. **Exporter** : **Exporter (Excel)** télécharge un classeur avec les filtres appliqués, une feuille par tableau. **Imprimer ou PDF** imprime la page ou l'enregistre en PDF.

Bon à savoir :

- Tous les chiffres sont réels et calculés à l'instant. Dans la MUTUELLE TEST, ce sont les données d'essai.
- Le taux de sinistralité compare la part payée par la mutuelle aux cotisations encaissées sur la même période. Au-delà de 100 %, la mutuelle paie plus qu'elle n'encaisse.
- Le filtre par adhérent n'est proposé qu'aux personnes habilitées aux données de santé.

## 14. Réinitialiser la MUTUELLE TEST (depuis le 22/11/2026)

Seule une mutuelle marquée « mutuelle de test » par l'administration du réseau propose cette fonction. Dans une vraie mutuelle, elle n'existe pas.

1. Dans le bandeau jaune **Environnement de test**, cliquez sur **Réinitialiser les données de test**. Ce lien est réservé à l'administrateur.
2. La page indique **ce qui sera effacé**, avec le nombre de lignes : adhérents, cotisations, soins, pharmacie, trésorerie, comptabilité, réclamations, etc.

   Elle indique aussi **ce qui est conservé** : l'équipe, les rôles, le plan comptable, les listes de référence, les prestataires conventionnés, les réglages et les journaux d'audit.
3. Si vous voulez repartir de formules neuves, cochez **Effacer aussi les formules et garanties**. Laissez **recharger les formules d'exemple** coché pour retrouver les formules de la mission.
4. Indiquez le **motif**, puis recopiez l'**identifiant** de la mutuelle affiché (par exemple `mutueltest`).
5. Cliquez sur **Réinitialiser les données de test**, puis confirmez.

Une **sauvegarde complète** de la mutuelle est faite juste avant. Si elle échoue, rien n'est effacé.

L'opération est inscrite dans le **Journal d'audit** et dans l'historique de la mutuelle, visible par l'administration du réseau.

Les comptes MX-CONNECT reliés à des adhérents d'essai sont déliés. Il faudra générer de nouveaux codes d'activation.

L'administration du réseau peut faire la même opération depuis la fiche de la mutuelle.

## 15. Rôle « gestionnaire des cotisations » et traces des accès aux données de santé

- **Gestionnaire des cotisations** (Équipe et rôles) : pour la personne du guichet. Elle peut :
  - consulter les adhérents et les souscriptions ;
  - encaisser les cotisations et le droit d'adhésion ;
  - tenir les échéanciers et les relevés ;
  - demander l'annulation d'un encaissement.

  Elle ne voit **ni la trésorerie, ni la comptabilité, ni les données de santé**. Ce rôle ne se cumule pas avec celui de contrôleur-validateur.
- **Journal d'audit → Accès aux données de santé** : chaque ouverture d'un recours, d'un accord préalable, d'une pièce de prestataire ou du registre de la pharmacie, par une personne habilitée, est inscrite (qui, quoi, quand, adresse IP). Le contenu médical n'y est jamais recopié.

## 16. Faire changer de formule un groupe d'adhérents (depuis le 23/11/2026)

Exemple : la mutuelle ferme la formule ESSENTIEL et fait passer ses adhérents à FAMILLE au 1er janvier.

1. **Paramètres → Changements de formule → Nouveau changement** (administrateur).
2. Choisissez la **formule actuelle**, la **formule cible** et la **date d'effet** (aujourd'hui ou plus tard), puis **Voir les adhérents**.
3. Vérifiez la liste :
   - pour chaque adhérent : sa cotisation actuelle, sa nouvelle cotisation et le nombre d'échéances de l'ancienne formule qui seront retirées ;
   - les adhérents **écartés** apparaissent en jaune avec le motif. Exemple : une échéance future déjà payée ; annulez d'abord cet encaissement au guichet.
4. Décochez au besoin, laissez **Conserver l'ancienneté** coché (pas de nouvelle carence), indiquez le **motif**, puis **Envoyez la demande**.
5. **Une autre personne valide**, l'administrateur ou le contrôleur, jamais vous-même. Une fois validé, le changement s'applique **à la date d'effet**, la nuit. Si la date est atteinte, il s'applique tout de suite, ou par **Appliquer maintenant**.

Bon à savoir :

- **Rien ne change dans le passé** : les soins faits avant la date d'effet restent pris en charge selon l'ancienne formule.
- **L'ancienne souscription n'est pas effacée** : elle porte une date de fin.
- **La nouvelle cotisation** est la cotisation de base de la formule cible multipliée par le nombre de bénéficiaires.
- **Formule cible sans cotisation fixée** (0, comme les formules d'exemple) : le changement est refusé. Fixez d'abord la cotisation dans le catalogue.
- **Pas de prorata** : choisissez de préférence une date d'effet qui tombe le jour d'une échéance.
- **Les adhérents concernés** sont prévenus par une notification.

## 17. Délai de grâce avant suspension (depuis le 24/11/2026)

Par défaut, une échéance impayée suspend les droits aux soins **dès le lendemain** de sa date. Votre règlement intérieur peut laisser quelques jours à l'adhérent.

1. **Cotisations → Règle de suspension** (administrateur).
2. Saisissez le **délai de grâce** en jours (0 à 180), puis **Enregistrer**. Laissez vide ou mettez 0 pour revenir à la règle d'origine.

Pendant le délai :

- l'échéance reste **en retard** : elle figure dans les arriérés, les exports de relance et les rapports ;
- seuls les droits aux soins restent ouverts ;
- le prestataire voit « droits maintenus jusqu'au … » et l'adhérent « vos droits restent ouverts jusqu'au … ».

À la fin du délai, les droits sont suspendus jusqu'au règlement.
