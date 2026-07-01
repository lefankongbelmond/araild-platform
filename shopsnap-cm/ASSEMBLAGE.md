# Assemblage complet — ShopSnap CM

⚠️ **Ce que je ne peux pas faire depuis cette session** : créer les comptes réels Airtable / Glide / Make.com / Flutterwave / WATI à ta place. Je n'ai ni identifiants ni accès navigateur à ces plateformes — ce sont des comptes tiers que toi (ou ton installateur) dois créer et piloter à la main via leurs interfaces web. Ce document ne remplace pas ça : il donne l'**ordre exact d'assemblage**, avec un test de validation à chaque étape, pour que tu puisses tout brancher toi-même sans te perdre entre les 10 fichiers déjà livrés.

Temps total estimé : **~1 journée** de config (hors validation KYC Flutterwave, qui peut prendre 24-72h en parallèle).

---

## Ordre de dépendance

```
1. Airtable base "Boutique"        (autonome)
2. Airtable base "Admin"           (autonome)
3. Make — scénario Vente→Stock→WhatsApp     (dépend de 1)
4. WATI — templates FR/EN                    (dépend de rien, mais bloque 3 et 6)
5. Flutterwave — compte + clés               (autonome, lancer le KYC en premier car lent)
6. Make — scénarios Abonnement (2)           (dépend de 2, 4, 5)
7. Glide — connecter les 2 bases + 4 écrans + écran blocage  (dépend de 1, 2)
8. Test de bout en bout                      (dépend de tout)
```

Astuce : lance le KYC Flutterwave (étape 5) **en tout premier**, en parallèle du reste — c'est la seule étape avec un délai externe (24-72h) hors de ton contrôle.

---

## Étape 1 — Base Airtable "Boutique" (~30 min)
📄 Référence : `airtable/STRUCTURE.md`

