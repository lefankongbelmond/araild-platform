# MX-CONNECT — Portail prestataire V2 : audit technique, diagnostic et plan de développement

Version de référence auditée : **2026.11.14**. Phase 1 livrée en **2026.11.15**.
Ce document répond à l'étape « audit d'abord » du cahier des charges « Portail prestataire V2 ».

---

## 1. Ce qui existe déjà (et fonctionne)

Le portail n'est pas une application séparée. C'est un espace du back-office de chaque mutuelle (`https://<mutuelle>.m.araild.com/prestataire`), réservé au rôle « Prestataire de soins ».

| Domaine | Existant | Où |
|---|---|---|
| Compte | Compte du personnel de la mutuelle avec le rôle `provider`, rattaché à un établissement (`users.care_provider_id`). Double authentification obligatoire. Création par l'administrateur de la mutuelle. | `TeamController`, `ProviderController` |
| Établissements | Registre CENTRAL `care_providers` (nom, type, localité, actif). Convention PAR mutuelle dans la base de la mutuelle `provider_conventions` (n°, dates, active, contact). | `App\Models\Central\CareProvider`, `ProviderConvention` |
| Droits d'accès | Matrice unique `TenantAccess` : `portal.*` = prestataire seul. Le prestataire n'ouvre aucune autre rubrique. | `App\Support\TenantAccess` |
| Identification | Code adhérent ou QR signé de la carte (photo ou caméra, lecteur intégré). Verdict seulement, aucune donnée médicale. | `ProviderPortalController::verify` |
| Contrôle des droits | 6 contrôles : adhérent actif, cotisations à jour (indépendant du cron), garantie applicable, période d'observation, acte exclu, établissement hors réseau de la garantie. | `RightsVerificationService` |
| Journal | Chaque vérification est journalisée (`rights_checks` : compte, établissement, adhérent, verdict, motifs, heure). | |
| Déclaration | Seulement depuis une vérification positive de moins de 12 h, faite par le même compte. Personne soignée (adhérent ou ayant droit actif), acte, date (≤ 30 jours, jamais future), montant. | `ProviderPortalController::storeDeclaration` |
| Calcul | Part mutuelle = taux de couverture de la version de garantie, plafonnée (plafond par acte ou annuel). Le reste est à la charge du patient. | `ClaimAssessmentService` |
| Anti-fraude | Signaux non bloquants (doublon, date atypique, plafond atteint, surconsommation) dans `alerts`. | `FraudDetectionService` |
| Validation | « 4 yeux » côté mutuelle : personne ne valide sa propre saisie. | `PrestationController` |
| Facturation | Demande de paiement par prise en charge validée (tiers payant), n° de facture du prestataire, PDF contrôlé (5 Mo, `%PDF-`, disque privé). Registre des factures côté mutuelle, rejet motivé, paiement enregistré hors plateforme (aucun fonds détenu). | `InvoiceDocumentService`, `InvoiceRegisterController` |
| Isolation | Toutes les requêtes sont filtrées sur l'établissement du compte et exécutées dans la base (ou les tables préfixées) de la mutuelle. | |

## 2. Anomalies trouvées

