# Bilan de la mission « Mutuelle paramétrable + portail adhérent »

MX-CONNECT, 22/11/2026, VERSION 2026.11.22. La mission a été livrée en six fois, de L1 à L6, du 18 au 22/11/2026. Chaque livraison comprend un ZIP cumulatif, un LISEZMOI, des tests Pest rejoués en base dédiée et en tables préfixées, et un contrôle dans un navigateur réel à 4 largeurs.

## 1. Les 17 livrables de la Partie 12

| # | Livrable | État | Où |
|---|---|---|---|
| 1 | Portail adhérent | Fait (L4) | Compte MX-CONNECT → « Mon adhésion » (site et application Android) |
| 2 | Création et gestion des formules | Fait (L1) | Paramètres → Catalogue des formules |
| 3 | Gestion des garanties | Fait (L1) | Fiche d'une formule → Garanties |
| 4 | Moteur des plafonds | Fait (L2) | `CoverageEngine` : montant et/ou nombre, acte/jour/mois/an, bénéficiaire/famille, partagés |
| 5 | Moteur des délais de carence | Fait (L2) | Par garantie et catégorie de bénéficiaire ; urgence, accident, renouvellement continu |
| 6 | Calcul des prises en charge | Fait (L2) | Côté serveur, sous verrou, dans une transaction ; 8 statuts explicites |
| 7 | Contrôle des médicaments | Fait (L3) | Liste positive, exclusions, quantités, ordonnances, double facturation, délivrances rapprochées |
| 8 | Préautorisations | Fait (L3) | Workflow, numéro, avis du médecin-conseil, urgence régularisée |
| 9 | Dérogations | Fait (L2) | Demande motivée, décision « 4 yeux », retrait tracé, règles initiales intactes |
| 10 | MUTUELLE TEST | Fait (L1 + L6) | Marquage par le réseau, formules d'exemple, bandeau, **réinitialisation sécurisée** |
| 11 | Tableau de bord administrateur | Fait (L5) | Pilotage → Tableau de bord ; filtres, graphique, Excel, impression ou PDF |
| 12 | Utilisateurs et permissions | Fait (Lots 6-7, L3, L6) | Matrice par route, 10 profils, MFA, 4 yeux, traçabilité des accès aux données de santé |
| 13 | Documentation technique | Fait | `DOCUMENTATION-TECHNIQUE-MOTEUR-PRISE-EN-CHARGE.md` (§ 1 à 23) |
| 14 | Guide administrateur | Fait | `GUIDE-ADMINISTRATEUR-FORMULES.md` (§ 1 à 15) |
| 15 | Guide adhérent | Fait | `GUIDE-ADHERENT.md` |
| 16 | Scripts de migration | Fait | Voir § 3 : additifs, appliqués automatiquement à la mise à jour |
| 17 | Tests automatisés | Fait | Voir § 2 |

## 2. Tests

- **Les 12 scénarios de la Partie 10** ont chacun au moins un test. Le tableau de correspondance est au § 23 de la documentation technique. Commande : `vendor/bin/pest --filter "scénario"`.
- **Tests propres à la mission** :
  - `MoteurPriseEnChargeTest`, `CatalogueFormulesTest` ;
  - `AccordsPrealablesPharmacieTest`, `PortailAdherentTest` ;
  - `TableauDeBordPilotageTest`, `MutuelleTestReinitialisationTest` ;
  - `GestionnaireCotisationsTest`, `TraceAccesSanteTest`.

  Tous passent en base dédiée et en tables préfixées (`MX_TEST_STORAGE=prefix`).
- **Suite complète** : tous les tests Pest de MX-CONNECT passent (nombre exact dans le LISEZMOI de chaque livraison).

## 3. Migrations de la mission

Toutes sont additives : aucune donnée existante supprimée.

| Base | Migration | Contenu |
|---|---|---|
| Chaque mutuelle | `2026_11_18_000100_coverage_catalog` | Catalogue : fiche de formule, garanties, plafonds, carences, catégories de soins, dérogations |
| Chaque mutuelle | `2026_11_19_000100_preauth_pharmacy` | Accords préalables, ordonnances, délivrances, politique pharmaceutique, rôle médecin-conseil |
| Chaque mutuelle | `2026_11_20_000100_member_portal` | Demandes des adhérents, échanges sur les réclamations |
| Chaque mutuelle | `2026_11_22_000100_contribution_manager_role` | Rôle gestionnaire des cotisations |
| Centrale | `2026_11_20_000100_create_member_activation_codes` | Codes d'activation du portail adhérent |

## 4. Ce qui n'est PAS fait, ou fait autrement

Ces écarts ont été dits à chaque livraison ; ils sont réunis ici.

1. **Procédure de migration des adhérents vers une nouvelle formule** (Partie 5.2) : **non faite**.
   - Ce qui marche aujourd'hui : une modification crée une nouvelle version datée, et les adhérents restent sur leur version, sans effet rétroactif.
   - Ce qui manque : faire passer un groupe d'adhérents sur une nouvelle version ou une autre formule, avec date d'effet, validation « 4 yeux » et échéanciers recalculés. **Proposé comme prochaine étape.**
2. **Règles de suspension paramétrables** : la règle est fixe (échéance échue impayée = droits suspendus). Il n'existe pas encore de délai de grâce réglable par mutuelle.
3. **Région** : non enregistrée sur les fiches ; l'antenne sert de zone dans le tableau de bord.
4. **Anglais** : l'application a des fichiers de langue (`lang/fr`, `lang/en`), mais les écrans de la mission sont rédigés directement en français. Les traduire demandera de passer leurs textes dans ces fichiers.
5. **PDF côté serveur** : attestation, carte, reçus et tableau de bord passent par l'impression du navigateur.
6. **Paiement en ligne** : masqué tant qu'aucun agrégateur n'est choisi. Rien n'est simulé.
7. **SMS et WhatsApp** : aucun fournisseur choisi. Les notifications passent par l'application et l'e-mail.
8. **Charge** : non mesurée sur l'hébergement LWS (nombre d'utilisateurs simultanés).
9. **Production** : rien n'a été vérifié sur https://m.araild.com, qui n'est pas joignable depuis l'environnement de développement. Tout a été testé sur une copie fidèle du site LWS.
