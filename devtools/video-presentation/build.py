# Montage de la vidéo de présentation MX-CONNECT (1080×1920) : scènes réelles + cadre + titres + sous-titres.
import json, os, re, subprocess, sys
D = os.path.dirname(os.path.abspath(__file__)); os.chdir(D)
M = json.load(open('marks.json')); raw = M['video']
RATE = 2.35   # mots par seconde (lecture posée)
STEPS = [
 ('intro', None, "Bienvenue sur MX-CONNECT, le réseau qui relie les mutuelles de santé, les organisations et les citoyens engagés. Je vous montre comment utiliser l’application, pas à pas."),
 ('ouvrir', "Ouvrir l’application", "Touchez l’icône MX-CONNECT sur votre téléphone. L’application s’ouvre et vous connecte au réseau."),
 ('bienvenue', "Bienvenue", "À la première ouverture, trois écrans présentent l’essentiel : découvrir, vous engager et collaborer. Touchez « Suivant », ou « Passer »."),
 ('inscription', "Créer un compte", "Pour créer votre compte, touchez « Créer un compte ». Indiquez votre nom, votre prénom et votre adresse e-mail ; le téléphone est facultatif. Choisissez un mot de passe, cochez la politique de confidentialité, puis touchez « Créer mon compte »."),
 ('conditions', "Accepter les conditions", "Lisez les règles de la communauté, cochez la case et touchez « Continuer ». Rien n’est jamais coché à votre place."),
 ('pret', "Votre compte est prêt", "Votre compte est prêt ! Touchez « Commencer maintenant »."),
 ('accueil', "L’accueil", "Voici l’accueil. En haut, la recherche et les domaines : santé, protection sociale, éducation… Faites défiler pour voir les actualités. L’en-tête se cache quand vous descendez et revient quand vous remontez."),
 ('publier', "Publier", "Pour publier, touchez le bouton vert « Publier ». Écrivez votre message, ajoutez une photo ou une vidéo si vous le souhaitez, puis publiez."),
 ('decouvrir', "Découvrir", "L’onglet « Découvrir » rassemble les organisations, les projets, les événements et les opportunités. Les filtres vous aident à trouver ce qui se passe près de chez vous."),
 ('organisation', "Suivre une organisation", "Sur la page d’une organisation, touchez « Suivre » pour recevoir ses actualités dans votre fil."),
 ('projet', "Projets et résultats", "Chaque projet présente son avancement, ses bénéficiaires et ses résultats. Les résultats vérifiés ont été contrôlés par le réseau."),
 ('engagement', "Je m’engage", "Pour aider, touchez « Je m’engage » : choisissez votre forme de contribution, indiquez vos disponibilités et écrivez un message. L’organisation vous répondra."),
 ('soutien', "Soutenir un projet", "Vous représentez une entreprise ou une association ? Touchez « Soutenir » pour proposer un partenariat. MX-CONNECT ne collecte jamais d’argent."),
 ('messages', "Messages et notifications", "L’onglet « Messages » vous permet d’écrire aux mutuelles, et la cloche affiche vos notifications."),
 ('profil', "Mon profil", "Touchez « Profil » : vous y retrouvez vos publications, vos organisations, vos groupes, vos opportunités et vos paramètres."),
 ('inviter', "Inviter un ami", "Pour faire grandir la communauté, touchez « Inviter un ami » et partagez le lien par WhatsApp, Facebook ou SMS."),
 ('aide', "Centre d’aide", "Une question ? Le Centre d’aide est là pour vous."),
 ('outro', None, "MX-CONNECT : connecter, collaborer, réussir. À très bientôt sur le réseau !"),
]
F0 = M.get('first', 0.0); marks = dict((k, max(0.0, t - F0)) for k, t in M['marks']); M['end'] = M['end'] - F0; order = [k for k, _ in M['marks']]
def need(text): return len(text.split()) / RATE + 0.9
def run(cmd): subprocess.run(cmd, check=True)
os.makedirs('clips', exist_ok=True)
timeline = []; t = 0.0; clips = []
for i, (key, title, text) in enumerate(STEPS):
    if key in ('intro', 'outro'):
        dur = max(5.0, need(text)); img = key + '.png'; out = f'clips/{i:02d}.mp4'
        fade = f"fade=in:0:15,fade=out:st={dur-0.5}:d=0.5" if key == 'outro' else f"fade=in:0:12"
        run(['ffmpeg','-v','error','-y','-loop','1','-t',f'{dur}','-i',img,'-vf',f"scale=1080:1920,{fade},format=yuv420p",'-r','30','-c:v','libx264','-preset','medium','-crf','20',out])
        clips.append(out); timeline.append((key, title, text, t, dur)); t += dur; continue
    a = marks[key]; b = marks[order[order.index(key) + 1]] if order.index(key) + 1 < len(order) else M['end']
    span = b - a; dur = max(span, need(text)); pad = dur - span; out = f'clips/{i:02d}.mp4'
    base = "fps=30,scale=600:1298:flags=lanczos"
    post = "pad=1080:1920:240:250:color=black[pv];[pv][1]overlay=0:0,format=yuv420p"
    cut = span - 1.3   # pause AVANT la dernière action de la scène (pas sur l'écran suivant)
    if pad > 0.05 and cut > 1.0:
        fc = (f"[0]{base},split[x][y];[x]trim=0:{cut:.2f},setpts=PTS-STARTPTS,tpad=stop_mode=clone:stop_duration={pad:.2f}[p1];"
              f"[y]trim={cut:.2f}:{span:.2f},setpts=PTS-STARTPTS[p2];[p1][p2]concat=n=2:v=1,{post}")
    else:
        fc = f"[0]{base},tpad=stop_mode=clone:stop_duration={max(pad,0):.2f},{post}"
    run(['ffmpeg','-v','error','-y','-ss',f'{a:.2f}','-t',f'{span:.2f}','-i',raw,'-i','frame.png','-filter_complex',fc,'-r','30','-c:v','libx264','-preset','medium','-crf','20',out])
    clips.append(out); timeline.append((key, title, text, t, dur)); t += dur
