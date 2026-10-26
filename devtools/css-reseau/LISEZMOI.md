# CSS de l'administration du réseau (`css/mx-network.css`)

Depuis le 02/11/2026, l'espace super-admin n'utilise plus le Tailwind CDN : sa feuille de style est compilée.
Après toute modification des vues `resources/views/network/**` ou de `components/layouts/network.blade.php`, recompiler :

```
npx tailwindcss@3.4.17 -c devtools/css-reseau/tailwind.config.js -i devtools/css-reseau/in.css -o production/css/mx-network.css --minify
```

Les classes construites dynamiquement (`border-{{ $c }}/30`…) sont couvertes par la `safelist`.
