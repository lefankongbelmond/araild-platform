# Table Airtable "opérateur" — `Abonnements`

Base séparée de celle des boutiques (une seule base `ShopSnap_CM_Admin`, une ligne par boutique cliente). Sert à piloter la facturation SaaS et à couper l'accès en cas d'impayé.

| Champ | Type | Formule / Règle |
|---|---|---|
| `Nom_Boutique` | Single line text *(primaire)* | |
| `Nom_Gerante` | Single line text | |
| `Telephone_Gerante` | Phone number | |
| `WhatsApp_Gerante` | Phone number | |
| `Langue_Preferee` | Single select | `Français`, `English` |
| `Airtable_Base_ID` | Single line text | Base de données de cette boutique (les 4 tables métier) |
| `Glide_App_URL` | URL | |
| `Montant_FCFA` | Number | Défaut `5000` |
| `Statut_Abonnement` | Single select | `actif`, `en_retard`, `suspendu`, `resilie` |
| `Date_Debut` | Date | |
| `Date_Dernier_Paiement` | Date | Mise à jour par le scénario webhook |
| `Date_Prochaine_Echeance` | Formula | `DATEADD({Date_Dernier_Paiement}, 30, 'days')` |
| `Jours_Avant_Echeance` | Formula | `DATETIME_DIFF({Date_Prochaine_Echeance}, TODAY(), 'days')` |
| `Lien_Paiement_Actuel` | URL | Régénéré à chaque cycle par le scénario "génération lien" |
| `Dernier_TX_REF` | Single line text | Référence de transaction Flutterwave, pour éviter les doublons de webhook |
| `Flutterwave_Subscription_ID` | Single line text | Optionnel — rempli seulement si la gérante paie par carte via le Payment Plan (voir `PAYMENT_PLAN_FLUTTERWAVE.md`). Vide pour les clientes Mobile Money. |
| `Notes` | Long text | Optionnel |

## Règles d'automatisation (voir `make/`)
1. **Tous les jours**, un scénario planifié cherche les lignes où `Jours_Avant_Echeance <= 3` et `Statut_Abonnement = actif` → génère un nouveau lien de paiement Flutterwave → envoie un rappel WhatsApp (FR/EN).
2. Si `Jours_Avant_Echeance < 0` (échéance dépassée) → `Statut_Abonnement = en_retard`, un 2ᵉ rappel plus insistant part.
3. Si `Jours_Avant_Echeance < -5` sans paiement → `Statut_Abonnement = suspendu`.
4. Le webhook Flutterwave, à la confirmation d'un paiement, remet `Statut_Abonnement = actif` et met à jour `Date_Dernier_Paiement` + `Dernier_TX_REF`.

## Côté Glide (dans l'app de chaque boutique)
Ajouter une **2ᵉ source de données** dans l'app Glide : la base `ShopSnap_CM_Admin`, filtrée sur la ligne correspondant à cette boutique (`Nom_Boutique` ou un ID stocké dans le User Profile à la connexion).
- Si `Statut_Abonnement = suspendu` → afficher un écran plein écran bloquant "Abonnement expiré" avec le `Lien_Paiement_Actuel` en gros bouton, et masquer les 4 onglets métier.
- Sinon → app normale.
