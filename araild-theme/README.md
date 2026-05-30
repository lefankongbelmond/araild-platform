# ARAILD — Thème WordPress Institutionnel

Thème officiel premium de l'ONG **ARAILD** (Action pour la Recherche et l'Appui aux Initiatives Locales de Développement), ONG camerounaise créée en 2018 et enregistrée en mai 2019.

## Caractéristiques

- Design system violet / magenta (variables CSS)
- Polices Poppins (titres) + Inter (corps)
- Page d'accueil 100 % éditable bloc par bloc via le Personnalisateur
- 8 types de contenu personnalisés (Membres, Partenaires, Projets, Documents, Résultats, Axes, Valeurs, FAQ)
- 20+ modèles de pages prêts à l'emploi (axes, vision, valeurs, équipe, résultats, ODD, contact, etc.)
- Création automatique des pages, menus et contenu de démonstration à l'activation
- Formulaires contact + soutien sécurisés (nonces, sanitisation, wp_mail)
- 100 % responsive mobile-first
- Sécurisé : échappement des sorties, nonces, capacités, text domain `araild`

## Installation

1. Compressez le dossier `araild-theme` en `.zip` (ou téléversez `araild-theme.zip`).
2. WordPress → **Apparence → Thèmes → Ajouter → Téléverser un thème**.
3. Installez puis **Activez**. Les pages, le menu et le contenu de démo sont créés automatiquement.
4. Personnalisez via **Apparence → Personnaliser → ARAILD — Paramètres du site**.

## Configuration requise

- WordPress 6.0+
- PHP 8.0+

## Structure

- `front-page.php` — accueil modulaire
- `page-*.php` — modèles de pages institutionnelles
- `functions.php` — CPT, méta-boxes, Personnalisateur, démo, formulaires
- `assets/js/araild.js` — interactions (vanilla JS, sans jQuery côté front)

Voir **GUIDE-ADMIN.md** pour le guide d'administration pas à pas.

## Licence

GPL v2 ou ultérieure.
