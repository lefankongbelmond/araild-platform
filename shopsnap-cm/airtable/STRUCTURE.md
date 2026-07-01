# Structure Airtable — ShopSnap CM (version finale, bilingue FR/EN)

Cette version corrige 2 points par rapport aux premiers échanges :
1. `Categorie` et `Mode_Paiement` utilisent des **codes neutres** (traduits côté Glide selon la langue de l'utilisateur), pour éviter la double saisie en français et anglais.
2. `Date_Vente` et `Date_Paiement` sont des champs **Date éditables** (et non `Created time`). En mode hors-ligne, une vente peut être *saisie* un jour et *synchronisée* le lendemain — `Created time` capturerait la date de synchro, pas la date réelle de la vente. Glide doit pré-remplir ce champ avec "Now" au moment de la saisie, pas au moment de la synchro serveur.

Import : voir les fichiers `.csv` dans ce dossier. Après import, convertir les colonnes `Produit` et `Client` en **Link to another record** (Airtable propose "Convert field type" et matche automatiquement par le nom si le texte correspond exactement au champ primaire de la table liée).

---

## 1. `Produits`

| Champ | Type | Formule / Règle |
|---|---|---|
| `Nom_Produit` | Single line text *(primaire)* | |
| `Code_Barre` | Single line text | Optionnel |
| `Categorie` | Single select | Codes : `friperie_homme`, `friperie_femme`, `friperie_enfant`, `cosmetique`, `alimentation`, `autre` — traduits en FR/EN dans Glide |
| `Prix_Achat_FCFA` | Number (entier) | |
| `Prix_Vente_FCFA` | Number (entier) | |
| `Stock_Actuel` | Number (entier) | Éditable : +manuel (réappro), -auto (Make.com à chaque vente) |
| `Seuil_Min` | Number (entier) | |
| `Alerte_Stock` | Formula | `IF({Stock_Actuel} <= {Seuil_Min}, "🔴", "🟢")` — le texte "Stock faible / Low stock" est ajouté par Glide |
| `Marge_Unitaire_FCFA` | Formula | `{Prix_Vente_FCFA} - {Prix_Achat_FCFA}` |
| `Valeur_Stock_FCFA` | Formula | `{Stock_Actuel} * {Prix_Vente_FCFA}` |
| `Photo` | Attachment | Optionnel |
| `Actif` | Checkbox | Défaut coché |
| `Date_Ajout` | Created time | Auto |
| `Ventes` | Link to `Ventes` | Auto (lien inverse) |

## 2. `Clients`

| Champ | Type | Formule / Règle |
|---|---|---|
| `Nom_Client` | Single line text *(primaire)* | |
| `Telephone` | Phone number | Sert aussi à l'OTP de connexion |
| `WhatsApp` | Phone number | Souvent = Telephone |
| `Langue_Preferee` | Single select | `Français`, `English` — défaut `Français`, choisit le template WATI |
| `Ventes_Credit` | Link to `Ventes` | Auto (lien inverse) |
| `Paiements` | Link to `Paiements_Credit` | Auto (lien inverse) |
| `Total_Achats_Credit_FCFA` | Rollup | `SUM` de `Ventes_Credit.Montant_Credit_FCFA` |
| `Total_Paiements_FCFA` | Rollup | `SUM` de `Paiements.Montant_Paye_FCFA` |
| `Solde_Du_FCFA` | Formula | `{Total_Achats_Credit_FCFA} - {Total_Paiements_FCFA}` |
| `Statut_Solde` | Formula | `IF({Solde_Du_FCFA} > 0, "🟠", "✅")` |
| `Dernier_Paiement_Date` | Rollup | `MAX` de `Paiements.Date_Paiement` |
| `Date_Creation` | Created time | Auto |

## 3. `Ventes`

| Champ | Type | Formule / Règle |
|---|---|---|
| `Vente_ID` | Autonumber *(primaire)* | |
| `Date_Vente` | **Date** (pas Created time) | Pré-rempli "Now" par Glide à la saisie |
| `Produit` | Link to `Produits` | |
| `Quantite` | Number (entier) | |
| `Prix_Unitaire_FCFA` | Lookup | Depuis `Produit.Prix_Vente_FCFA` |
| `Montant_Total_FCFA` | Formula | `{Quantite} * {Prix_Unitaire_FCFA}` |
| `Mode_Paiement` | Single select | Codes : `cash`, `mobile_money`, `credit` |
| `Client` | Link to `Clients` | Requis seulement si `Mode_Paiement = credit` |
| `Montant_Credit_FCFA` | Formula | `IF({Mode_Paiement} = "credit", {Montant_Total_FCFA}, 0)` |
| `Statut_Paiement` | Formula | `IF({Mode_Paiement} = "credit", "🟠", "✅")` |
| `Vendeur` | Created by | Auto |
| `Notes` | Long text | Optionnel |

## 4. `Paiements_Credit`

| Champ | Type | Formule / Règle |
|---|---|---|
| `Paiement_ID` | Autonumber *(primaire)* | |
| `Client` | Link to `Clients` | Requis |
| `Date_Paiement` | **Date** (pas Created time) | Pré-rempli "Now" par Glide à la saisie |
| `Montant_Paye_FCFA` | Number (entier) | |
| `Mode_Paiement` | Single select | `cash`, `mobile_money` |
| `Note` | Long text | Optionnel |
| `Enregistre_Par` | Created by | Auto |

---

## Données de test incluses

- `produits.csv` — 10 articles friperie (2 volontairement sous le seuil pour tester l'alerte 🔴 : *Chaussures baskets homme*, *Manteau hiver enfant*)
- `clients.csv` — 5 clients (3 FR, 2 EN pour tester le bilinguisme des rappels WhatsApp)
- `ventes.csv` — 20 ventes de juin 2026, mix cash / mobile_money / credit
- `paiements_credit.csv` — 4 paiements partiels (Grace Achu n'a rien payé, elle reste endettée)

Soldes attendus après import et calcul des rollups :
| Client | Solde dû |
|---|---|
| Marie Ngo Bell | 10 000 FCFA |
| Paul Eto'o Mballa | 4 500 FCFA |
| Aissatou Bello | 6 000 FCFA |
| Jean-Pierre Fotso | 7 000 FCFA |
| Grace Achu | 4 500 FCFA |
