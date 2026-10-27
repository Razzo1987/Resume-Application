// Common site behaviors (base theme and the themes that extend it): encoded contacts, print,
// "back to top", menu item of the section being read. No dependencies, no hidden content.

/**
 * Email and phone arrive encoded (base64 of the bytes in reverse order) and are decoded here:
 * they never appear in plain text in the HTML.
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

// External links (other domains) open in a new tab; screen readers announce it (#new-tab-hint)
document.querySelectorAll('a[href]').forEach((link) => {
    if (!/^https?:$/.test(link.protocol) || link.host === window.location.host || link.target) {
        return;
    }

    link.target = '_blank';
    link.relList.add('noopener');

    if (document.getElementById('new-tab-hint')) {
        const described = (link.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean);
        link.setAttribute('aria-describedby', [...new Set([...described, 'new-tab-hint'])].join(' '));
    }
});

// "Print" button (hidden without JavaScript)
document.querySelectorAll('.js-print').forEach((button) => {
    button.hidden = false;
    button.addEventListener('click', () => window.print());
});

// When printing, closed <details> are opened, then closed again
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

// "Back to top" appears after some scrolling
const backToTop = document.querySelector('.back-to-top');

if (backToTop) {
    const toggle = () => backToTop.classList.toggle('is-visible', window.scrollY > 600);

    window.addEventListener('scroll', toggle, { passive: true });
    toggle();
}

// Menu: highlights the section being read (the last one whose title has passed a third of the screen).
// At the bottom of the page, where the last sections cannot scroll that high, the one opened from the menu
// (#hash) wins if it is visible, otherwise the last visible one.
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
