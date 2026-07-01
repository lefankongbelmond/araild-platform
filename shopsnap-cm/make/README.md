# Scénario Make.com — ShopSnap CM

## Fichier
`scenario_vente_stock_whatsapp.json` — Nouvelle Vente → MAJ Stock → Rappel WhatsApp (si crédit, dans la langue du client).

## Import
1. Make.com → **Scenarios** → **Create a new scenario** → menu ⋮ en haut à droite → **Import Blueprint**.
2. Charger le fichier `.json`.
3. Make va demander de **reconnecter** chaque module (Airtable ×3, HTTP ×2) à vos comptes — c'est normal et obligatoire, les clés API/connexions ne sont jamais incluses dans un export.
4. Remplacer dans les paramètres :
   - `YOUR_AIRTABLE_BASE_ID` → l'ID de votre base (visible dans l'URL Airtable, commence par `app...`)
   - `YOUR_WATI_API_KEY` → votre clé API WATI (Dashboard WATI → API Docs)

## Logique du scénario
1. **Watch Records** sur `Ventes` — se déclenche à chaque nouvelle vente saisie dans Glide.
2. **Get a Record** sur `Produits` — récupère le `Stock_Actuel` avant décrément.
3. **Update a Record** sur `Produits` — `Stock_Actuel = Stock_Actuel - Quantite`.
4. **Filtre** : continue uniquement si `Mode_Paiement = credit`.
5. **Get a Record** sur `Clients` — récupère `Langue_Preferee`, `WhatsApp`, `Solde_Du_FCFA`.
6. **Router** à 2 branches selon `Langue_Preferee` :
   - `Français` → envoi du template WATI `rappel_credit_fr`
   - `English` → envoi du template WATI `credit_reminder_en`

## Prérequis côté WATI (à faire avant d'activer le scénario)
Créer et faire approuver par Meta 2 templates WhatsApp Business :

| Template | Langue | Contenu suggéré |
|---|---|---|
| `rappel_credit_fr` | Français | "Bonjour {{1}}, petit rappel : vous avez un solde de {{2}} FCFA chez nous. Merci de régulariser dès que possible 🙏" |
| `credit_reminder_en` | English | "Hello {{1}}, friendly reminder: you have an outstanding balance of {{2}} FCFA with us. Please settle when convenient 🙏" |

Sans approbation Meta du template, l'envoi échouera (limite du canal WhatsApp Business API, pas de Make.com).

## Limites volontaires du MVP
- Pas de retry automatique si le numéro WhatsApp est invalide (à ajouter en V2 avec un module "Error handler").
- Le scénario ne gère que les nouvelles ventes, pas les ventes modifiées/supprimées (hors scope MVP).
- Fréquence recommandée : "Instant" (webhook) si votre plan Make le permet, sinon 5 min minimum pour rester dans un plan gratuit/Core.
