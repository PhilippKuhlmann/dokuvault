<aside id="logo-sidebar"
        class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full text-gray-900 bg-white border-r border-gray-200 sm:translate-x-0 dark:bg-gray-900 dark:border-gray-700"
        aria-label="Sidebar">
        <div class="h-full px-3 pb-4 overflow-y-auto bg-white dark:bg-gray-900">
            <ul class="space-y-2">

            <x-aside.dropdown label="Benutzer" svg="svg.user" >
                <x-slot:links>
                    <x-aside.dropdownlink label="Benutzer" href="{{ route('admin.user.index') }}" />
                    <x-aside.dropdownlink label="Rollen" href="{{ route('admin.role.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

            <x-aside.dropdown label="Kunden" svg="svg.office" >
                <x-slot:links>
                    <x-aside.dropdownlink label="Kunden" href="{{ route('admin.customer.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

            <x-aside.dropdown label="Auswahlmenüs" svg="svg.computer" >
                <x-slot:links>
                    <x-aside.dropdownlink label="Betriebsysteme" href="{{ route('admin.operatingsystem.index') }}" />
                    <x-aside.dropdownlink label="Mail Anbieter" href="{{ route('admin.mailboxprovider.index') }}" />
                    <x-aside.dropdownlink label="Rack-Katalog" href="{{ route('admin.rackcatalogitem.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

            <x-aside.dropdown label="Protokoll" svg="svg.document" >
                <x-slot:links>
                    <x-aside.dropdownlink label="Aktivitäten" href="{{ route('admin.activity.index') }}" />
                </x-slot:links>
            </x-aside.dropdown>

        </ul>
    </div>
</aside>




