{{--
    Hinweis auf einer öffentlichen Demo-Instanz. Erscheint nur bei
    DEMO_MODE=true und sagt Besuchern zwei Dinge, die sie sonst raten müssten:
    dass sie alles ausprobieren dürfen, und dass ihre Eingaben nicht bleiben.

    Farben bewusst deckend, nicht als Lasur: Die Login-Seite gibt dem body
    keinen Hintergrund, der dunkle Grund kommt erst aus dem Slot darunter.
    Eine halbtransparente Fläche würde sich hier mit Weiß mischen und im
    Dunkelmodus beige aussehen.
--}}
@if (config('app.demo'))
    <div role="status"
        class="w-full border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900
               dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center">

            <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-950
                         dark:bg-amber-400 dark:text-amber-950">Demo</span>

            <span>Alles darf ausprobiert werden – die Daten werden stündlich zurückgesetzt.</span>

            @guest
                <span class="text-amber-800/80 dark:text-gray-400">
                    Anmeldung
                    <span class="font-mono text-amber-900 dark:text-gray-200">admin</span>,
                    <span class="font-mono text-amber-900 dark:text-gray-200">techniker</span>,
                    <span class="font-mono text-amber-900 dark:text-gray-200">kunde-rw</span> oder
                    <span class="font-mono text-amber-900 dark:text-gray-200">kunde-r</span> –
                    Passwort <span class="font-mono text-amber-900 dark:text-gray-200">password</span>
                </span>
            @else
                <a href="{{ route('changelog') }}"
                    class="underline underline-offset-2 hover:no-underline">Änderungsverlauf</a>
            @endguest

        </div>
    </div>
@endif
