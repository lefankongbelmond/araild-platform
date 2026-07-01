# Configuration Flutterwave — Abonnement 5.000 FCFA/mois

## 1. Créer le compte marchand
1. [flutterwave.com/cm](https://flutterwave.com/cm/) → **Créer un compte**.
2. Renseigner l'entité qui exploite ShopSnap CM (toi / ta société), pas les boutiques clientes individuellement — **un seul compte Flutterwave gère toutes les boutiques**, chaque paiement étant identifié par un `tx_ref` unique.
3. KYC obligatoire pour activer le mode Live : pièce d'identité du dirigeant, registre de commerce (ou déclaration d'activité si non enregistré), RIB/compte Mobile Money de règlement. Traitement généralement 24-72h.
4. Rester en **mode Test** (clés `TEST-...`) tant que le KYC n'est pas validé pour construire et tester tout le flux ci-dessous sans argent réel.

## 2. Récupérer les clés API
Dashboard → **Settings → API Keys** :
- `Public Key` (`FLWPUBK-...`) — utilisée côté lien de paiement hébergé.
- `Secret Key` (`FLWSECK-...`) — utilisée uniquement côté serveur/Make.com, jamais exposée dans Glide.
- `Encryption Key` — non nécessaire pour l'intégration "Hosted Payment Page" décrite ici (seulement pour l'intégration carte directe, hors scope MVP).

## 3. Configurer le webhook
Dashboard → **Settings → Webhooks** :
1. **Webhook URL** : l'URL du webhook Make.com (module "Custom Webhook", voir `make/scenario_webhook_flutterwave.json` — Make génère cette URL automatiquement à la création du module, à copier-coller ici).
2. **Secret Hash** : générer une chaîne aléatoire longue (ex: via un gestionnaire de mots de passe), la coller ici ET dans le module HTTP du scénario Make (comparaison de signature).
3. Enregistrer.

## 4. Créer le lien de paiement (Hosted Payment Page)
Deux options :

**Option A — Lien statique simple (le plus rapide, à faire manuellement une fois)**
Dashboard → **Payment Links** → **Create Payment Link** → Montant fixe `5000 XAF`, titre "Abonnement ShopSnap CM". Ce lien unique peut être réutilisé chaque mois pour toutes les boutiques, mais on ne peut pas savoir automatiquement QUELLE boutique a payé (le champ `tx_ref` n'est pas personnalisable). **Déconseillé** dès qu'il y a plus d'1 boutique cliente.

**Option B — Lien généré dynamiquement par boutique (recommandé, automatisé)**
Le scénario Make.com planifié (`scenario_generation_lien_paiement.json`) appelle l'API Flutterwave `POST /v3/payments` avec :
```
{
  "tx_ref": "shopsnap-{Nom_Boutique_slug}-{AAAAMM}",
  "amount": 5000,
  "currency": "XAF",
  "redirect_url": "https://votre-page-de-confirmation.example",
  "customer": { "phonenumber": "{Telephone_Gerante}", "name": "{Nom_Boutique}" },
  "customizations": { "title": "Abonnement ShopSnap CM", "description": "Mensualité 5000 FCFA" }
}
```
La réponse contient un `link` unique à usage unique → stocké dans `Abonnements.Lien_Paiement_Actuel` → envoyé par WhatsApp. Le `tx_ref` encode la boutique et le mois, ce qui permet au webhook de savoir précisément qui a payé et pour quelle échéance.

## 5. Tester avant le mode Live
1. Utiliser les [numéros de test Mobile Money Flutterwave](https://developer.flutterwave.com/docs/testing-helpers) fournis dans leur doc développeur (mode Test uniquement).
2. Déclencher un paiement test → vérifier que le webhook Make.com reçoit l'événement `charge.completed` → vérifier que `Abonnements.Statut_Abonnement` repasse à `actif` dans Airtable.
3. Ne basculer en clés Live qu'après validation KYC ET un test Test-mode réussi de bout en bout.

## 6. Sécurité
- La `Secret Key` et le `Secret Hash` ne doivent **jamais** apparaître dans Glide (qui est côté client/PWA, donc inspectable). Ils restent uniquement dans les modules Make.com.
- Toujours vérifier la transaction côté serveur avec `GET /v3/transactions/{id}/verify` avant de considérer un paiement comme confirmé — ne jamais faire confiance uniquement au `redirect_url` (falsifiable côté client).
