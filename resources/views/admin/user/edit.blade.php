<x-admin-layout>
    {{-- Geschuetzte Demo-Zugaenge bekommen gar kein Formular. Ein Feld, das sich
         ausfuellen laesst, dessen Inhalt aber nie gespeichert wird, waere die
         schlechtere Antwort auf dieselbe Sperre. --}}
    @if ($user->isDemoProtected())
        <div class="mx-auto max-w-3xl px-3 py-3">
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900
                        dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" role="status">
                <span class="font-semibold">{{ __('Demo-Zugang – gesperrt.') }}</span>
                {{ __('Dieser Benutzer lässt sich weder ändern noch löschen. Die Zugangsdaten stehen im Hinweis-Banner; könnte man sie hier überschreiben, käme kein anderer Besucher mehr herein. Selbst angelegte Benutzer lassen sich uneingeschränkt bearbeiten.') }}
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
        <x-create.main :header="__('Benutzer bearbeiten')" :labelsubmit="__('Speichern')" action="{{ route('admin.user.update', $user) }}">
            @method('PATCH')

            @if ($user->auth_source !== 'local')
                {{-- A directory account: what the directory owns is shown, not
                     edited - it is set anew on every sign-in. The fields go
                     along hidden so the form's validation passes; the
                     controller takes only the lock from it. --}}
                <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800 dark:border-sky-800 dark:bg-sky-900/20 dark:text-sky-200">
                    {{ __('Dieses Konto kommt aus dem Active Directory. Name, Benutzername, Kennwort und Rolle verwaltet das AD – die Rolle über „Gruppe → Rolle“ unter Administration › Anmeldung (AD). Die zweite Stufe und das Sperren stellst du hier ein.') }}
                </div>
                <input type="hidden" name="name" value="{{ $user->name }}">
                <input type="hidden" name="username" value="{{ $user->username }}">
                <input type="hidden" name="email" value="{{ $user->email }}">
                <input type="hidden" name="role_id" value="{{ $user->role_id }}">
                <dl class="mb-4 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Name') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $user->name }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Benutzername') }}</dt><dd class="font-mono text-gray-900 dark:text-gray-100">{{ $user->username }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('E-Mail') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $user->email ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Rolle') }}</dt><dd class="text-gray-900 dark:text-gray-100">{{ $user->role?->name }}</dd></div>
                </dl>

                {{-- DokuVault's own: per user, the directory leaves it alone. --}}
                <x-create.zweite-stufe :checked="$user->two_factor_required" />
            @else
                <x-create.singlerow :label="__('Name')" name="name" :default="$user->name" />

                <x-create.doublerow :label1="__('Benutzername')" name1="username" :default1="$user->username" :label2="__('Passwort')" name2="password" />
                {{-- Leer lassen heisst hier "unveraendert" - die Regel gilt nur,
                     wenn tatsaechlich eines eingetragen wird. --}}
                <x-kennwortregel />

                <x-create.singlerow :label="__('E-Mail')" name="email" :default="$user->email" />

                <x-edit.select.role selector="{{ $user->role_id }}" :$roles/>

                <x-edit.select.customer selector="{{ $user->customer_id }}" :$customers/>

                <x-create.zweite-stufe :checked="$user->two_factor_required" />
            @endif

            {{-- Nur beim Bearbeiten: Ein Zugang, der gesperrt angelegt wird,
                 ergaebe keinen Sinn. --}}
            <x-create.gesperrt :checked="$user->isDisabled()" :seit="$user->deactivated_at" />
            <x-input.fehler :messages="$errors->get('deactivated')" class="mt-2" />

            {{-- Shown, not edited: the members of a team are changed in the
                 team, by its members (TeamController) - here anyone with
                 admin_user could otherwise add themselves. --}}
            @unless ($user->customer_id)
                <div class="mt-5 text-sm">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Teams') }}:</span>
                    <span class="text-gray-900 dark:text-gray-100">{{ $user->teams->pluck('name')->join(', ') ?: '—' }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Mitglieder ändert man im Team unter Admin › Teams.') }}</span>
                </div>
            @endunless

        </x-create.main>

        @if ($errors->has('invitation'))
            <div class="mx-auto mt-4 max-w-3xl px-3">
                <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/25 dark:text-red-300">
                    {{ $errors->first('invitation') }}
                </div>
            </div>
        @endif

        @if (session('status') === 'einladung-verschickt')
            <div class="mx-auto mt-4 max-w-3xl px-3">
                <div class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/25 dark:text-green-300" role="status">
                    {{ __('Einladung verschickt an') }} {{ $user->email }}
                </div>
            </div>
        @endif

        {{-- Im Spam gelandet, Link abgelaufen, versehentlich geloescht: das ist
             der Alltag, nicht der Fehler. Not for a directory account: it
             signs in with the directory's password, an invitation would set
             a DokuVault one. --}}
        @if ($user->auth_source === 'local')
        <div class="mx-auto mt-4 max-w-3xl px-3">
            <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ __('Einladung') }}</p>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        @if ($user->email)
                            {{ __('Schickt einen Link an :adresse, hinter dem sich der Benutzer ein neues Kennwort vergibt.', ['adresse' => $user->email]) }}
                        @else
                            {{ __('Ohne E-Mail-Adresse lässt sich keine Einladung verschicken.') }}
                        @endif
                    </p>

                    {{-- Der Stand der letzten Einladung: Ohne ihn sieht ein
                         Administrator nicht, ob er gerade zum zweiten Mal
                         schickt oder zum ersten Mal. --}}
                    @if ($user->invitationExpired())
                        <p class="mt-2 text-amber-700 dark:text-amber-400">
                            {{ __('Die Einladung vom :datum ist abgelaufen und wurde nicht eingelöst.', ['datum' => \App\Support\LocalTime::display($user->invited_at, 'd.m.Y')]) }}
                        </p>
                    @elseif ($user->invitationPending())
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            {{ __('Verschickt am :datum, noch nicht eingelöst.', ['datum' => \App\Support\LocalTime::display($user->invited_at, 'd.m.Y H:i')]) }}
                        </p>
                    @endif
                </div>

                @if ($user->email)
                    <form method="post" action="{{ route('admin.user.invite', $user) }}" class="shrink-0">
                        @csrf
                        <x-input.button color="gray" :label="__('Einladung senden')" />
                    </form>
                @endif
            </div>
        </div>
        @endif

        {{-- Verlorenes Telefon: sonst bliebe nur der Griff in die Datenbank. --}}
        @if ($user->hasTwoFactor())
            <div class="mx-auto mt-4 max-w-3xl px-3">
                <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ __('Zweite Stufe der Anmeldung') }}</p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">
                            {{ __('Eingeschaltet seit') }} {{ $user->two_factor_confirmed_at->translatedFormat('d.m.Y') }}.
                            {{ __('Zurücksetzen, wenn das Telefon weg ist – der Benutzer meldet sich danach wieder nur mit Kennwort an.') }}
                        </p>
                    </div>

                    <form method="post" action="{{ route('admin.user.two-factor', $user) }}" class="shrink-0">
                        @csrf
                        @method('delete')
                        <x-input.button color="gray" :label="__('Zurücksetzen')" />
                    </form>
                </div>
            </div>
        @endif

        {{-- Benutzer kennen keinen Papierkorb: Die Karte sagte trotzdem, der
             Eintrag lasse sich wiederherstellen. Wer sich darauf verlassen
             hat, hat einen Zugang endgueltig geloescht im Glauben, ihn
             zurueckholen zu koennen. --}}
        <x-deletecard action="{{ route('admin.user.destroy', [$user]) }}"
            :hinweis="__('Der Eintrag wird endgültig gelöscht und lässt sich nicht wiederherstellen.')"
            :frage="__('Endgültig löschen?')" />
    @endif
</x-admin-layout>
