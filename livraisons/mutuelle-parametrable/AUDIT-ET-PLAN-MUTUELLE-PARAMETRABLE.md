# MX-CONNECT — Mutuelle paramétrable et portail adhérent : audit, diagnostic et plan

Version auditée : **2026.11.17** (17/11/2026). Ce document répond à la Partie 1 de la mission « Évolution et professionnalisation de MX-CONNECT ». Aucune ligne de code n'a été modifiée pour cet audit.

---

## 1. Architecture actuelle

| Élément | Constat |
|---|---|
| Langage, framework | PHP 8.4, **Laravel 13.25**, Blade côté serveur, CSS compilé (`mx-tenant.css`, `mx-social.css`), JavaScript léger sans framework |
| Base de données | MariaDB 11.8. Une base **centrale** (comptes, mutuelles, réseau social, espace prestataire) et une base **par mutuelle** (ou des tables préfixées `mutNN_` dans la base centrale sur LWS) via stancl/tenancy 3.10 |
| Multi-mutuelle | Déjà en place et éprouvé : sous-domaine `<slug>.m.araild.com`, isolation physique des données, suspension réelle, création automatique |
| Authentification | 5 gardes. `web` (personnel de la mutuelle, MFA), `network` (super-admin, MFA), `member` (comptes du réseau social), `provider` (espace prestataire, MFA), `sanctum` (ancienne API membre) |
| Rôles | Mutuelle : 8 rôles, matrice unique `TenantAccess` appliquée à chaque route, contrôle « 4 yeux ». Établissements de soins : 9 rôles, `ProviderAccess` |
| Hébergement | Mutualisé LWS : pas de SSH, cron par URL, fichiers statiques servis seulement par le domaine principal |
| Tests | 125 tests Pest (dont plus de 60 dans une vraie base de mutuelle, aussi en tables préfixées), contrôles en navigateur réel à 4 largeurs |

## 2. Ce qui existe déjà (à réutiliser, pas à refaire)

| Domaine | Existant | Où |
|---|---|---|
| Adhérents | Fiche, statut (actif / suspendu / sorti / radié), import, détection des doublons, suspension et réactivation | `MemberController`, table `members` |
| Ayants droit | Nom, sexe, date de naissance, lien (conjoint, enfant, ascendant, autre), statut (actif / retiré) | table `dependents` |
| Adhésion en ligne | Demande depuis le réseau social, pièces privées, instruction par l'agent. À l'acceptation, **lien central `member_links`** entre le compte du réseau social et l'adhérent | `OnlineAdhesionController`, `MemberLink` |
| « Formules » | Aujourd'hui appelées **Garanties** : versions datées et **immuables une fois utilisées** (taux de couverture, ticket modérateur, cotisation, périodicité mensuelle/trimestrielle/annuelle, droit d'adhésion, période d'observation, exclusions, réseau de soins, plafonds par an / par acte / par séjour) | `Guarantee`, `GuaranteeVersion`, `Ceiling`, `GuaranteeVersioningService` |
| Souscriptions | Saisie puis validation « 4 yeux », échéanciers glissants | `Subscription`, `ScheduleGenerationService` |
| Cotisations | Encaissement au guichet, reçu, annulation « 4 yeux », droit d'adhésion, relevé par adhérent | Lots 13–14 |
| Actes et médicaments | Catalogue des actes (code, libellé, catégorie libre, tarif conventionnel), médicaments multi-devises (catégorie, présentation, prix de référence) | `ActType`, `Medicine` |
| Prestations | Recours, prestations, calcul part mutuelle / part adhérent plafonné, validation « 4 yeux », rejet motivé, remboursements et factures des prestataires, paiement enregistré hors plateforme | `ClaimCaptureService`, `ClaimAssessmentService`, `PrestationController` |
| Contrôle | 6 contrôles de droits, 5 signaux anti-fraude non bloquants (doublon, date, plafond, surconsommation, montant supérieur au tarif) | `RightsVerificationService`, `FraudDetectionService` |
| Portail prestataire | Espace central multi-mutuelles, vérification des droits, recherche encadrée, estimation, déclaration, factures | Portail V2, phases 1 à 3 |
| Cartes | Carte format CB, QR signé (vérification sans donnée médicale), planche A4 | `DigitalCardService`, `MemberCardController` |
| Notifications aux membres | Application et e-mail, lien WhatsApp, export CSV pour SMS | `MemberNotificationController` |
| Réclamations | Registre côté mutuelle, avec délai de traitement (canal téléphone, sur place, écrit) | `ComplaintController` |
| Pilotage | Rapports d'activité, évolution mensuelle, états SYSCOHADA, budget, trésorerie, solvabilité | Lots 10–12 |
| Espace membre | **Ancienne PWA** sur le sous-domaine (`/espace-membre`, connexion par téléphone et jeton Sanctum) : fiche, carte, échéances, recours, paiement | `MemberApiController`, vue `tenant/pwa/app` |
| Application Android | TWA sur **m.araild.com** (réseau social), parcours `/app` | Lot Android |

