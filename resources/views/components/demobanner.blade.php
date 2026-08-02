{{--
    Hinweis auf einer öffentlichen Demo-Instanz. Erscheint nur bei
    DEMO_MODE=true und sagt Besuchern zwei Dinge, die sie sonst raten müssten:
    dass sie alles ausprobieren dürfen, und dass ihre Eingaben nicht bleiben.
--}}
@if (config('app.demo'))
    <div class="w-full bg-amber-100 text-amber-900 text-center text-sm px-4 py-2
                dark:bg-amber-900/40 dark:text-amber-200" role="status">
        <span class="font-semibold">Demo</span> – alles darf ausprobiert werden.
        Die Daten werden <span class="font-semibold">stündlich zurückgesetzt</span>.
        @auth
            <a href="{{ route('changelog') }}" class="underline hover:no-underline">Änderungsverlauf</a>
        @else
            <br class="sm:hidden">
            Anmeldung:
            <span class="font-mono">admin</span>,
            <span class="font-mono">techniker</span>,
            <span class="font-mono">kunde-rw</span> oder
            <span class="font-mono">kunde-r</span> –
            Passwort jeweils <span class="font-mono">password</span>
        @endauth
    </div>
@endif