| # | Anomalie | Gravité | Traitement |
|---|---|---|---|
| A1 | Le lecteur de QR du portail chargeait ses scripts depuis le sous-domaine de la mutuelle, qui ne sert aucun fichier statique en production : le bouton « Scanner avec la caméra » et la lecture sur photo ne pouvaient pas fonctionner sur `<mutuelle>.m.araild.com`. | **Haute** | **Corrigé (phase 1)** |
| A2 | Rejet d'une prestation sans motif : le prestataire voyait « Rejetée » sans savoir pourquoi. | Haute | **Corrigé** : motif obligatoire, visible par le prestataire |
| A3 | Deux déclarations identiques (double clic, double saisie) passaient sans avertissement : risque de double facturation. | Haute | **Corrigé** : confirmation explicite exigée ; la mutuelle reçoit toujours un signal |
| A4 | Demande de paiement sans verrou : deux clics simultanés pouvaient créer deux demandes. | Moyenne | **Corrigé** : verrou de la prise en charge pendant l'enregistrement |
| A5 | Plafond annuel calculé sur les seules prestations validées : deux déclarations proches pouvaient consommer deux fois le même reste. | Moyenne | **Corrigé** : les parts en attente de validation sont réservées |
| A6 | Convention arrivée à échéance : le prestataire ne pouvait plus facturer des soins faits et validés pendant la convention. | Moyenne | **Corrigé** : facturation des soins déjà validés possible tant que la mutuelle n'a pas suspendu la convention |
| A7 | Un établissement désactivé dans le registre central gardait l'usage du portail. | Moyenne | **Corrigé** |
| A8 | `copay_rate` (ticket modérateur) est enregistré mais jamais utilisé : la part patient = total − part mutuelle. | Info | Conservé tel quel (aucun taux inventé). À afficher dans le moteur de couverture (phase 3) |
| A9 | Plafond « par séjour » traité comme « par acte ». | Info | Phase 4 (hospitalisation) |
| A10 | Le montant déclaré n'est pas comparé au tarif conventionnel de l'acte (`act_types.tariff_minor`). | Moyenne | Phase 3/5 : signal « montant supérieur au tarif », jamais de correction automatique |
| A11 | Une déclaration du portail = une prise en charge = une facture : pas de regroupement par bordereau. | Info | Phase 5 |
| A12 | Le statut `controlled` des prestations existe mais n'est jamais posé. | Info | Phase 4 (circuit d'accord préalable) |
| A13 | Recherche seulement par code ou QR ; aucune recherche par nom, téléphone ou pièce. | Info | Phase 3 (recherche encadrée, voir § 4) |
| A14 | Aucune notification au prestataire quand la mutuelle valide, rejette ou paie. | Info | Phase 6 |
| A15 | Les comptes du prestataire sont créés par la mutuelle ; l'établissement ne gère pas ses utilisateurs. | Info | Phase 2 |
| A16 | Un établissement conventionné avec plusieurs mutuelles a un compte par mutuelle, sans vue d'ensemble. | Structurant | **Décision à prendre** (§ 3) |

## 3. Décision d'architecture à prendre avant la phase 2

**Comptes prestataires : par mutuelle (actuel) ou espace prestataire central multi-mutuelles ?**

| | Option A — garder un compte par mutuelle | Option B — espace prestataire central (recommandé) |
|---|---|---|
| Adresse | `<mutuelle>.m.araild.com/prestataire` | `m.araild.com/prestataire`, puis choix de la mutuelle |
| Compte | Un par mutuelle | Un seul par personne, rôles DANS l'établissement (administrateur, accueil, médecin, pharmacien, laborantin, facturation, lecture) |
| Multi-mutuelles (module 12) | Non : tableaux de bord séparés | Oui : tableau de bord consolidé par devise, sans conversion |
| Isolation | Inchangée | Chaque action est exécutée DANS la mutuelle visée (`$mutual->run()`), seulement si une convention existe ; jamais de requête d'une mutuelle sur les tables d'une autre |
| Existant | Rien ne change | Les comptes actuels continuent de fonctionner pendant la transition (aucune suppression) |
| Coût | Faible | Plus élevé (phase 2 plus longue) |

Une **Option C** intermédiaire est possible : garder les comptes par mutuelle et ajouter seulement, sur le domaine central, un sélecteur qui ouvre le portail de chaque mutuelle. Elle ne donne pas de tableau de bord consolidé.

Autres points à confirmer :

1. **CIM-10** : liste à utiliser (OMS ou version française), et rôles autorisés à voir le diagnostic. Proposition : réservé au médecin de l'établissement et aux rôles « donnée de santé » de la mutuelle, jamais affiché au guichet, à la facturation ni dans le réseau social.
2. **Tarifs par convention** : tarifs propres à chaque établissement (avec historique) en plus du tarif conventionnel de l'acte ?
3. **Seuils d'accord préalable** : par acte, par montant, ou les deux ? Fixés par la mutuelle, jamais par défaut.
4. **Hors connexion** : saisie d'un brouillon et d'un acte d'urgence permis ; l'écran dira toujours « Droits NON vérifiés — à régulariser ». Aucune confirmation de droits hors ligne.

## 4. Règles de conception pour les phases suivantes

