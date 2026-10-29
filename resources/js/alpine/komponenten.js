/*
 * Alpine components with logic of their own, as Alpine.data.
 *
 * They used to stand as object literals in x-data. Alpine's CSP build cannot
 * read methods there (no 'unsafe-eval'), so they live here and the view only
 * names them: x-data="befehlspalette(...)". Values from PHP come in as
 * arguments, written with @alpineJson - plain JSON, which the CSP parser
 * reads; @js would write JSON.parse(...), and the global JSON is off limits.
 */

// Preview address of a chosen image file, null without a file.
function bildvorschau(event) {
    const datei = event.target.files[0];

    return datei ? URL.createObjectURL(datei) : null;
}

export function komponentenRegistrieren(Alpine) {
    // Success toast at the bottom right: from session('success') after a
    // normal request, or from the browser event "hinweis" of Livewire.
    Alpine.data('erfolgsmeldung', (show = false, text = '') => ({
        show,
        text,
        timer: null,
        init() {
            if (this.show) {
                this.timer = setTimeout(() => { this.show = false; }, 4000);
            }
        },
        zeigen(nachricht) {
            this.text = nachricht;
            this.show = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.show = false; }, 4000);
        },
    }));

    // Page layout: whether the side menu is folded away, kept per browser.
    Alpine.data('seitenlayout', () => ({
        menuEin: (() => {
            try { return localStorage.getItem('menu-eingeklappt') === '1'; } catch (e) { return false; }
        })(),
        menuUmschalten() {
            this.menuEin = ! this.menuEin;
            try { localStorage.setItem('menu-eingeklappt', this.menuEin ? '1' : '0'); } catch (e) { /* ignore */ }
        },
    }));

    // Admin: device model and rack catalog item - report the choice to the
    // rack preview next to the form.
    Alpine.data('geraetemodell', (typ, he, drawing, zeichnungen) => ({
        typ,
        he,
        drawing,
        neuesBild: null,
        weg: false,
        zeichnungen,
        bildWaehlen(event) { this.neuesBild = bildvorschau(event); },
        melden() {
            this.$dispatch('rack-vorschau', {
                appearance: this.zeichnungen[this.typ], he: this.he,
                drawing: this.drawing,
            });
        },
    }));
    Alpine.data('katalogelement', (appearance, he) => ({
        appearance,
        he,
        neuesBild: null,
        bildWaehlen(event) { this.neuesBild = bildvorschau(event); },
        melden() {
            this.$dispatch('rack-vorschau', { appearance: this.appearance, he: this.he });
        },
    }));

    // Keyboard navigation of a search result list: arrow keys move the active
    // result across all groups (flat DOM order via [data-result]), Enter opens it.
    Alpine.data('trefferliste', () => ({
        active: 0,
        items() { return [...this.$root.querySelectorAll('[data-result]')]; },
        move(step) {
            const list = this.items();
            if (! list.length) return;
            this.active = Math.max(0, Math.min(this.active + step, list.length - 1));
            list[this.active]?.scrollIntoView({ block: 'nearest' });
        },
        choose() { this.items()[this.active]?.click(); },
    }));

    // Services of a server: chosen tiles, catalog styles, and in a modal the
    // Livewire property every change goes to directly.
    Alpine.data('dienstauswahl', (gewaehlt, stile, wireModel = null) => ({
        gewaehlt,
        neu: '',
        stile,
        stil(name) {
            return this.stile[name.toLowerCase()] ?? '';
        },
        hat(name) {
            return this.gewaehlt.some((d) => d.toLowerCase() === name.toLowerCase());
        },
        dazu(name) {
            name = (name || '').trim();
            if (name && ! this.hat(name)) {
                this.gewaehlt.push(name);
            }
            this.neu = '';
            this.melden();
        },
        weg(name) {
            this.gewaehlt = this.gewaehlt.filter((d) => d !== name);
            this.melden();
        },
        melden() {
            if (wireModel) {
                this.$wire.set(wireModel, this.gewaehlt.join(','));
            }
        },
    }));

    // "/" jumps into the search of the page (without one it opens the command
    // palette), "?" opens the list of shortcuts - not while typing in a field.
    Alpine.data('tastenkuerzel', () => ({
        helpOpen: false,
        typing(e) {
            const t = e.target;
            return t instanceof Element && (t.closest('input, textarea, select') !== null || t.isContentEditable);
        },
        onKey(e) {
            if (e.metaKey || e.ctrlKey || e.altKey || this.typing(e)) return;
            if (e.key === '/') {
                e.preventDefault();
                const feld = document.querySelector('input[type=search]');
                if (feld) { feld.focus(); } else { window.dispatchEvent(new CustomEvent('palette-open')); }
            } else if (e.key === '?') {
                e.preventDefault();
                this.helpOpen = true;
            }
        },
    }));

    // File upload: takes the name from the chosen file and shows its size.
    Alpine.data('dateiupload', () => ({
        dateiname: '',
        groesse: null,
        uebernehmen(feld) {
            const datei = feld.files[0];
            if (! datei) return;
            this.groesse = datei.size;
            if (! this.dateiname) {
                this.dateiname = datei.name.replace(/\.[^.]+$/, '');
            }
        },
        lesbar() {
            if (this.groesse === null) return '';
            const e = ['B', 'KB', 'MB', 'GB'];
            let w = this.groesse;
            let i = 0;
            while (w >= 1024 && i < e.length - 1) { w /= 1024; i++; }

            return (i === 0 ? w : w.toFixed(1).replace('.', ',')) + ' ' + e[i];
        },
    }));

    // Command palette (Cmd/Ctrl + K): jump to any page the user may open.
    Alpine.data('befehlspalette', (ziele) => ({
        offen: false,
        suche: '',
        aktiv: 0,
        ziele,
        gefiltert() {
            const q = this.suche.trim().toLowerCase();
            const liste = q ? this.ziele.filter((z) => z.label.toLowerCase().includes(q)) : this.ziele;
            if (this.aktiv >= liste.length) this.aktiv = Math.max(0, liste.length - 1);

            return liste;
        },
        oeffnen() {
            this.offen = true;
            this.suche = '';
            this.aktiv = 0;
            this.$nextTick(() => this.$refs.feld && this.$refs.feld.focus());
        },
        schliessen() { this.offen = false; },
        runter() { const n = this.gefiltert().length; if (n) this.aktiv = (this.aktiv + 1) % n; },
        hoch() { const n = this.gefiltert().length; if (n) this.aktiv = (this.aktiv - 1 + n) % n; },
        waehlen() { const z = this.gefiltert()[this.aktiv]; if (z) window.location = z.url; },
        taste(e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.oeffnen(); }
        },
    }));
}