1. Crée une base Airtable, 4 tables : `Produits`, `Clients`, `Ventes`, `Paiements_Credit`.
2. Importe les 4 CSV (`airtable/*.csv`).
3. Convertis les colonnes texte `Produit` (dans `Ventes`) et `Client` (dans `Ventes` et `Paiements_Credit`) en **Link to another record** — Airtable propose la conversion automatiquement si les noms correspondent exactement (c'est le cas, déjà vérifié).
4. Ajoute les champs formule/rollup listés dans `STRUCTURE.md` qui ne viennent pas du CSV (`Alerte_Stock`, `Marge_Unitaire_FCFA`, `Solde_Du_FCFA`, `Statut_Solde`, `Montant_Total_FCFA`, `Montant_Credit_FCFA`, `Statut_Paiement`).

✅ **Test de validation** : les 5 soldes clients affichés correspondent exactement à :
Marie Ngo Bell 10 000 · Paul Eto'o Mballa 4 500 · Aissatou Bello 6 000 · Jean-Pierre Fotso 7 000 · Grace Achu 4 500 FCFA.
Si un chiffre est faux → un lien Produit/Client n'a pas été converti à l'étape 3.

## Étape 2 — Base Airtable "Admin" (~15 min)
📄 Référence : `paiement/STRUCTURE_ABONNEMENTS.md`

1. Crée une **2ᵉ base séparée** (`ShopSnap_CM_Admin`), une seule table `Abonnements`.
2. Importe `paiement/abonnements.csv` (1 ligne d'exemple), ajoute les champs formule (`Date_Prochaine_Echeance`, `Jours_Avant_Echeance`).
3. Note quelque part le **Base ID** (visible dans l'URL, commence par `app...`) — il sera réutilisé aux étapes 6 et 7.

✅ **Test de validation** : `Jours_Avant_Echeance` affiche bien un nombre cohérent avec `Date_Debut` + 30 jours moins aujourd'hui.

## Étape 3 — Make : scénario Vente → Stock → WhatsApp (~20 min, à finir après l'étape 4)
📄 Référence : `make/README.md` + `make/scenario_vente_stock_whatsapp.json`

1. Importe le blueprint dans Make.com, reconnecte Airtable à la base "Boutique" (étape 1).
2. Laisse `YOUR_WATI_API_KEY` en attente — impossible à tester avant l'étape 4.

## Étape 4 — WATI : templates WhatsApp (~1-2h + attente approbation Meta)
📄 Référence : `make/README.md` (section templates) + `paiement/make/scenario_generation_lien_paiement.json` (notes)

Créer et faire approuver par Meta **4 templates** :
| Template | Usage |
|---|---|
| `rappel_credit_fr` / `credit_reminder_en` | Rappel de dette client (scénario étape 3) |
| `rappel_abonnement_fr` / `subscription_reminder_en` | Rappel d'abonnement boutique (scénario étape 6) |

⚠️ L'approbation Meta peut prendre plusieurs heures à quelques jours — à lancer tôt.

✅ **Test de validation** : les 4 templates ont le statut "Approved" dans le dashboard WATI. Retourne à l'étape 3 pour coller la clé API WATI et faire un "Run once" test (voir section Tests plus bas).

## Étape 5 — Flutterwave : compte + KYC (~30 min de saisie + 24-72h d'attente KYC)
📄 Référence : `paiement/DECISION.md` + `paiement/GUIDE_CONFIG_FLUTTERWAVE.md`

1. Créer le compte sur `flutterwave.com/cm`, soumettre le KYC (identité + registre de commerce/déclaration d'activité).
2. **Pendant que le KYC est en attente**, tu peux déjà travailler en clés Test (`GUIDE_CONFIG_FLUTTERWAVE.md` section 5) — pas besoin d'attendre pour avancer sur l'étape 6.
3. Créer le Payment Plan (optionnel, carte bancaire) si besoin — voir `paiement/PAYMENT_PLAN_FLUTTERWAVE.md`.

✅ **Test de validation** : tu peux générer un lien de paiement test depuis le dashboard Flutterwave (mode Test).

## Étape 6 — Make : scénarios Abonnement (~30 min)
📄 Référence : `paiement/GUIDE_CONFIG_FLUTTERWAVE.md` + les 2 fichiers `paiement/make/*.json`

1. Importe `scenario_generation_lien_paiement.json`, reconnecte à la base "Admin" (étape 2) + WATI (étape 4) + clé secrète Flutterwave (étape 5, mode Test pour l'instant).
2. Importe `scenario_webhook_flutterwave.json`, copie l'URL de webhook générée par Make dans Flutterwave (Settings → Webhooks), avec un secret hash de ton choix collé des deux côtés.

✅ **Test de validation** : voir la section Tests plus bas (Run once + paiement Mobile Money test).

## Étape 7 — Glide : l'app (~45 min)
📄 Référence : `glide/SCREENS.md` + `paiement/STRUCTURE_ABONNEMENTS.md` (section "Côté Glide")

1. Connecte la base "Boutique" (étape 1) comme source principale.
2. Ajoute la base "Admin" (étape 2) comme **2ᵉ source**, filtrée sur la ligne de cette boutique.
3. Construis les 4 onglets métier (Vendre, Stock, Clients, Caisse) selon `SCREENS.md`.
4. Ajoute l'écran de blocage "Abonnement expiré" (condition `Statut_Abonnement = suspendu`) qui masque les 4 onglets et affiche `Lien_Paiement_Actuel`.
5. Active PWA + bilingue FR/EN (réglages détaillés dans `SCREENS.md`).

✅ **Test de validation** : le parcours de vente prend moins de 10 secondes, l'écran de blocage apparaît si tu forces `Statut_Abonnement = suspendu` manuellement dans Airtable.

## Étape 8 — Test de bout en bout
Une fois tout branché, le test qui valide vraiment l'assemblage (détail complet déjà donné dans la réponse précédente, résumé ici) :
1. Vendre à crédit dans Glide → vérifier le stock décrémenté + le solde client mis à jour.
2. Recevoir le rappel WhatsApp de crédit dans la bonne langue.
3. Sur la base Admin, forcer une échéance d'abonnement à 3 jours → recevoir le rappel + lien de paiement.
4. Payer ce lien avec un [numéro Mobile Money de test Flutterwave](https://developer.flutterwave.com/docs/testing-helpers) → vérifier que `Statut_Abonnement` repasse `actif` dans Airtable et que l'app Glide débloque les 4 onglets.
5. Couper le réseau, faire une vente, remettre le réseau → vérifier la synchro et la date réelle conservée.

---

## Suivi
Coche au fur et à mesure : `[ ]` Étape 1 · `[ ]` Étape 2 · `[ ]` Étape 3 · `[ ]` Étape 4 · `[ ]` Étape 5 · `[ ]` Étape 6 · `[ ]` Étape 7 · `[ ]` Étape 8.
