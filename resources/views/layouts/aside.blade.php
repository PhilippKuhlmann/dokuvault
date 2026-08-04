<aside id="logo-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full text-gray-900 bg-white border-r border-gray-200 sm:translate-x-0 dark:bg-gray-900 dark:border-gray-700" aria-label="Sidebar">
    <div class="flex justify-between flex-col h-full px-3 pb-4 overflow-y-auto bg-white dark:bg-gray-900">
        <ul class="space-y-1">

            <form method="post" action="{{ route('filter.site', $customer) }}" class="pb-3 mb-2 border-b border-gray-200 dark:border-gray-700">
                @csrf
                <div class="flex flex-col">
                    <x-input.label :value="__('Standort')" />
                    <x-input.select name="site" class="w-full mt-1" onchange="this.form.submit()">
                        <option value="all">{{ __('Alle') }}</option>
                        @foreach ($customer->sites as $site)
                            <option value="{{ $site->id }}"
                                {{ $site->id == session()->get('site') ? 'selected' : '' }}>{{ $site->name }}
                            </option>
                        @endforeach
                    </x-input.select>
                </div>
            </form>

            @canany(['site_viewAny', 'contactperson_viewAny'])
                <x-aside.dropdown :label="__('Kunde')" svg="svg.office">
                    <x-slot:links>
                        @can('site_viewAny')
                            <x-aside.dropdownlink :label="__('Standort')" href="{{ route('site.index', $customer) }}" />
                        @endcan
                        @can('contactperson_viewAny')
                            <x-aside.dropdownlink :label="__('Ansprechpartner')" href="{{ route('contactperson.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['securepointutm_viewAny', 'router_viewAny', 'network_viewAny', 'wifi_viewAny', 'networkswitch_viewAny', 'accesspoint_viewAny', 'internetconnection_viewAny', 'rack_viewAny', 'patchpanel_viewAny'])
                <x-aside.dropdown :label="__('Netzwerk')" svg="svg.wifi">
                    <x-slot:links>
                        @can('internetconnection_viewAny')
                            <x-aside.dropdownlink :label="__('Internet / WAN')" href="{{ route('internetconnection.index', $customer) }}" />
                        @endcan
                        @can('securepointutm_viewAny')
                            <x-aside.dropdownlink :label="__('Securepoint UTM')"
                                href="{{ route('securepointutm.index', $customer) }}" />
                        @endcan
                        @can('router_viewAny')
                            <x-aside.dropdownlink :label="__('Router')" href="{{ route('router.index', $customer) }}" />
                        @endcan
                        @can('network_viewAny')
                            <x-aside.dropdownlink :label="__('VLAN')" href="{{ route('network.index', $customer) }}" />
                            <x-aside.dropdownlink :label="__('IPAM')" href="{{ route('ipplan.index', $customer) }}" />
                        @endcan
                        @can('wifi_viewAny')
                            <x-aside.dropdownlink :label="__('WLAN Netze')" href="{{ route('wifi.index', $customer) }}" />
                        @endcan
                        @can('networkswitch_viewAny')
                            <x-aside.dropdownlink :label="__('Switch')" href="{{ route('networkswitch.index', $customer) }}" />
                        @endcan
                        @can('accesspoint_viewAny')
                            <x-aside.dropdownlink :label="__('Accesspoint')" href="{{ route('accesspoint.index', $customer) }}" />
                        @endcan
                        @can('rack_viewAny')
                            <x-aside.dropdownlink :label="__('Serverschränke')" href="{{ route('rack.index', $customer) }}" />
                        @endcan
                        @can('patchpanel_viewAny')
                            <x-aside.dropdownlink :label="__('Patchfelder')" href="{{ route('patchpanel.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['server_viewAny', 'vm_viewAny', 'nas_viewAny'])
                <x-aside.dropdown :label="__('Server')" svg="svg.servers">
                    <x-slot:links>
                        @can('server_viewAny')
                            <x-aside.dropdownlink :label="__('Server')" href="{{ route('server.index', $customer) }}" />
                        @endcan
                        @can('vm_viewAny')
                            <x-aside.dropdownlink :label="__('VMs')" href="{{ route('vm.index', $customer) }}" />
                        @endcan
                        @can('nas_viewAny')
                            <x-aside.dropdownlink :label="__('NAS')" href="{{ route('nas.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['computer_viewAny', 'printer_viewAny', 'iotdevice_viewAny', 'machine_viewAny', 'otherclient_viewAny'])
                <x-aside.dropdown :label="__('Clients')" svg="svg.computer">
                    <x-slot:links>
                        @can('computer_viewAny')
                            <x-aside.dropdownlink :label="__('Computer')" href="{{ route('computer.index', $customer) }}" />
                        @endcan
                        @can('printer_viewAny')
                            <x-aside.dropdownlink :label="__('Drucker')" href="{{ route('printer.index', $customer) }}" />
                        @endcan
                        @can('iotdevice_viewAny')
                            <x-aside.dropdownlink :label="__('IoT-Gerät')" href="{{ route('iotdevice.index', $customer) }}" />
                        @endcan
                        @can('machine_viewAny')
                            <x-aside.dropdownlink :label="__('Maschinen')" href="{{ route('machine.index', $customer) }}" />
                        @endcan
                        @can('otherclient_viewAny')
                            <x-aside.dropdownlink :label="__('Sonstige')" href="{{ route('otherclient.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['addomain_viewAny', 'aduser_viewAny', 'adgroup_viewAny'])
                <x-aside.dropdown :label="__('AD')" svg="svg.user">
                    <x-slot:links>
                        @can('addomain_viewAny')
                            <x-aside.dropdownlink :label="__('AD-Domäne')" href="{{ route('addomain.index', $customer) }}" />
                        @endcan
                        @can('aduser_viewAny')
                            <x-aside.dropdownlink :label="__('AD-User')" href="{{ route('aduser.index', $customer) }}" />
                        @endcan
                        @can('adgroup_viewAny')
                            <x-aside.dropdownlink :label="__('AD-Gruppen')" href="{{ route('adgroup.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['phonesystem_viewAny', 'phone_viewAny', 'dect_viewAny'])
                <x-aside.dropdown :label="__('Telefon')" svg="svg.phone">
                    <x-slot:links>
                        @can('phonesystem_viewAny')
                            <x-aside.dropdownlink :label="__('TK-Anlage')" href="{{ route('phonesystem.index', $customer) }}" />
                        @endcan
                        @can('phone_viewAny')
                            <x-aside.dropdownlink :label="__('Telefon')" href="{{ route('phone.index', $customer) }}" />
                        @endcan
                        @can('dect_viewAny')
                            <x-aside.dropdownlink :label="__('DECT')" href="{{ route('dect.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['logingeneral_viewAny', 'loginwebsite_viewAny', 'loginnas_viewAny', 'loginrecorder_viewAny'])
                <x-aside.dropdown :label="__('Logins')" svg="svg.login">
                    <x-slot:links>
                        @can('logingeneral_viewAny')
                            <x-aside.dropdownlink :label="__('Allgemein')" href="{{ route('logingeneral.index', $customer) }}" />
                        @endcan
                        @can('loginwebsite_viewAny')
                            <x-aside.dropdownlink :label="__('Webseiten')" href="{{ route('loginwebsite.index', $customer) }}" />
                        @endcan
                        @can('loginnas_viewAny')
                            <x-aside.dropdownlink :label="__('NAS')" href="{{ route('loginnas.index', $customer) }}" />
                        @endcan
                        @can('loginrecorder_viewAny')
                            <x-aside.dropdownlink :label="__('Recorder')" href="{{ route('loginrecorder.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['securepointuma_viewAny', 'mailbox_viewAny'])
                <x-aside.dropdown :label="__('E-Mail')" svg="svg.mail">
                    <x-slot:links>
                        @can('securepointuma_viewAny')
                           <x-aside.dropdownlink :label="__('E-Mail-Archivierung')" href="{{ route('securepointuma.index', $customer) }}" />
                        @endcan
                        @can('mailbox_viewAny')
                           <x-aside.dropdownlink :label="__('E-Mail Postfächer')" href="{{ route('mailbox.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['recorder_viewAny', 'camera_viewAny'])
                <x-aside.dropdown :label="__('Kamera')" svg="svg.cam">
                    <x-slot:links>
                        @can('recorder_viewAny')
                            <x-aside.dropdownlink :label="__('Recorder')" href="{{ route('recorder.index', $customer) }}" />
                        @endcan
                        @can('camera_viewAny')
                            <x-aside.dropdownlink :label="__('Kamera')" href="{{ route('camera.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['licensewindows_viewAny','licenseaccess_viewAny' , 'licensesoftware_viewAny'])
                <x-aside.dropdown :label="__('Lizenzen')" svg="svg.document">
                    <x-slot:links>
                        @can('licensewindows_viewAny')
                            <x-aside.dropdownlink :label="__('Windows')" href="{{ route('licensewindows.index', $customer) }}" />
                        @endcan
                        @can('licenseaccess_viewAny')
                            <x-aside.dropdownlink :label="__('CAL')" href="{{ route('licenseaccess.index', $customer) }}" />
                        @endcan
                        @can('licensesoftware_viewAny')
                            <x-aside.dropdownlink :label="__('Software')" href="{{ route('licensesoftware.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @canany(['ftpserver_viewAny', 'dyndns_viewAny', 'domain_viewAny', 'certificate_viewAny', 'backup_viewAny'])
                <x-aside.dropdown :label="__('Dienste')" svg="svg.settings">
                    <x-slot:links>
                        @can('ftpserver_viewAny')
                            <x-aside.dropdownlink :label="__('FTP-Server')" href="{{ route('ftpserver.index', $customer) }}" />
                        @endcan
                        @can('dyndns_viewAny')
                            <x-aside.dropdownlink :label="__('DynDNS')" href="{{ route('dyndns.index', $customer) }}" />
                        @endcan
                        @can('domain_viewAny')
                            <x-aside.dropdownlink :label="__('Domains')" href="{{ route('domain.index', $customer) }}" />
                        @endcan
                        @can('certificate_viewAny')
                            <x-aside.dropdownlink :label="__('Zertifikate')" href="{{ route('certificate.index', $customer) }}" />
                        @endcan
                        @can('backup_viewAny')
                            <x-aside.dropdownlink :label="__('Backup')" href="{{ route('backup.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany

            @php $wizardPermissions = collect(config('custom.wizard_steps'))->pluck('permission')->all(); @endphp
            @canany(['file_viewAny', 'ups_viewAny', 'see_hidden', ...$wizardPermissions])
                <x-aside.dropdown :label="__('Sonstiges')" svg="svg.settings">
                    <x-slot:links>
                        @can('ups_viewAny')
                            <x-aside.dropdownlink :label="__('USV')" href="{{ route('ups.index', $customer) }}" />
                        @endcan
                        @can('file_viewAny')
                            <x-aside.dropdownlink :label="__('Dateien')" href="{{ route('file.index', $customer) }}" />
                        @endcan
                        @canany($wizardPermissions)
                            <x-aside.dropdownlink :label="__('Erstaufnahme-Assistent')" href="{{ route('wizard.index', $customer) }}" />
                        @endcanany
                        @can('see_hidden')
                            <x-aside.dropdownlink :label="__('Auto-Dokumentation')" href="{{ route('agent.index', $customer) }}" />
                            <x-aside.dropdownlink :label="__('Papierkorb')" href="{{ route('trash.index', $customer) }}" />
                        @endcan
                    </x-slot:links>
                </x-aside.dropdown>
            @endcanany



        </ul>
        <a href="{{ route('changelog') }}" target="_blank" class="mt-10 pt-3 text-center text-xs text-gray-400 border-t border-gray-200 hover:text-cerulean-600 dark:border-gray-700 dark:text-gray-500">
            v{{ $version }}
        </a>
    </div>
</aside>
