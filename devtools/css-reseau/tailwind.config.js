// Chemin du dossier de l'application (à adapter).
const P = __dirname + '/../../production/mxconnect';
module.exports = {
  content: [P + '/resources/views/network/**/*.blade.php', P + '/resources/views/components/layouts/network.blade.php',
    P + '/vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php'],
  safelist: [{ pattern: /^(bg|text|border)-(pine|sage|clay|ink|green|navy|mist)(\/(5|10|15|20|30|40|50|60|70))?$/ }],
  theme: { extend: {
    colors: {
      ink: '#0B1F3A', pine: '#0A5FB5', navy: { DEFAULT: '#0B2559', deep: '#061A3A' }, 'navy-deep': '#061A3A',
      sage: '#4F992F', green: '#4F992F', mist: '#EAF6EE', clay: '#C0392B', paper: '#F5F7FA',
      leaf: { 50: '#EAF6EE', 100: '#D4EEDC', 300: '#7BD389', 500: '#2E9E4F', 600: '#23853F', 700: '#1C6B33' },
    },
    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'] },
  }},
};