export function rackRegistrieren(Alpine) {
    // Rack editor: devices from the palette and installed items are dragged
    // onto a free height unit; the preview shows the units it would take.
    Alpine.data('rackeditor', (rackHeight) => ({
        drag: null, // { kind, he, ... } - what is being dragged
        hover: null, // lowest height unit under the pointer
        rackHeight,

        span() { return this.drag?.he ?? 1; },

        // Drag start from the palette or the rack. The element carries its
        // data in data-* attributes (catalog id, item id, height units).
        ziehen(kind, el, extra = {}) {
            this.drag = {
                kind,
                id: Number(el.dataset.catalogId ?? el.dataset.itemId ?? el.dataset.deviceId),
                he: Number(el.dataset.he ?? 1),
                ...extra,
            };
        },
        loslassen() {
            this.drag = null;
            this.hover = null;
        },
        ueber(el) { this.hover = Number(el.dataset.unit); },
        verlassen(event, el) {
            if (! el.contains(event.relatedTarget)) this.hover = null;
        },
        ablegen(el) { this.handleDrop(Number(el.dataset.unit)); },

        // Read the layout from the DOM every time. A plan baked into x-data
        // once would be stale after every Livewire change (e.g. more height
        // units) and the preview would lie.
        placed() {
            return [...this.$root.querySelectorAll('[data-item-id]')].map((el) => ({
                id: Number(el.dataset.itemId),
                unit: Number(el.dataset.unit),
                he: Number(el.dataset.he),
            }));
        },

        // Does the dragged item fit at the position under the pointer?
        fits() {
            if (! this.drag || this.hover === null) return false;
            const top = this.hover + this.span() - 1;
            if (this.hover < 1 || top > this.rackHeight) return false;
            // When moving, its own height units do not count as taken.
            const self = this.drag.kind === 'move' ? this.drag.id : null;

            return ! this.placed().some((i) => i.id !== self && this.hover <= i.unit + i.he - 1 && top >= i.unit);
        },

        // The preview covers exactly the units that would be taken - cut off at the top of the rack.
        previewStyle() {
            if (this.hover === null) return {};
            const top = Math.min(this.hover + this.span() - 1, this.rackHeight);
            const rows = Math.max(1, top - this.hover + 1);

            return { gridColumn: '2', gridRow: `${this.rackHeight - top + 1} / span ${rows}` };
        },

        previewLabel() {
            if (! this.drag || this.hover === null) return '';
            const top = this.hover + this.span() - 1;
            const range = this.span() > 1 ? `U${this.hover}–U${top}` : `U${this.hover}`;

            return this.fits() ? range : `${range} · kein Platz`;
        },

        handleDrop(position) {
            if (! this.drag) return;
            if (this.drag.kind === 'device') this.$wire.placeDevice(this.drag.type, this.drag.id, position);
            if (this.drag.kind === 'catalog') this.$wire.placeCatalog(this.drag.id, position);
            if (this.drag.kind === 'move') this.$wire.move(this.drag.id, position);
            this.loslassen();
        },
    }));
}