## 3. Diagnostic : écarts et anomalies

| # | Constat | Gravité | Traitement proposé |
|---|---|---|---|
| D1 | Une « formule » n'a **qu'un seul taux de couverture** pour tous les soins. Impossible d'avoir 80 % en consultation et 70 % en pharmacie. | **Structurant** | Ajouter des **garanties (lignes de couverture)** par catégorie de soins dans chaque version de formule |
| D2 | Plafonds limités à par an / par acte / par séjour, par adhérent. Pas de plafond jour ou mois, ni en nombre d'actes, ni par bénéficiaire ou par famille, ni partagé entre garanties. Le plafond « par séjour » est traité comme « par acte ». | Haute | Moteur de plafonds généralisé, avec un **registre des consommations** |
| D3 | Délai de carence unique (`observation_days`) par formule, sans distinction par garantie, catégorie de bénéficiaire, accident, urgence ou renouvellement. | Haute | Moteur de **délais de carence** paramétrable |
| D4 | Le plafond annuel est contrôlé par une simple somme au moment de la saisie : deux saisies simultanées peuvent consommer le même reste (scénario 12). | Haute | Consommation sous **verrou de ligne** (transaction) dans le registre des consommations |
| D5 | Pas de préautorisation, pas de dérogation tracée, pas de procédure d'urgence. | Haute | Workflows de **préautorisation** (avec urgence jamais bloquée) et de **dérogation** « 4 yeux » |
| D6 | Pharmacie : la table `prescribed_medicines` existe mais **n'est utilisée par aucun écran**. Pas d'ordonnance, de dispensation, de liste positive ni de contrôle de double délivrance. | Haute | Module **pharmacie** (ordonnances, dispensations, liste positive, plafonds) |
| D7 | Les ayants droit sont « actifs » dès leur saisie, sans validation ni date d'intégration ou de sortie. Le lien « enfant adopté » est absent. | Moyenne | Cycle de vie du bénéficiaire (en attente → actif → sorti), dates, justificatif. **Aucune couverture sans validation** |
| D8 | Formule : pas de catégorie, de population cible, d'âges, de nombre de bénéficiaires, de dates de commercialisation, de périodicité semestrielle, ni de statut (brouillon / test / active / suspendue / archivée). | Moyenne | Champs ajoutés à la formule (migrations additives) |
| D9 | **Espace membre** : ancienne PWA sur le sous-domaine, mise en page minimale, connexion par téléphone seul (sans mot de passe oublié ni double appareil), hors de l'application Android. | Haute | **Nouveau portail adhérent** (décision § 5) |
| D10 | Le paiement en ligne de l'ancienne PWA passe par un pilote **« Sandbox »** (paiement simulé). Cela contrevient à la règle « aucun paiement simulé ». | **Haute** | Bouton de paiement **retiré** tant qu'aucun prestataire de paiement réel n'est configuré. Architecture prête pour Mobile Money (rapprochement idempotent déjà prévu) |
| D11 | Réclamations : seulement saisies par la mutuelle. L'adhérent ne peut ni en déposer ni suivre la réponse. | Moyenne | Réclamations depuis le portail adhérent, avec échanges et notifications |
| D12 | Pas de photo d'adhérent ni de bénéficiaire. | Info | Photo facultative, stockée en privé (jamais publique) |
| D13 | Le tableau de bord de la mutuelle est éclaté (facturation, rapports, trésorerie). Il manque la sinistralité et la consommation par formule et par garantie, ainsi que les exports. | Moyenne | Tableau de bord de pilotage avec filtres et exports (CSV/Excel, PDF par impression) |
| D14 | Rôles : il manque le **médecin-conseil**. Le « gestionnaire des cotisations » est aujourd'hui le trésorier-comptable. | Info | Rôle `medical_advisor` (préautorisations, données de santé), sans fusion des rôles existants |
| D15 | Environnement de test : `mutueltest` sert aux essais réels du fondateur. Les mutuelles de démonstration (`is_demo`) du réseau social n'ont pas de base. | Info | Environnement **MUTUELLE TEST** isolé, avec réinitialisation sécurisée (décision § 5) |

## 4. Principes de conception retenus

