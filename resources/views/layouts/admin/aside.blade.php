<aside id="logo-sidebar"
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full text-gray-900 bg-white border-r border-gray-200 lg:translate-x-0 dark:bg-gray-900 dark:border-gray-700"
        aria-label="Sidebar">
        <div class="h-full px-3 pb-4 overflow-y-auto bg-white dark:bg-gray-900">
            <ul class="space-y-2">

            <x-aside.toplink :label="__('Dashboard')" svg="svg.home" :href="route('admin.dashboard')" />

            {{-- Zwei Bereiche unter einem Menuepunkt: Wer nur Rollen pflegen
                 darf, sieht den Punkt, aber nur die eine Zeile darin. --}}
            @canany(['admin_user', 'admin_role'])
                <x-aside.dropdown :label="__('Benutzer')" svg="svg.user" >
                    <x-slot:links>
                        @can('admin_user')
                            <x-aside.dropdownlink :label="__('Benutzer')" href="{{ route('admin.user.index') }}" />
                        @endcan
                        @can('admin_role')
                            <x-aside.dropdownlink :label="__('Rollen')" href="{{ route('admin.role.index') }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @can('admin_customer')
                <x-aside.dropdown :label="__('Kunden')" svg="svg.office" >
                    <x-slot:links>
                        <x-aside.dropdownlink :label="__('Kunden')" href="{{ route('admin.customer.index') }}" />
                    </x-slot:links>
                </x-aside.dropdown>
            @endcan

            @can('admin_catalog')
                <x-aside.dropdown :label="__('Auswahlmenüs')" svg="svg.computer" >
                    <x-slot:links>
                        <x-aside.dropdownlink :label="__('Betriebssysteme')" href="{{ route('admin.operatingsystem.index') }}" />
                        <x-aside.dropdownlink :label="__('Mail Anbieter')" href="{{ route('admin.mailboxprovider.index') }}" />
                        <x-aside.dropdownlink :label="__('Dienste')" href="{{ route('admin.service.index') }}" />
                        <x-aside.dropdownlink :label="__('Rack-Katalog')" href="{{ route('admin.rackcatalogitem.index') }}" />
                    </x-slot:links>
                </x-aside.dropdown>
            @endcan

            @can('admin_operatingsystem')
                <x-aside.dropdown :label="__('Betriebssysteme')" svg="svg.servers" >
                    <x-slot:links>
                        <x-aside.dropdownlink :label="__('Support-Ende (EOL)')" href="{{ route('admin.eol.index') }}" />
                    </x-slot:links>
                </x-aside.dropdown>
            @endcan

            @canany(['admin_setting', 'admin_activity', 'admin_apitoken'])
                <x-aside.dropdown :label="__('Einstellungen')" svg="svg.settings" >
                    <x-slot:links>
                        @can('admin_setting')
                            <x-aside.dropdownlink :label="__('Allgemein')" href="{{ route('admin.general.index') }}" />
                            <x-aside.dropdownlink :label="__('Fernwartung')" href="{{ route('admin.setting.index') }}" />
                        @endcan
                        {{-- Die Aufbewahrungsfrist gehoert zum Protokoll, nicht zu
                             den uebrigen Einstellungen. --}}
                        @can('admin_activity')
                            <x-aside.dropdownlink :label="__('Protokoll-Historie')" href="{{ route('admin.logretention') }}" />
                        @endcan
                        @can('admin_apitoken')
                            <x-aside.dropdownlink :label="__('API-Token')" href="{{ route('admin.apitoken') }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @can('admin_trash')
                <x-aside.dropdown :label="__('Papierkorb')" svg="svg.trash" >
                    <x-slot:links>
                        <x-aside.dropdownlink :label="__('Alle Kunden')" href="{{ route('admin.trash') }}" />
                    </x-slot:links>
                </x-aside.dropdown>
            @endcan

            @can('admin_activity')
                <x-aside.dropdown :label="__('Protokoll')" svg="svg.document" >
                    <x-slot:links>
                        <x-aside.dropdownlink :label="__('Aktivitäten')" href="{{ route('admin.activity.index') }}" />
                    </x-slot:links>
                </x-aside.dropdown>
            @endcan

        </ul>
    </div>
</aside>




