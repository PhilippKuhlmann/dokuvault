<?php

namespace App\Livewire;

use App\Models\Server;
use App\Models\VM;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class RemoteSearch extends Component
{
    #[Url]
    public $search;

    public function render()
    {
        // A customer account only finds its own devices - as in the global
        // search, and as RemoteConnectController checks on connect.
        $kunde = auth()->user()->customer_id;

        $abfrage = function (string $klasse, string $tabelle, string $recht) use ($kunde) {
            // The remote search right alone must not reveal device names the
            // role may not see in the lists.
            if (Gate::denies($recht)) {
                return collect();
            }

            return $klasse::where($tabelle.'.remoteID', '!=', '')
                ->where($tabelle.'.remotePassword', '!=', '')
                ->with('customer')
                ->join('customers', $tabelle.'.customer_id', '=', 'customers.id')
                ->select($tabelle.'.*', 'customers.name as customerName')
                ->when($kunde, fn ($q) => $q->where($tabelle.'.customer_id', $kunde))
                // Kundenname oder Geraetename: Wer einen bestimmten Server
                // fernwarten will, kennt meist dessen Namen und nicht den des
                // Kunden. Vorher traf die Suche nur den Kunden - wer
                // "SRV-DC01" eintippte, bekam nichts und hielt das Geraet fuer
                // nicht eingerichtet.
                ->when($this->search, fn ($q) => $q->whereEnthaelt(['customers.name', $tabelle.'.name'], $this->search))
                ->orderBy('customers.name')
                ->get();
        };

        $servers = $abfrage(Server::class, 'servers', 'server_viewAny');
        $vms = $abfrage(VM::class, 'vms', 'vm_viewAny');

        $remotes = [];

        foreach ($servers as $server) {
            $remotes[] = [
                'geraet' => $server,
                'customerName' => $server->customerName,
                'name' => $server->name,
            ];
        }

        foreach ($vms as $vm) {
            $remotes[] = [
                'geraet' => $vm,
                'customerName' => $vm->customerName,
                'name' => $vm->name,
            ];
        }

        usort($remotes, function ($a, $b) {
            $customerComparison = strcmp($a['customerName'], $b['customerName']);

            if ($customerComparison !== 0) {
                return $customerComparison;
            }

            return strcmp($a['name'], $b['name']);
        });

        return view('livewire.remote-search', compact('remotes'))
            ->layout('layouts.empty', ['title' => 'Rustdesk']);
    }
}
