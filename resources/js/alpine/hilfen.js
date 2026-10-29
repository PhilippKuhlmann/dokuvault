/*
 * Small helpers for Alpine expressions, as magics ($name).
 *
 * Alpine runs in its CSP build (no 'unsafe-eval'): an expression in the HTML
 * is one JavaScript expression, without arrow functions, statements, template
 * strings or access to window/document. Whatever needs more lives here, and
 * the attribute only calls it - for example x-on:click="$kopieren($refs.pw.value)".
 */

const ERSTES_FELD = 'input:not([type=hidden]):not([type=search]), select:not([aria-hidden=true]), textarea, [role=combobox]';

export function hilfenRegistrieren(Alpine) {
    // Copies the text and shows the tick for a moment: sets the flag of the
    // surrounding x-data ("copied" unless named otherwise) and resets it.
    Alpine.magic('kopieren', (el) => (text, flag = 'copied', ms = 1500) => {
        window.copyText(String(text ?? ''));
        const daten = Alpine.$data(el);
        daten[flag] = true;
        setTimeout(() => { daten[flag] = false; }, ms);
    });

    // Sets a value of the surrounding x-data after a while - hides a toast.
    Alpine.magic('nachher', (el) => (ms, schluessel, wert) => {
        const daten = Alpine.$data(el);
        setTimeout(() => { daten[schluessel] = wert; }, ms);
    });

    // Focuses the first input field inside the element, once it is drawn.
    Alpine.magic('fokusErstesFeld', (el) => (selektor = ERSTES_FELD) => {
        Alpine.nextTick(() => el.querySelector(selektor)?.focus());
    });

    // Focuses an element once it is drawn - pass it as $refs.name; that also
    // finds elements an x-teleport moved out of the component.
    Alpine.magic('fokus', () => (ziel) => {
        Alpine.nextTick(() => ziel?.focus());
    });

    // Tooltip at the viewport: remembers where the element sits (x, y) and
    // opens it - position: fixed escapes every scrolling frame.
    Alpine.magic('tooltipZeigen', (el) => () => {
        const r = el.getBoundingClientRect();
        const daten = Alpine.$data(el);
        daten.x = r.left + r.width / 2;
        daten.y = r.top;
        daten.offen = true;
    });

    // Role matrix: a group checkbox sets all checkboxes of its block, a row
    // switch flips the row (all on, unless all are on already).
    Alpine.magic('hakenUebernehmen', (el) => (block) => {
        const an = el.checked;
        el.closest(block).querySelectorAll('.perm-cb').forEach((c) => { c.checked = an; });
    });
    Alpine.magic('zeileUmschalten', (el) => () => {
        const haken = [...el.closest('tr').querySelectorAll('.perm-cb')];
        const alle = haken.every((c) => c.checked);
        haken.forEach((c) => { c.checked = ! alle; });
    });

    // Preview of a service tile: background colour and the font colour that
    // follows from its brightness.
    Alpine.magic('kachelStil', () => (hex) => {
        if (! /^#[0-9a-fA-F]{6}$/.test(hex ?? '')) {
            return 'background-color:#e5e7eb;color:#111827';
        }
        const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16) / 255);
        const hell = 0.299 * r + 0.587 * g + 0.114 * b;

        return 'background-color:' + hex + ';color:' + (hell > 0.6 ? '#111827' : '#ffffff');
    });

    // Offers a text as a file download (agent scripts).
    Alpine.magic('herunterladen', () => (text, typ, dateiname) => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([text], { type: typ }));
        a.download = dateiname;
        a.click();
        URL.revokeObjectURL(a.href);
    });

    // Back where the user came from, otherwise to the start page.
    Alpine.magic('zurueck', () => () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.assign('/');
        }
    });

    // Wizard: empties the form after saving, and on a new step focuses the
    // first field and scrolls to the top.
    Alpine.magic('felderLeeren', (el) => () => {
        Alpine.nextTick(() => el.querySelectorAll('input, textarea, select').forEach((feld) => { feld.value = ''; }));
    });
    Alpine.magic('schrittZeigen', (el) => () => {
        Alpine.nextTick(() => {
            el.querySelector('input:not([type=hidden]):not([type=search]), [role=combobox]')?.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Jumped in from the global search: scroll to the highlighted entry and
    // flash it briefly.
    //
    // Tables: the marker sits on the table wrapper with the row number - a div
    // between tbody and tr is no valid HTML, the browser moved it out of the
    // table, empty, and nothing lit up. A row takes no box-shadow in every
    // browser, so it gets a tint instead.
    //
    // Cards: the wrapper div has no box of its own - the visible card is the
    // inner panel (with its own margin and rounding). Ring the inner element so
    // the frame hugs the card and follows its corner radius.
    Alpine.magic('treffer', (el) => () => {
        Alpine.nextTick(() => {
            const w = el.querySelector('[data-highlight]');
            if (! w) return;

            if (w.dataset.highlightRow !== undefined) {
                const row = w.querySelector('tbody')?.rows[w.dataset.highlightRow];
                if (! row) return;
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row.style.transition = 'background-color .3s';
                row.style.backgroundColor = 'rgb(56 152 236 / .2)';
                setTimeout(() => { row.style.backgroundColor = ''; }, 2200);

                return;
            }

            const target = w.firstElementChild || w;
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.style.transition = 'box-shadow .3s';
            target.style.boxShadow = '0 0 0 3px rgb(56 152 236)';
            setTimeout(() => { target.style.boxShadow = ''; }, 2200);
        });
    });
}
