<?php

namespace App\Livewire;

use App\Models\Server;
use App\Models\VM;
use Livewire\Attributes\Url;
use Livewire\Component;

class RemoteSearch extends Component
{
    #[Url]
    public $search;

    public function render()
    {
        if ($this->search) {
            $search = $this->search;

            $servers = Server::where('remoteID', '!=', '')->where('remotePassword', '!=', '')
                ->with('customer')
                ->join('customers', 'servers.customer_id', '=', 'customers.id')
                ->select('servers.*', 'customers.name as customerName')
                ->where('customers.name', 'like', "%$search%")
                ->orderBy('customers.name')
                ->get();

            $vms = VM::where('remoteID', '!=', '')->where('remotePassword', '!=', '')
                ->with('customer')
                ->join('customers', 'vms.customer_id', '=', 'customers.id')
                ->select('vms.*', 'customers.name as customerName')
                ->where('customers.name', 'like', "%$search%")
                ->orderBy('customers.name')
                ->get();

        } else {
            $servers = Server::where('remoteID', '!=', '')
                ->where('remotePassword', '!=', '')
                ->with('customer')
                ->join('customers', 'servers.customer_id', '=', 'customers.id')
                ->select('servers.*', 'customers.name as customerName')
                ->orderBy('customers.name')
                ->get();

            $vms = VM::where('remoteID', '!=', '')
                ->where('remotePassword', '!=', '')
                ->select('name')
                ->join('customers', 'vms.customer_id', '=', 'customers.id')
                ->select('vms.*', 'customers.name as customerName')
                ->orderBy('customers.name')
                ->get();
        }

        $remotes = [];

        foreach ($servers as $server) {
            $remotes[] = [
                'remoteID' => $server->remoteID,
                'remotePassword' => $server->remotePassword,
                'customerName' => $server->customerName,
                'name' => $server->name,
            ];
        }

        foreach ($vms as $vm) {
            $remotes[] = [
                'remoteID' => $vm->remoteID,
                'remotePassword' => $vm->remotePassword,
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
