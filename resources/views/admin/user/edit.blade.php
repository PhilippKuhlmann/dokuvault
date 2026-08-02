<x-admin-layout>
    {{-- Geschuetzte Demo-Zugaenge bekommen gar kein Formular. Ein Feld, das sich
         ausfuellen laesst, dessen Inhalt aber nie gespeichert wird, waere die
         schlechtere Antwort auf dieselbe Sperre. --}}
    @if ($user->istDemoGeschuetzt())
        <div class="mx-auto max-w-3xl px-3 py-3">
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900
                        dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" role="status">
                <span class="font-semibold">Demo-Zugang – gesperrt.</span>
                Dieser Benutzer lässt sich weder ändern noch löschen. Die Zugangsdaten stehen im
                Hinweis-Banner; könnte man sie hier überschreiben, käme kein anderer Besucher mehr
                herein. Selbst angelegte Benutzer lassen sich uneingeschränkt bearbeiten.
            </div>

            <dl class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white
                       dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                @foreach ([
                    'Name' => $user->name,
                    'Benutzername' => $user->username,
                    'E-Mail' => $user->email,
                    'Rolle' => $user->role?->name,
                    'Kunde' => $user->customer?->name ?? '—',
                ] as $bezeichnung => $wert)
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400 sm:w-40 sm:shrink-0">{{ $bezeichnung }}</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $wert }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @else
        <x-create.main header="Benutzer bearbeiten" labelsubmit="Speichern" action="{{ route('admin.user.update', $user) }}">
            @method('PATCH')

            <x-create.singlerow label="Name" name="name" :default="$user->name" />

            <x-create.doublerow label1="Benutzername" name1="username" :default1="$user->username" label2="Passwort" name2="password" />

            <x-create.singlerow label="E-Mail" name="email" :default="$user->email" />

            <x-edit.select.role selector="{{ $user->role_id }}" :$roles/>

            <x-edit.select.customer selector="{{ $user->customer_id }}" :$customers/>

        </x-create.main>

        <x-deletecard action="{{ route('admin.user.destroy', [$user]) }}" />
    @endif
</x-admin-layout>