// What x-input.select and x-input.mehrfachauswahl share: a list at the
// viewport (position: fixed escapes every scrolling frame of cards, modals
// and tables), arrow-key movement and Escape that closes only the list.
// Plain methods, no getters - spreading an object would copy a getter's value.
function listenfeld(mitSuche) {
    return {
        offen: false,
        suche: '',
        markiert: -1,
        x: 0,
        y: 0,
        breite: 0,
        nachOben: false,
        mitSuche,
        platzieren() {
            const r = this.$refs.ausloeser.getBoundingClientRect();
            const platzUnten = window.innerHeight - r.bottom;
            this.x = r.left;
            this.breite = r.width;
            // Open upwards when there is no room below - otherwise the list
            // hangs below the edge of the screen.
            this.nachOben = platzUnten < 300 && r.top > platzUnten;
            this.y = this.nachOben ? window.innerHeight - r.top : r.bottom;
        },
        // At least as wide as the field, but growing: in narrow table columns
        // long names would otherwise wrap onto three lines.
        listenStil() {
            return `left: ${this.x}px; min-width: ${this.breite}px; `
                + (this.nachOben ? `bottom: ${this.y + 4}px` : `top: ${this.y + 4}px`);
        },
        neuPlatzieren() {
            if (this.offen) this.platzieren();
        },
        escapeTaste(event) {
            if (! this.offen) return;
            event.stopPropagation();
            this.schliessen();
        },
        schliessen() {
            this.offen = false;
            this.$refs.ausloeser.focus();
        },
        bewegen(schritte) {
            const anzahl = this.gefiltert.length;
            if (! anzahl) {
                this.markiert = -1;

                return;
            }
            this.markiert = (this.markiert + schritte + anzahl) % anzahl;
            this.$nextTick(() => this.$refs.liste
                ?.querySelector('[data-markiert]')
                ?.scrollIntoView({ block: 'nearest' }));
        },
        filtern(optionen) {
            const suche = this.suche.trim().toLowerCase();

            return suche ? optionen.filter((o) => o.text.toLowerCase().includes(suche)) : optionen;
        },
    };
}

