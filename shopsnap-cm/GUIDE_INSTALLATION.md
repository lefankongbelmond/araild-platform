# Guide d'installation — ShopSnap CM
### 5 étapes pour une commerçante / 5 steps for a shop owner

*(FR à gauche, EN à droite — même app, bilingue dès l'installation)*

---

### Étape 1 — Recevoir le lien / Receive the link
🇫🇷 Votre installateur vous envoie un lien par SMS ou WhatsApp (ex : `https://votreapp.glide.page`).
🇬🇧 Your installer sends you a link by SMS or WhatsApp (e.g. `https://votreapp.glide.page`).

### Étape 2 — Installer l'app sur l'écran d'accueil / Add to home screen
🇫🇷 Ouvrez le lien avec votre navigateur (Chrome). Appuyez sur le menu ⋮ puis **"Ajouter à l'écran d'accueil"**. L'icône ShopSnap apparaît comme une vraie application.
🇬🇧 Open the link in Chrome. Tap the ⋮ menu then **"Add to Home screen"**. The ShopSnap icon appears like a real app.

### Étape 3 — Se connecter / Sign in
🇫🇷 Entrez votre numéro de téléphone. Vous recevez un code à 6 chiffres par SMS. Entrez-le. Choisissez votre langue préférée (🇫🇷 Français / 🇬🇧 English) — l'app entière s'affiche dans cette langue.
🇬🇧 Enter your phone number. You'll receive a 6-digit code by SMS. Enter it. Choose your preferred language — the whole app displays in that language.

### Étape 4 — Ajouter vos produits et clients / Add your products and clients
🇫🇷 Onglet **Stock** → "+ Nouveau produit" pour chaque article (nom, prix d'achat, prix de vente, quantité). Onglet **Clients** → "+ Nouveau client" pour vos clients habituels à crédit.
🇬🇧 **Stock** tab → "+ New product" for each item. **Clients** tab → "+ New client" for your regular credit customers.

### Étape 5 — Faire votre première vente / Make your first sale
🇫🇷 Onglet **Vendre** → choisissez le produit → la quantité → le mode de paiement (Cash / Mobile Money / Crédit) → Valider. C'est fait en moins de 10 secondes.
🇬🇧 **Sell** tab → pick the product → quantity → payment mode (Cash / Mobile Money / Credit) → Confirm. Done in under 10 seconds.

---

## Abonnement / Subscription
🇫🇷 5.000 FCFA/mois, paiement Orange Money ou MTN Money via le lien envoyé le jour de l'inscription puis chaque mois. Sans paiement, l'accès est suspendu (les données restent en sécurité, elles ne sont pas supprimées).
🇬🇧 5,000 FCFA/month, pay via Orange Money or MTN Money through the link sent at signup and each month. Without payment, access is suspended (your data stays safe, it is not deleted).

## Besoin d'aide ? / Need help?
🇫🇷 Envoyez "AIDE" sur le numéro WhatsApp de support fourni par votre installateur.
🇬🇧 Send "HELP" to the support WhatsApp number provided by your installer.

---

## Annexe technique (pour l'installateur, pas pour la commerçante)
1. Créer la base Airtable (voir `airtable/STRUCTURE.md`) et importer les 4 `.csv`.
2. Après import, convertir les colonnes texte `Produit` et `Client` (dans `Ventes` et `Paiements_Credit`) en champs **Link to another record**.
3. Connecter Glide à cette base Airtable (voir `glide/SCREENS.md`) et construire les 4 onglets.
4. Importer `make/scenario_vente_stock_whatsapp.json` dans Make.com, reconnecter les comptes, créer les 2 templates WATI.
5. Configurer Paystack ou Flutterwave pour le prélèvement récurrent 5.000 FCFA/mois (Mobile Money), lier le webhook de paiement à un champ `Statut_Abonnement` sur le User Profile Glide.