- **Couverture** : calculée uniquement à partir des paramètres de la version de garantie (taux, plafonds, exclusions, réseau, tarifs). Si un paramètre manque, l'écran affiche « non défini — à confirmer par la mutuelle », jamais un taux par défaut.
- **Urgence** : un acte urgent n'est jamais bloqué par une panne ou un droit non vérifiable. Il est déclaré « urgence — droits à régulariser » et examiné par la mutuelle.
- **Facture validée ≠ payée** : statuts distincts ; « payée » seulement quand le trésorier enregistre le paiement réel.
- **Alertes = signaux** : affichées comme « à examiner », jamais comme une preuve, jamais bloquantes sauf règle contractuelle explicite.
- **Historique médical** : le prestataire ne voit que ses propres prises en charge. Il ne voit jamais l'historique tenu par d'autres établissements.
- **Recherche par nom ou téléphone** : exige au moins deux critères (par exemple nom + date de naissance). Elle renvoie au plus quelques résultats masqués (nom, année de naissance, code partiel), est journalisée et limitée en fréquence.

## 5. Plan de développement par phase

| Phase | Contenu | Schéma (migrations additives) | Livraison |
|---|---|---|---|
| **1. Audit et corrections** | Anomalies A1–A7 | `prestations.decision_note`, `decided_at` | **2026.11.15 — faite** |
| **2. Profil et tableau de bord** | Fiche établissement (coordonnées, horaires, spécialités, documents de convention), utilisateurs de l'établissement et rôles, tableau de bord avec indicateurs réels (vérifications, prises en charge par statut, montants à facturer, factures en attente ou payées, délais moyens), graphiques et alertes. | Selon la décision du § 3 : `provider_accounts` + `provider_account_roles` (central) ou `provider_staff_roles` (tenant) ; `care_providers` complété | 1 livraison |
| **3. Identification et droits** | Recherche encadrée (carte, n°, QR, nom + date de naissance, téléphone, pièce), statuts détaillés (actif, suspendu, en observation, cotisations en retard, plafond atteint, hors réseau), simulation de couverture avant soins (« estimation »), bon de prise en charge imprimable | `coverage_estimates` (ou calcul à la volée journalisé) | 1 livraison |
| **4. Soins et accords préalables** | Demandes d'accord (types, 9 statuts, circuit, urgence), feuille de soins électronique + PDF, catalogue d'actes et tarifs par convention, CIM-10 protégée, hospitalisation (séjour, plafond par séjour), orientation, pharmacie (ordonnance, délivrance), laboratoire | `care_authorizations`, `authorization_events`, `care_sheets`, `care_sheet_lines`, `diagnoses` (chiffré), `hospital_stays`, `referrals`, `prescriptions`, `dispensations`, `lab_orders`, `convention_tariffs` (avec dates) | 2 livraisons |
| **5. Facturation et paiements** | Factures à lignes, bordereaux d'envoi groupés (lot + PDF), rejets partiels et renvoi, statuts distincts validée/payée, paiements confirmés seulement, exports CSV | `invoice_batches`, `provider_invoice_lines`, `invoice_events` | 1 livraison |
| **6. Contrôle et communication** | Tableau des signaux (montant > tarif, doublons, fréquence), journal d'audit consultable, messagerie prestataire ↔ mutuelle par dossier, notifications (validation, rejet, paiement) | `provider_threads`, `provider_messages`, `provider_notifications` | 1 livraison |
| **7. Rapports et fonctions avancées** | Rapports filtrés et exportés, rendez-vous, mode mobile, brouillons hors connexion et urgences (jamais de droits hors ligne), multi-mutuelles consolidé (si option B) | `appointments`, `offline_drafts` (côté appareil) | 1 livraison |

Chaque phase livrera : fonctionnalités, fichiers modifiés, migrations, routes, tests (Pest dans une vraie base de mutuelle + mode tables préfixées, navigateur en conditions LWS), anomalies, limites et instructions d'installation.

## 6. Scénario de test imposé (15 étapes) et cas limites : couverture prévue

| Cas | Phase où il devient testable de bout en bout |
|---|---|
| Adhérent inactif, cotisations non à jour | Déjà couvert (refus avec motifs) — enrichi en phase 3 |
| Plafond atteint | Déjà couvert (part mutuelle plafonnée + signal), réservation en attente (phase 1) |
| Acte non couvert | Déjà couvert (exclusions de la garantie) |
| Demande rejetée | Phase 1 (motif) ; accords préalables en phase 4 |
| Facture incomplète | Phase 5 |
| Double facturation | Phase 1 (double déclaration, double demande) ; lignes en phase 5 |
| Convention expirée | Phase 1 |
| Utilisateur non autorisé | Déjà couvert (matrice des droits, cloisonnement par établissement) |
| Perte de connexion | Phase 7 (page hors ligne déjà en place, sans aucune donnée) |
