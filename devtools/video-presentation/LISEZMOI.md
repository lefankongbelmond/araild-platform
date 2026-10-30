# Vidéo de présentation de l'application (refaire après un changement d'écran)

Enregistre la vraie application sur la copie fidèle du site (`devtools/copie-lws`), puis monte une vidéo verticale 1080×1920 avec cadre de téléphone, titres d'étapes et sous-titres.

1. Copie LWS démarrée ; `scratchpad/nav/data.json` (page et projet de démonstration) présent à côté du dossier de travail (chemin `../nav/data.json`).
2. Supprimer le compte de démonstration s'il existe (`aminata.fouda@exemple.cm`).
3. `node record.js` → `raw.mp4` + `marks.json` (début de chaque scène). La capture se fait image par image en haute définition (CDP screencast).
4. `node cards.js` → `frame.png` (fond + cadre, fenêtre transparente), `intro.png`, `outro.png`.
5. `python3 -I build.py` → `final.mp4`, `mxconnect-sous-titres.srt`, `timeline.json`. La narration (dans `STEPS`) fixe la durée de chaque scène (2,35 mots/s) ; la pause se place juste avant la dernière action de la scène.

Pas de voix de synthèse ici : le texte est fait pour être enregistré par une voix humaine (voir `livraisons/video/TEXTE-NARRATION-*.md`).
