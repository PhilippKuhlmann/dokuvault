<x-admin-layout>

    <div class="flex w-full pl-3 pt-3 gap-3">
        <div class="w-64 rounded-xl border border-gray-200 bg-white shadow-sm p-4 dark:bg-gray-800 dark:border-gray-700">
            <div class="h-8 text-cerulean-500 text-center font-CoconPro">
                {{ __('Mail Anbieter Gesamt') }}
            </div>
            <div class="h-10 text-chathams-blue-800 dark:text-gray-100 text-center font-CoconPro text-4xl">
                {{ $mailboxprovidersCount }}
            </div>

        </div>

    </div>



    <x-sitetopmenu />

<div class="m-3">
    <x-table.main>
        <x-table.head :labels="['Name', 'POP3', 'IMAP', 'SMTP', '', ]" />

        <x-table.body>

            @foreach ($mailboxproviders as $mailboxprovider)

                <x-table.datarow
                    :values="[
                        $mailboxprovider->name,
                        $mailboxprovider->pop3server . ':' . $mailboxprovider->pop3port,
                        $mailboxprovider->imapserver . ':' . $mailboxprovider->imapport,
                        $mailboxprovider->smtpserver . ':' . $mailboxprovider->smtpport,

                    ]"

                    editUrl="/{{ Request::path() }}/{{ $mailboxprovider->id }}/edit"
                    can="admin_catalog"
                />

            @endforeach

        </x-table.body>
    </x-table.main>
    <div class="mt-5 mb-10">
        {{ $mailboxproviders->links() }}
    </div>

</div>

</x-admin-layout>
