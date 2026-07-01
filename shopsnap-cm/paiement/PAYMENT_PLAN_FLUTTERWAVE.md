# Configuration du Payment Plan Flutterwave — détail

## ⚠️ À lire avant de configurer : ce que "Payment Plan" fait réellement

Le "Payment Plan" Flutterwave est un **auto-débit par tokenisation de carte bancaire**. Confirmé par la doc Flutterwave elle-même : après le premier paiement, c'est **la carte** qui est débitée automatiquement à chaque échéance, et un **email** de rappel est envoyé avant chaque charge (pas de WhatsApp, pas de FR/EN configurable — c'est un template Flutterwave fixe en anglais).

**Le Mobile Money (MTN/Orange) n'est pas éligible à ce mécanisme** : il n'y a pas de token à stocker, chaque charge exige une autorisation PIN fraîche du client (cf. `DECISION.md`). Créer un Payment Plan ne changera donc rien pour une commerçante qui paie en Mobile Money — elle continuera de recevoir le lien + rappel WhatsApp qu'on a déjà mis en place (`GUIDE_CONFIG_FLUTTERWAVE.md` + les 2 scénarios Make).

**Où le Payment Plan devient utile ici** : si une minorité de gérantes (diaspora payant pour une boutique au pays, ou commerçante ayant une carte Visa/Mastercard locale) préfère régler par carte, le Payment Plan leur évite de repayer manuellement chaque mois — un vrai auto-debit silencieux, pour elles uniquement. Je détaille la config ci-dessous comme option secondaire, en parallèle du flux Mobile Money qui reste le flux principal.

---

## 1. Créer le plan (une seule fois, pas par boutique)

### Dashboard
Flutterwave Dashboard → **Payments → Payment Plans → Create Plan** :
- **Name** : `Abonnement ShopSnap CM Mensuel`
- **Amount** : `5000`
- **Currency** : `XAF`
- **Interval** : `Monthly`
- **Duration** : laisser vide / `0` → illimité (continue jusqu'à annulation), sinon un nombre de cycles fixe

### Ou via API (plus cohérent avec l'automatisation Make déjà en place)
```
POST https://api.flutterwave.com/v3/payment-plans
Authorization: Bearer YOUR_FLUTTERWAVE_SECRET_KEY
Content-Type: application/json

{
  "amount": 5000,
  "name": "Abonnement ShopSnap CM Mensuel",
  "interval": "monthly",
  "duration": 0,
  "currency": "XAF"
}
```
Réponse : `data.id` = l'identifiant du plan (à noter une bonne fois pour toutes, ex. `12345`) et `data.plan_token`.

Intervalles acceptés : `daily`, `weekly`, `monthly`, `quarterly`, `yearly` (ou `biannually`/`annually` selon la version — vérifier dans le dashboard au moment de la création, la doc a varié dans le temps).

## 2. Souscrire une gérante au plan (au moment de son tout premier paiement par carte)

Sur son tout premier paiement, ajouter `payment_plan` au payload de création de paiement (le même appel `POST /v3/payments` que dans `GUIDE_CONFIG_FLUTTERWAVE.md`, section Option B) :
```
{
  "tx_ref": "shopsnap-{ID enregistrement Airtable de la ligne Abonnements}-{AAAAMM}",
  "amount": 5000,
  "currency": "XAF",
  "payment_plan": 12345,
  "redirect_url": "https://votre-page-de-confirmation.example",
  "customer": { "email": "...", "phonenumber": "{Telephone_Gerante}", "name": "{Nom_Boutique}" },
  "customizations": { "title": "Abonnement ShopSnap CM", "description": "Mensualite 5000 FCFA" }
}
```
Important : `payment_plan` ne prend effet que si la gérante règle **par carte** sur la page de paiement hébergée (elle doit choisir l'onglet Carte, pas Mobile Money). C'est le seul moyen de la faire basculer sur l'auto-debit silencieux.

## 3. Suivre et gérer les abonnements carte actifs
- `GET https://api.flutterwave.com/v3/subscriptions` — liste tous les abonnements actifs liés à un plan (email client, `id` de la souscription, date de prochaine charge).
- `PUT https://api.flutterwave.com/v3/subscriptions/{id}/cancel` — annule l'abonnement **d'une seule cliente**, sans toucher aux autres. C'est cet endpoint qu'il faut utiliser en cas de résiliation individuelle, pas l'annulation du plan.
- `PUT https://api.flutterwave.com/v3/payment-plans/{id}/cancel` — annule **le plan entier** (arrête l'auto-debit pour toutes les clientes qui y sont abonnées). À réserver à un arrêt total de cette offre, pas à une résiliation individuelle.

## 4. Ce qu'il faut ajouter à la table `Abonnements`
Un seul champ suffit, ajouté en option à `STRUCTURE_ABONNEMENTS.md` :

| Champ | Type | Règle |
|---|---|---|
| `Flutterwave_Subscription_ID` | Single line text | Rempli uniquement si la gérante a payé par carte et est abonnée au Payment Plan. Vide pour les clientes Mobile Money (immense majorité). Sert à appeler `PUT /subscriptions/{id}/cancel` en cas de résiliation. |

Pas besoin de `Flutterwave_Plan_ID` par ligne : c'est le même plan (`12345`) pour toutes, une constante à coder une fois dans le scénario Make, pas une donnée par boutique.

## 5. Webhook : un événement différent pour les charges automatiques
Le scénario `make/scenario_webhook_flutterwave.json` existant fonctionne tel quel pour les charges automatiques du Payment Plan : Flutterwave envoie le même événement `charge.completed` que pour un paiement par lien classique, avec le même `tx_ref` que celui fourni à la souscription. Aucune modification du scénario n'est nécessaire — le filtre "montant + devise corrects" et la mise à jour `Statut_Abonnement = actif` s'appliquent identiquement.

## 6. Recommandation
Ne pas pousser cette option en avant dans l'app Glide/le guide commerçante — la présenter uniquement si une gérante demande explicitement à payer par carte. Le flux par défaut, pour ~100% des utilisatrices visées (boutiques, friperies, Yaoundé/Douala), reste **lien Mobile Money généré chaque mois + rappel WhatsApp**, déjà en place.
