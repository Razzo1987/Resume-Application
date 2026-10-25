// Tema base — JavaScript senza dipendenze. Tutto il contenuto è leggibile anche senza script.

// Sprite SVG delle icone: incluso nel manifest di Vite (con hash) e usato da <x-theme-icon>
import.meta.glob('../icons/*.svg', { eager: true, query: '?url', import: 'default' });

import './behaviors.js';