1. **Rien n'est codé en dur.** Formules, garanties, taux, plafonds, carences, exclusions et préautorisations sont des paramètres, saisis par l'administrateur de la mutuelle.
2. **On fait évoluer l'existant.** La table `guarantees` devient la **formule** (le libellé de l'écran change, pas la donnée). Chaque `guarantee_versions` reçoit ses **garanties** (lignes de couverture). Les formules existantes restent valables : une seule ligne « toutes prestations » reprend leur taux actuel. Aucune donnée n'est perdue.
3. **Versionnement déjà en place.** Une version utilisée est verrouillée. Toute modification crée une nouvelle version datée, avec son auteur. Une prestation garde la version applicable à sa date de soins : aucune rétroactivité.
4. **Un seul moteur** pour le back-office, le portail prestataire, l'estimation et le portail adhérent. Les calculs se font côté serveur, en transaction, et la consommation des plafonds se fait sous verrou.
5. **Résultats honnêtes.** Un paramètre absent est affiché « non défini », jamais remplacé par une valeur par défaut. L'urgence n'est jamais bloquée au stade de la stabilisation. Une dérogation ne modifie jamais la règle et laisse toujours une trace.
6. **Les données d'exemple** (ESSENTIEL, FAMILLE, SCOLAIRE, CONFORT, SENIOR ; tableau des délais de carence ; plafonds pharmacie) ne sont chargées **que dans la MUTUELLE TEST**, marquées « Exemple ».
7. **Les données médicales** (actes détaillés, ordonnances, diagnostics) restent réservées aux rôles habilités et ne quittent jamais la base de la mutuelle.

## 5. Décisions à prendre par le fondateur

1. **Où placer le portail adhérent ?**
   - **A (recommandée)** : sur le domaine principal, dans le compte MX-CONNECT déjà utilisé pour le réseau social (`m.araild.com/compte/mon-adhesion`), et donc aussi dans l'**application Android**.
     - Un seul compte, même si la personne adhère à plusieurs mutuelles ; mot de passe oublié et documents légaux déjà en place.
     - Le lien compte ↔ adhérent existe déjà pour l'adhésion en ligne. Pour les adhérents enregistrés au guichet, la mutuelle remet un **code d'activation** à usage unique.
   - **B** : refaire l'espace membre sur l'adresse de chaque mutuelle (ancienne PWA). Un compte par mutuelle, hors de l'application Android.
2. **Environnement MUTUELLE TEST** :
   - **créer une mutuelle dédiée** « MUTUELLE TEST », marquée « test », réinitialisable, avec les formules d'exemple (recommandé) ;
   - ou **transformer `mutueltest`**. Ses données actuelles seraient alors effacées à chaque réinitialisation.
3. **Paiement en ligne** : aucun agrégateur réel n'est intégré. Proposition : masquer le paiement en ligne jusqu'au choix d'un agrégateur (MTN MoMo, Orange Money, ou un agrégateur camerounais), puis l'intégrer avec rapprochement idempotent. Les reçus et l'historique restent visibles.

## 6. Plan de développement (livraisons successives, chacune testée et documentée)

