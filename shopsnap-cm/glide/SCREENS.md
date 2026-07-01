# Spécification des écrans Glide — ShopSnap CM

> Je ne peux pas créer de compte Glide ni générer un vrai lien d'app partageable depuis cette session (ça nécessite un compte Glide + une connexion Airtable authentifiés, que je n'ai pas). Ce document est la spec exacte à suivre pour construire l'app dans Glide en ~45 minutes, écran par écran. C'est la même chose qu'un développeur Glide recevrait comme cahier des charges.

## Connexion de la donnée
Glide → **New App** → **Connect Data** → **Airtable** → sélectionner la base ShopSnap CM (les 4 tables + les rollups/formulas suivent automatiquement).

## Réglages globaux de l'app
- **Layout** : Tab (barre du bas), pas Menu — plus rapide au pouce.
- **PWA** : Settings → General → cocher "Progressive Web App" + activer "Add to Home Screen" prompt.
- **Couleurs du thème** : Primaire `#25D366` (vert WhatsApp), Accent `#FFA500` (orange argent).
- **Langue** : Settings → Localization → activer FR + EN. Ajouter un champ `Langue_App` au **User Profile** (table interne Glide, pas Airtable) avec un sélecteur 🌐 sur l'écran de connexion. Toutes les colonnes de libellé (catégories, statuts, boutons) utilisent une colonne calculée `If → Then → Else` basée sur `Langue_App`.
- **Auth** : Settings → Sign-in → Email/Phone + code (OTP). Limiter à 2 utilisateurs invités par boutique (Glide Business plan, gestion des sièges).

---

## Onglet 1 — Vendre (écran d'accueil, icône 🛒)
But : enregistrer une vente en **3 clics max**.

1. Bouton géant "+ Nouvelle Vente" (vert #25D366).
2. Étape 1/3 — **Choisir le produit** : barre de recherche + liste avec vignette photo (Glide "Search Bar" + Inline List filtré sur `Actif = true`). Option scan code-barre (composant natif Glide "Barcode Scanner" qui cherche dans `Code_Barre`).
3. Étape 2/3 — **Quantité** : stepper +/- avec `Prix_Vente_FCFA` affiché en temps réel (`Quantite × Prix_Vente_FCFA`).
4. Étape 3/3 — **Mode de paiement** : 3 gros boutons icônes — 💵 Cash / 📱 Mobile Money / 🟠 Crédit.
   - Si Crédit → écran "Choisir client" (liste `Clients` + bouton "+ Nouveau client" en haut) obligatoire avant validation.
5. Bouton "Valider la vente" → crée la ligne dans `Ventes` (Form/Add Row action), `Date_Vente` pré-rempli avec `Now()`.

C'est le parcours à 3 clics : Produit → Qté/Paiement → Valider.

## Onglet 2 — Stock (icône 📦)
1. Liste des produits, triée par `Alerte_Stock` (🔴 en premier).
2. Barre de recherche + filtre par `Categorie`.
3. Sur chaque ligne : nom, stock actuel, badge 🔴/🟢.
4. Bouton "+ Entrée stock" sur chaque fiche produit → popup quantité → Set Column `Stock_Actuel = Stock_Actuel + quantité saisie`.
5. Bouton "+ Nouveau produit" en haut (Form Airtable classique).

## Onglet 3 — Clients (icône 👥)
1. Liste des clients triée par `Solde_Du_FCFA` décroissant (ceux qui doivent le plus en premier).
2. Fiche client : Nom, Téléphone, `Statut_Solde` (🟠/✅), historique des `Ventes_Credit` et `Paiements`.
3. Bouton **"Rappeler sur WhatsApp"** (vert #25D366, icône WhatsApp) → action "Open Link" vers `https://wa.me/{WhatsApp}` en fallback direct, ET déclenche le scénario Make.com si vous préférez le template WATI automatique (webhook Make déclenché depuis un bouton Glide via "Call API").
4. Bouton "+ Enregistrer un paiement" → Form vers `Paiements_Credit`, pré-rempli avec le client courant.

## Onglet 4 — Caisse (icône 💰)
1. Gros chiffre du jour : Somme de `Ventes.Montant_Total_FCFA` où `Date_Vente = Today()` ET `Mode_Paiement ≠ credit`.
2. Sous-total : chiffre d'hier (`Date_Vente = Today() - 1`), avec flèche ▲/▼ de variation en %.
3. Répartition Cash vs Mobile Money du jour (2 barres simples).
4. Pas de comptabilité, pas de graphique complexe — juste le chiffre.

---

## Notes de build Glide
- Tous les libellés visibles par l'utilisateur (`Categorie`, `Mode_Paiement`, `Statut_*`) sont des **colonnes calculées côté Glide** qui traduisent les codes neutres stockés dans Airtable — voir `airtable/STRUCTURE.md`.
- Le mode hors-ligne est natif à Glide (Pages/Apps se comportent comme des PWA avec cache local) : aucune configuration additionnelle nécessaire, mais tester explicitement "mode avion → vente → retour réseau → vérifier sync" avant mise en prod.
