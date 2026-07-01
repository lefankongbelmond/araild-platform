# ShopSnap CM — Livrables

App mobile no-code/low-code pour commerçantes camerounaises : Stock + Crédit Clients + Caisse du Jour, bilingue Français/Anglais.

## Contenu de ce dossier

| Chemin | Contenu |
|---|---|
| `airtable/STRUCTURE.md` | Structure exacte des 4 tables Airtable (champs, types, formules), version finale bilingue |
| `airtable/*.csv` | Données de test : 10 produits friperie, 5 clients endettés, 20 ventes, 4 paiements partiels |
| `make/scenario_vente_stock_whatsapp.json` | Blueprint Make.com : Nouvelle Vente → MAJ Stock → Rappel WhatsApp (FR/EN selon le client) |
| `make/README.md` | Instructions d'import et prérequis WATI (templates à faire approuver par Meta) |
| `glide/SCREENS.md` | Spec écran par écran des 4 onglets Glide (Vendre, Stock, Clients, Caisse) + réglages PWA/bilingue |
| `GUIDE_INSTALLATION.md` | Guide 5 étapes bilingue pour la commerçante + annexe technique pour l'installateur |

## Ce qui n'est PAS inclus
Un lien d'app Glide déployé n'a pas pu être généré depuis cette session : cela nécessite un compte Glide et une connexion Airtable authentifiés que je n'ai pas. `glide/SCREENS.md` contient la spec complète pour qu'un développeur (ou vous, via l'interface Glide) construise l'app en ~45 minutes sans écrire de code.

## Ordre de mise en œuvre recommandé
1. Airtable (`airtable/`) — base + données de test
2. Glide (`glide/SCREENS.md`) — connecter la base, construire les 4 écrans
3. Make.com (`make/`) — automatisation stock + WhatsApp
4. Paystack/Flutterwave — abonnement récurrent 5.000 FCFA/mois (non détaillé ici, dépend du compte marchand choisi)
5. `GUIDE_INSTALLATION.md` — remise à la commerçante
