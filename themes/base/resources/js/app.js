// Tema base — JavaScript senza dipendenze. Tutto il contenuto è leggibile anche senza script.

// Sprite SVG delle icone: incluso nel manifest di Vite (con hash) e usato da <x-theme-icon>
import.meta.glob('../icons/*.svg', { eager: true, query: '?url', import: 'default' });

/**
 * Email e telefono arrivano codificati (base64 dei byte in ordine inverso) e vengono ricomposti qui:
 * nell'HTML non compaiono in chiaro.
 */
function decode(value) {
    try {
        const bytes = Uint8Array.from(atob(value), (char) => char.charCodeAt(0)).reverse();

        return new TextDecoder().decode(bytes);
    } catch {
        return null;
    }
}

document.querySelectorAll('.js-cloak').forEach((link) => {
    const href = decode(link.dataset.cloakHref ?? '');

    if (href) {
        link.href = href;
    }

    if (link.dataset.cloakText) {
        const text = decode(link.dataset.cloakText);

        if (text) {
            link.textContent = text;
        }
    }
});

// Pulsante "Stampa" (nascosto senza JavaScript)
document.querySelectorAll('.js-print').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => window.print());
});

// In stampa i dettagli chiusi (<details>) vengono aperti, poi richiusi
let openedForPrint = [];

window.addEventListener('beforeprint', () => {
    openedForPrint = [...document.querySelectorAll('details:not([open])')];
    openedForPrint.forEach((details) => {
        details.open = true;
    });
});

window.addEventListener('afterprint', () => {
    openedForPrint.forEach((details) => {
        details.open = false;
    });
    openedForPrint = [];
});

// "Torna su" compare dopo un po' di scorrimento
const backToTop = document.querySelector('.back-to-top');

if (backToTop) {
    const toggle = () => backToTop.classList.toggle('is-visible', window.scrollY > 600);

    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
}

// Menu: evidenzia la sezione che si sta leggendo
const menuLinks = [...document.querySelectorAll('.site-nav a[href^="#"]')];

if (menuLinks.length > 0 && 'IntersectionObserver' in window) {
    const linksById = new Map(menuLinks.map((link) => [decodeURIComponent(link.hash.slice(1)), link]));

    const observer = new IntersectionObserver((entries) => {
        entries
            .filter((entry) => entry.isIntersecting)
            .forEach((entry) => {
                menuLinks.forEach((link) => link.removeAttribute('aria-current'));
                linksById.get(entry.target.id)?.setAttribute('aria-current', 'true');
            });
    }, { rootMargin: '-35% 0px -60% 0px' });

    linksById.forEach((_, id) => {
        const section = document.getElementById(id);

        if (section) {
            observer.observe(section);
        }
    });
}
