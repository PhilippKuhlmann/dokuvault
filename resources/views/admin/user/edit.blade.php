<x-admin-layout>
    @if ($user->istDemoGeschuetzt())
        <div class="mx-auto max-w-3xl px-3 pt-3">
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900
                        dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" role="status">
                <span class="font-semibold">Demo-Zugang.</span>
                Passwort und Rolle sind gesperrt, und der Benutzer lässt sich nicht löschen –
                sonst könnten sich die übrigen Besucher nicht mehr anmelden. Selbst angelegte
                Benutzer lassen sich uneingeschränkt bearbeiten.
            </div>
        </div>
    @endif

    <x-create.main header="Benutzer bearbeiten" labelsubmit="Speichern" action="{{ route('admin.user.update', $user) }}">
        @method('PATCH')

        <x-create.singlerow label="Name" name="name" :default="$user->name" />

        <x-create.doublerow label1="Benutzername" name1="username" :default1="$user->username" label2="Passwort" name2="password" />

        <x-create.singlerow label="E-Mail" name="email" :default="$user->email" />

        <x-edit.select.role selector="{{ $user->role_id }}" :$roles/>

        <x-edit.select.customer selector="{{ $user->customer_id }}" :$customers/>

    </x-create.main>

    {{-- Loeschen-Karte entfaellt bei geschuetzten Demo-Zugaengen. Der Controller
         lehnt den Aufruf ohnehin ab, aber ein Knopf, der nichts tut, verwirrt. --}}
    @unless ($user->istDemoGeschuetzt())
        <x-deletecard action="{{ route('admin.user.destroy', [$user]) }}" />
    @endunless
</x-admin-layout>
