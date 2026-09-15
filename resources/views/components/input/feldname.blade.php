{{--
    Feldbeschriftung auf den Gast-Seiten: Versalien auf Monospace, wie die
    Portlisten und der IP-Plan im Rest der Anwendung.

    Eigene Komponente und kein zusaetzliches class= an x-input.label: Deren
    Klassen (text-sm, text-gray-900) stuenden mit diesen im selben
    Klassenattribut, und welche gewinnt, entscheidet dann die Reihenfolge im
    gebauten CSS - nicht die im Blade.
--}}
@props(['value' => null])

<label {{ $attributes->merge(['class' => 'block font-mono text-[11px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400']) }}>
    {{ $value ?? $slot }}
</label>
