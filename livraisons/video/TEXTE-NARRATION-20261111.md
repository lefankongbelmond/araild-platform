# Vidéo de présentation MX-CONNECT — texte de la narration

Vidéo : `mxconnect-presentation-20261111.mp4` (format vertical 1080×1920, 2 min 55, sous-titres incrustés).
La vidéo est livrée **sans voix**. Ce texte permet de l’enregistrer avec une voix féminine camerounaise / africaine.

## Conseils d’enregistrement
- Un téléphone suffit : pièce calme, téléphone à 20 cm de la bouche, application « Dictaphone ».
- Lire posément, avec le sourire. Chaque scène a un temps indiqué : il suffit de commencer la phrase au début de la scène.
- Le plus simple : enregistrer **en une seule fois** en regardant la vidéo (sans le son), puis envoyer le fichier audio. Je l’assemble avec la vidéo.
- Les guillemets « … » indiquent le nom d’un bouton à l’écran.

## Texte, scène par scène

| Début | Étape | Texte à lire |
|---|---|---|
| 0:00.0 | Ouverture | Bienvenue sur MX-CONNECT, le réseau qui relie les mutuelles de santé, les organisations et les citoyens engagés. Je vous montre comment utiliser l’application, pas à pas. |
| 0:12.0 | 1. Ouvrir l’application | Touchez l’icône MX-CONNECT sur votre téléphone. L’application s’ouvre et vous connecte au réseau. |
| 0:18.4 | 2. Bienvenue | À la première ouverture, trois écrans présentent l’essentiel : découvrir, vous engager et collaborer. Touchez « Suivant », ou « Passer ». |
| 0:28.7 | 3. Créer un compte | Pour créer votre compte, touchez « Créer un compte ». Indiquez votre nom, votre prénom et votre adresse e-mail ; le téléphone est facultatif. Choisissez un mot de passe, cochez la politique de confidentialité, puis touchez « Créer mon compte ». |
| 0:47.0 | 4. Accepter les conditions | Lisez les règles de la communauté, cochez la case et touchez « Continuer ». Rien n’est jamais coché à votre place. |
| 0:56.8 | 5. Votre compte est prêt | Votre compte est prêt ! Touchez « Commencer maintenant ». |
| 1:02.0 | 6. L’accueil | Voici l’accueil. En haut, la recherche et les domaines : santé, protection sociale, éducation… Faites défiler pour voir les actualités. L’en-tête se cache quand vous descendez et revient quand vous remontez. |
| 1:16.1 | 7. Publier | Pour publier, touchez le bouton vert « Publier ». Écrivez votre message, ajoutez une photo ou une vidéo si vous le souhaitez, puis publiez. |
| 1:27.2 | 8. Découvrir | L’onglet « Découvrir » rassemble les organisations, les projets, les événements et les opportunités. Les filtres vous aident à trouver ce qui se passe près de chez vous. |
| 1:40.0 | 9. Suivre une organisation | Sur la page d’une organisation, touchez « Suivre » pour recevoir ses actualités dans votre fil. |
| 1:47.7 | 10. Projets et résultats | Chaque projet présente son avancement, ses bénéficiaires et ses résultats. Les résultats vérifiés ont été contrôlés par le réseau. |
| 1:57.0 | 11. Je m’engage | Pour aider, touchez « Je m’engage » : choisissez votre forme de contribution, indiquez vos disponibilités et écrivez un message. L’organisation vous répondra. |
| 2:07.7 | 12. Soutenir un projet | Vous représentez une entreprise ou une association ? Touchez « Soutenir » pour proposer un partenariat. MX-CONNECT ne collecte jamais d’argent. |
| 2:17.5 | 13. Messages et notifications | L’onglet « Messages » vous permet d’écrire aux mutuelles, et la cloche affiche vos notifications. |
| 2:24.8 | 14. Mon profil | Touchez « Profil » : vous y retrouvez vos publications, vos organisations, vos groupes, vos opportunités et vos paramètres. |
| 2:33.8 | 15. Inviter un ami | Pour faire grandir la communauté, touchez « Inviter un ami » et partagez le lien par WhatsApp, Facebook ou SMS. |
| 2:43.2 | 16. Centre d’aide | Une question ? Le Centre d’aide est là pour vous. |
| 2:48.7 | Fin | MX-CONNECT : connecter, collaborer, réussir. À très bientôt sur le réseau ! |

## Assembler la voix et la vidéo (si vous le faites vous-même)
Avec le logiciel gratuit ffmpeg :

```
ffmpeg -i mxconnect-presentation-20261111.mp4 -i voix.m4a -map 0:v -map 1:a -c:v copy -c:a aac -b:a 128k -shortest mxconnect-presentation-voix.mp4
```

Ou avec CapCut / InShot sur téléphone : importer la vidéo, ajouter l’audio enregistré, exporter en 1080p.

Les sous-titres sont aussi fournis séparément (`mxconnect-presentation-20261111.srt`) pour YouTube ou Facebook.
