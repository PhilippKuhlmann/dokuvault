<aside id="logo-sidebar"
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full text-gray-900 bg-white border-r border-gray-200 sm:translate-x-0 dark:bg-gray-900 dark:border-gray-700"
        aria-label="Sidebar">
        <div class="h-full px-3 pb-4 overflow-y-auto bg-white dark:bg-gray-900">
            <ul class="space-y-2">

            <x-aside.dropdown :label="__('Benutzer')" svg="svg.user" >
                <x-slot:links>
                    <x-aside.dropdownlink :label="__('Benutzer')" href="{{ route('admin.user.index') }}" />
                    <x-aside.dropdownlink :label="__('Rollen')" href="{{ route('admin.role.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

            <x-aside.dropdown :label="__('Kunden')" svg="svg.office" >
                <x-slot:links>
                    <x-aside.dropdownlink :label="__('Kunden')" href="{{ route('admin.customer.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

            <x-aside.dropdown :label="__('Auswahlmenüs')" svg="svg.computer" >
                <x-slot:links>
                    <x-aside.dropdownlink :label="__('Betriebsysteme')" href="{{ route('admin.operatingsystem.index') }}" />
                    <x-aside.dropdownlink :label="__('Mail Anbieter')" href="{{ route('admin.mailboxprovider.index') }}" />
                    <x-aside.dropdownlink :label="__('Rack-Katalog')" href="{{ route('admin.rackcatalogitem.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

            <x-aside.dropdown :label="__('Protokoll')" svg="svg.document" >
                <x-slot:links>
                    <x-aside.dropdownlink :label="__('Aktivitäten')" href="{{ route('admin.activity.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

        </ul>
    </div>
</aside>




