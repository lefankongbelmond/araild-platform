# Décision : Flutterwave (pas Paystack)

## Pourquoi
- **Paystack** : sa couverture pays officielle ne couvre pas clairement le Cameroun/XAF (fort au Nigeria, Ghana, Afrique du Sud, Kenya, Côte d'Ivoire, Rwanda). Aucune confirmation fiable d'un support Mobile Money camerounais natif.
- **Flutterwave** : opère officiellement au Cameroun (`flutterwave.com/cm`), avec support direct **MTN Mobile Money** et **Orange Money** en XAF, règlement local sous 24h. C'est le choix par défaut pour ce projet.

Si tu as déjà un compte marchand Paystack actif capable d'encaisser en XAF, dis-le moi et j'adapte — sinon je pars sur Flutterwave pour toute la suite.

## ⚠️ Contrainte technique importante : le "récurrent" a une limite réelle

Le brief demande un prélèvement "5.000 FCFA/mois **récurrent**". Il faut être précis sur ce que ça veut dire techniquement :

- **Carte bancaire** : Flutterwave supporte la tokenisation → un vrai débit automatique silencieux mensuel est possible (Payment Plans API).
- **Mobile Money (MTN/Orange)** : **il n'existe pas de débit automatique silencieux**. À chaque charge, l'opérateur envoie une notification push au téléphone du client, qui doit **taper son code PIN pour autoriser** la transaction. C'est une contrainte de sécurité imposée par MTN/Orange eux-mêmes, pas une limitation de Flutterwave.

**Conséquence concrète pour ShopSnap CM** (dont la cible n'a quasiment jamais de carte bancaire, seulement du Mobile Money) :
> L'abonnement ne peut pas être un vrai prélèvement automatique silencieux. Le design réaliste est : **rappel WhatsApp automatique + lien de paiement à 1 clic**, que la commerçante confirme elle-même sur son téléphone chaque mois (elle tape juste son code PIN Mobile Money, ~10 secondes). C'est la meilleure approximation possible du "récurrent" avec les moyens de paiement disponibles au Cameroun.

Tout le reste (génération du lien, rappel, vérification, mise à jour du statut, blocage de l'app si impayé) est automatisé — seule l'ultime confirmation PIN reste manuelle, côté client.

## Où vit le statut d'abonnement
Le brief limite volontairement Airtable à 4 tables **par boutique** (données métier : Produits, Clients, Ventes, Paiements_Credit). La facturation SaaS, elle, se fait au niveau de **toi en tant qu'exploitant**, à travers toutes les boutiques clientes — ce n'est pas une donnée de boutique. Elle vit donc dans une base Airtable séparée, "opérateur", décrite dans `STRUCTURE_ABONNEMENTS.md`.