| Livraison | Phases de la mission | Contenu | Schéma (migrations additives) |
|---|---|---|---|
| **L1** | 1 + 2 + 3 | **Catalogue des formules et garanties**. Formule complétée (catégorie, cible, âges, bénéficiaires, commercialisation, statut, périodicités dont semestrielle). Bibliothèque de catégories de soins. Garanties par version (taux, ticket modérateur, préautorisation, prestataires, conditions). Plafonds multi-portées. Délais de carence par catégorie de bénéficiaire. Écrans d'administration. Formules d'exemple dans la MUTUELLE TEST | `service_categories`, `coverage_rules`, `coverage_limits`, `waiting_periods` ; colonnes sur `guarantees` / `guarantee_versions` ; `act_types.service_category_id` |
| **L2** | 4 | **Moteur de prise en charge** : 8 résultats de droits explicites, carences, plafonds (montant, nombre, partagés, bénéficiaire, famille, jour/mois/an) avec registre des consommations sous verrou, calcul détaillé (facturé, éligible, part mutuelle, ticket modérateur, non couvert, reste). Branché sur le back-office, le portail prestataire et l'estimation. **Dérogations** « 4 yeux ». Renouvellement continu | `coverage_consumptions`, `coverage_exceptions` |
| **L3** | 4 + 6 | **Préautorisations** (workflow, n° d'autorisation, urgence) et **pharmacie** (ordonnances, dispensations, liste positive, double délivrance, plafonds) ; portail prestataire et back-office ; rôle médecin-conseil | `preauthorizations`, `prescriptions`, `dispensations`, `dispensation_lines` ; `medicines` complétée |
| **L4** | 5 | **Portail adhérent** : tableau de bord, profil (modifications sensibles validées par la mutuelle), bénéficiaires (cycle de vie validé), carte numérique PDF, garanties en langage simple, annuaire des prestataires, historique des prestations, cotisations et reçus, réclamations, notifications | `member_change_requests`, `complaint_messages`, colonnes photo, cycle de vie des `dependents` |
| **L5** | 7 | **Tableau de bord de pilotage** : sinistralité, consommations par formule et par garantie, pharmacie, filtres, exports | — |
| **L6** | 8 + 9 | **MUTUELLE TEST** (réinitialisation sécurisée), rôles complémentaires, sécurité, les 12 scénarios de la Partie 10, documentation technique, guides administrateur et adhérent | indicateur `is_test` sur la mutuelle |

Chaque livraison suit le même circuit :
- un ZIP cumulatif avec son LISEZMOI ;
- des tests Pest dans une vraie base de mutuelle, aussi en tables préfixées ;
- des contrôles en navigateur réel à 4 largeurs ;
- la liste de ce qui est vraiment terminé et de ce qui ne l'est pas.

## 7. Scénarios de la Partie 10 : où ils seront couverts

| Scénario | Livraison |
|---|---|
| 1. Consultation des garanties par l'adhérent | L4 (moteur L1–L2) |
| 2. Délai de carence non expiré | L2 |
| 3. Plafond disponible | L2 |
| 4. Dépassement du plafond annuel | L2 |
| 5. Ordonnance facturée deux fois | L3 |
| 6. Couverture expirée | L2 |
| 7. Modification d'une formule → nouvelle version | L1 |
| 8. Dérogation justifiée | L2 |
| 9. Accès aux données d'une autre mutuelle | L4 (et déjà couvert par l'isolation existante) |
| 10. Renouvellement continu sans nouvelle carence | L2 |
| 11. Urgence sans préautorisation | L3 |
| 12. Deux demandes simultanées sur le même plafond | L2 |

## 8. Avancement

**L1–L2 livrées le 18/11/2026 (VERSION 2026.11.18).** Voir `DOCUMENTATION-TECHNIQUE-MOTEUR-PRISE-EN-CHARGE.md` et `GUIDE-ADMINISTRATEUR-FORMULES.md`.

- Scénarios couverts par des tests Pest : 2, 3, 4, 6, 7, 8, 10, 12, et 11 pour la partie carence (urgence et accident).
- **Écart au plan, assumé.** Il n'y a pas de table `coverage_consumptions`. Le registre des consommations, ce sont les prestations elles-mêmes : elles gardent la garantie appliquée, le montant éligible, le ticket modérateur, le non couvert et la dérogation utilisée. La consommation est relue dans la transaction de saisie, après verrouillage de la ligne de l'adhérent. Un seul registre veut dire aucun risque de désaccord entre deux sources. Une prestation rejetée libère sa part sans écriture compensatoire.
- **Préautorisation par garantie** : reportée en L3 avec le module des préautorisations. Le statut correspondant est prévu dans le moteur.
- **Indicateur « test »** : posé sur la mutuelle par le super-admin (`is_test`). La réinitialisation reste en L6.

**L3 livrée le 19/11/2026 (VERSION 2026.11.19).**
- Accords préalables : workflow en 10 étapes, urgence régularisée a posteriori.
- Pharmacie : ordonnances, délivrances, liste positive, double facturation.
- Rôle de médecin-conseil.
- Scénarios 5 et 11 couverts par des tests Pest.

**L4 livrée le 20/11/2026 (VERSION 2026.11.20).**
- Portail adhérent dans le compte MX-CONNECT (`/compte/mon-adhesion`, aussi dans l'application Android).
- Activation par code au guichet.
- Demandes de modification validées par la mutuelle.
- Réclamations en ligne.
- Notifications sans donnée médicale.
- Scénarios 1 et 9 couverts par des tests Pest.
- Guide de l'adhérent : `GUIDE-ADHERENT.md`.

**L5 livrée le 21/11/2026 (VERSION 2026.11.21).**
- Tableau de bord de pilotage (`/pilotage`) : adhésions, cotisations, prestations, consommation par formule, par garantie et en pharmacie, plafonds consommés, taux de sinistralité.
- Filtres : période, formule, prestataire, antenne, type de soins, statut, adhérent.
- Graphique mensuel, export Excel (`.xlsx`) et impression ou PDF.
- **Écart au plan, assumé** : il n'y a pas de filtre « région », car la région n'est pas enregistrée sur les fiches. L'antenne tient lieu de zone.
