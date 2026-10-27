// Base theme — dependency-free JavaScript. All content is readable without scripts too.

// SVG icon sprite: included in the Vite manifest (with hash) and used by <x-theme-icon>
import.meta.glob('../icons/*.svg', { eager: true, query: '?url', import: 'default' });

import './behaviors.js';