export function auswahlRegistrieren(Alpine) {
    // x-input.select: the native <select> stays (name, value, binding), hidden;
    // visible is a trigger with a list of its own and, from twelve entries,
    // a search that finds a word anywhere in the name. modell is the bound
    // Livewire property or null for classic forms.
    Alpine.data('auswahlfeld', (mitSuche, modell = null) => ({
        ...listenfeld(mitSuche),
        optionen: [],
        eigenerWert: '',
        init() {
            this.lesen();
            // Livewire also swaps option lists on a re-render - the switches of
            // a patch panel, say. A list remembered once at init would be stale.
            new MutationObserver(() => this.lesen())
                .observe(this.$refs.select, { childList: true, subtree: true });
        },
        lesen() {
            this.optionen = Array.from(this.$refs.select.options).map((o) => ({ wert: o.value, text: o.text }));
            if (! modell) {
                this.eigenerWert = this.$refs.select.value;
            }
        },
        // From $wire with Livewire: a modal is reused for "new" and "edit",
        // Livewire only swaps the content and init() would not run again.
        get wert() {
            return modell ? String(this.$wire.get(modell) ?? '') : this.eigenerWert;
        },
        get gefiltert() {
            return this.filtern(this.optionen);
        },
        get beschriftung() {
            return this.optionen.find((o) => o.wert === this.wert)?.text ?? '';
        },
        oeffnen() {
            if (this.offen) return;
            this.offen = true;
            this.suche = '';
            this.markiert = this.gefiltert.findIndex((o) => o.wert === this.wert);
            this.platzieren();
            // Only with a search field the focus moves; without, it stays on
            // the trigger, which handles the arrow keys itself.
            if (this.mitSuche) {
                this.$nextTick(() => this.$refs.suchfeld?.focus());
            }
        },
        enterTaste() {
            if (this.offen && this.gefiltert[this.markiert]) {
                this.waehle(this.gefiltert[this.markiert].wert);
            } else {
                this.oeffnen();
            }
        },
        enterImSuchfeld() {
            if (this.gefiltert[this.markiert]) this.waehle(this.gefiltert[this.markiert].wert);
        },
        waehle(wert) {
            this.$refs.select.value = wert;
            if (modell) {
                this.$wire.set(modell, wert);
            } else {
                this.eigenerWert = wert;
                // 'change', not 'input': the onchange handlers of the classic
                // forms listen to it. With 'input' the choice looks right but
                // arrives nowhere.
                this.$refs.select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.schliessen();
        },
    }));

    // x-input.mehrfachauswahl: several entries on a Livewire array property.
    // A click toggles an entry and leaves the list open.
    Alpine.data('mehrfachauswahl', (optionen, mitSuche, modell) => ({
        ...listenfeld(mitSuche),
        optionen,
        get werte() {
            return Array.from(this.$wire.get(modell) ?? []).map(String);
        },
        get gefiltert() {
            return this.filtern(this.optionen);
        },
        get beschriftung() {
            return this.optionen.filter((o) => this.werte.includes(o.wert)).map((o) => o.text).join(', ');
        },
        oeffnen() {
            if (this.offen) return;
            this.offen = true;
            this.suche = '';
            this.markiert = this.gefiltert.length ? 0 : -1;
            this.platzieren();
            if (this.mitSuche) {
                this.$nextTick(() => this.$refs.suchfeld?.focus());
            }
        },
        enterTaste() {
            if (this.offen && this.gefiltert[this.markiert]) {
                this.umschalten(this.gefiltert[this.markiert].wert);
            } else {
                this.oeffnen();
            }
        },
        enterImSuchfeld() {
            if (this.gefiltert[this.markiert]) this.umschalten(this.gefiltert[this.markiert].wert);
        },
        // A click on an entry must not take the focus from the trigger: the
        // focus fell to <body>, Escape no longer reached this component but
        // the dialog - and closed the whole form with what was typed.
        mausDown(event) {
            if (event.target !== this.$refs.suchfeld) event.preventDefault();
        },
        umschalten(wert) {
            const werte = this.werte;
            this.$wire.set(modell, werte.includes(wert) ? werte.filter((w) => w !== wert) : [...werte, wert]);
        },
    }));
}