open('clips/list.txt','w').write(''.join(f"file '{os.path.basename(c)}'\n" for c in clips))
run(['ffmpeg','-v','error','-y','-f','concat','-safe','0','-i','clips/list.txt','-c','copy','silent.mp4'])
# Sous-titres (narration) + titres d'étape
def ts(x): h = int(x // 3600); m = int(x % 3600 // 60); s = x % 60; return f"{h}:{m:02d}:{s:05.2f}"
def srt(x): ms = int(round(x * 1000)); return f"{ms//3600000:02d}:{ms//60000%60:02d}:{ms//1000%60:02d},{ms%1000:03d}"
def chunks(text):
    parts = re.split(r'(?<=[.!?…])\s+', text); out = []
    for p in parts:
        if len(p) > 95 and ';' in p: out += [s.strip() + (';' if j == 0 else '') for j, s in enumerate(p.split(';'))]
        elif len(p) > 95 and ':' in p: x, y = p.split(':', 1); out += [x.strip() + ' :', y.strip()]
        else: out.append(p)
    return [o for o in out if o]
ass = ["[Script Info]","ScriptType: v4.00+","PlayResX: 1080","PlayResY: 1920","WrapStyle: 0","",
 "[V4+ Styles]","Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding",
 "Style: Sub,Inter,44,&H00FFFFFF,&H00FFFFFF,&H00591E0B,&H7A0B1A07,1,0,0,0,100,100,0,0,3,14,0,2,70,70,64,1",
 "Style: Step,Inter,40,&H00FFFFFF,&H00FFFFFF,&H004F9E2E,&H004F9E2E,1,0,0,0,100,100,0,0,3,12,0,8,60,60,178,1",
 "","[Events]","Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text"]
srt_lines = []; n = 0; script = []; step_no = 0
for key, title, text, start, dur in timeline:
    if title:
        step_no += 1; ass.append(f"Dialogue: 0,{ts(start)},{ts(start+dur)},Step,,0,0,0,,{step_no} · {title}")
    cs = chunks(text); total = sum(len(c) for c in cs); s = start + 0.35; win = dur - 0.5
    script.append((start, title or ('Ouverture' if key == 'intro' else 'Fin'), text))
    for c in cs:
        d = win * len(c) / total; e = s + d
        ass.append(f"Dialogue: 0,{ts(s)},{ts(e)},Sub,,0,0,0,,{c}"); n += 1
        srt_lines.append(f"{n}\n{srt(s)} --> {srt(e)}\n{c}\n"); s = e
open('mxconnect.ass','w').write('\n'.join(ass) + '\n'); open('mxconnect-sous-titres.srt','w').write('\n'.join(srt_lines))
run(['ffmpeg','-v','error','-y','-i','silent.mp4','-vf','ass=mxconnect.ass','-c:v','libx264','-preset','medium','-crf','21','-pix_fmt','yuv420p','-movflags','+faststart',
     '-f','lavfi','-i' if False else '-an','final-sans-voix.mp4'] if False else
    ['ffmpeg','-v','error','-y','-i','silent.mp4','-f','lavfi','-t',f'{t:.2f}','-i','anullsrc=r=44100:cl=stereo','-vf','ass=mxconnect.ass','-map','0:v','-map','1:a',
     '-c:v','libx264','-preset','medium','-crf','21','-pix_fmt','yuv420p','-c:a','aac','-b:a','96k','-shortest','-movflags','+faststart','final.mp4'])
json.dump([dict(cle=k, titre=ti, texte=tx, debut=round(st, 2), duree=round(du, 2)) for k, ti, tx, st, du in timeline], open('timeline.json','w'), ensure_ascii=False, indent=1)
print(f'{t:.1f} s', len(clips), 'scènes')
