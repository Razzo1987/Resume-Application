// Comportamenti comuni del sito (tema base e temi che lo estendono): contatti codificati, stampa,
// "torna su", voce di menu della sezione in lettura. Nessuna dipendenza, nessun contenuto nascosto.

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

// Menu: evidenzia la sezione che si sta leggendo (l'ultima il cui titolo ha superato un terzo dello schermo).
// In fondo alla pagina, dove le ultime sezioni non possono salire fin lassù, vale quella aperta dal menu
// (#indirizzo) se è visibile, altrimenti l'ultima visibile.
const menuLinks = [...document.querySelectorAll('.site-nav a[href^="#"]')];
const menuSections = menuLinks
    .map((link) => [link, document.getElementById(decodeURIComponent(link.hash.slice(1)))])
    .filter(([, section]) => section);

if (menuSections.length > 0) {
    let scheduled = false;

    const update = () => {
        scheduled = false;
        const line = window.innerHeight / 3;
        const atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2;
        const visible = ([, section]) => {
            const box = section.getBoundingClientRect();

            return box.top < window.innerHeight && box.bottom > 0;
        };

        let current = null;

        for (const entry of menuSections) {
            if (entry[1].getBoundingClientRect().top <= line) {
                current = entry;
            }
        }

        if (atBottom) {
            current = menuSections.find(([link, section]) => link.hash === window.location.hash && visible([link, section]))
                ?? menuSections.filter(visible).pop()
                ?? current;
        }

        menuLinks.forEach((link) => {
            if (link === current?.[0]) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    const schedule = () => {
        if (!scheduled) {
            scheduled = true;
            requestAnimationFrame(update);
        }
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('hashchange', schedule);
    update();
}
