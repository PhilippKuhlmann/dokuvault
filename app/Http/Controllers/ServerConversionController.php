<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Server;
use App\Models\VM;
use App\Support\ServerToVm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "In VM umwandeln": a server that is in truth a VM - entered as a server
 * by mistake, or reported as one by an agent - becomes a VM. Either a new
 * one, or it is merged into a VM a hypervisor agent already documents.
 *
 * One direction only. A VM that is really hardware is rare enough to be
 * entered anew.
 */
class ServerConversionController extends Controller
{
    public function create(Customer $customer, Server $server)
    {
        Gate::authorize('server_delete');

        return view('server.to-vm', [
            'customer' => $customer,
            'server' => $server,
            'blocker' => ServerToVm::blocker($server),
            'vms' => VM::where('customer_id', $customer->id)->with('host')->orderBy('name')->get(),
            'hosts' => Server::where('customer_id', $customer->id)->whereKeyNot($server->id)->orderBy('name')->get(),
            'clusters' => Cluster::where('customer_id', $customer->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Customer $customer, Server $server)
    {
        Gate::authorize('server_delete');

        if ($grund = ServerToVm::blocker($server)) {
            return back()->withErrors(['server' => $grund]);
        }

        $data = $request->validate([
            'mode' => ['required', Rule::in(['new', 'merge'])],
            'name' => ['required_if:mode,new', 'nullable', 'string', 'max:255'],
            'server_id' => ['nullable', 'integer', Rule::exists('servers', 'id')->where('customer_id', $customer->id)->whereNull('deleted_at')->whereNot('id', $server->id)],
            'cluster_id' => ['nullable', 'integer', Rule::exists('clusters', 'id')->where('customer_id', $customer->id)->whereNull('deleted_at')],
            'vm_id' => ['required_if:mode,merge', 'nullable', 'integer', Rule::exists('vms', 'id')->where('customer_id', $customer->id)->whereNull('deleted_at')],
        ]);

        if ($data['mode'] === 'new') {
            Gate::authorize('vm_create');
            $vm = ServerToVm::asNewVm($server, $data['name'], $data['server_id'] ?? null, $data['cluster_id'] ?? null);
            $meldung = __(':server ist jetzt die VM :vm.', ['server' => $server->name, 'vm' => $vm->name]);
        } else {
            Gate::authorize('vm_update');
            $vm = VM::where('customer_id', $customer->id)->findOrFail($data['vm_id']);
            ServerToVm::into($server, $vm);
            $meldung = __(':server ist mit der VM :vm zusammengeführt.', ['server' => $server->name, 'vm' => $vm->name]);
        }

        return redirect()->route('vm.index', [$customer, 'highlight' => $vm->id])->with('success', $meldung);
    }
}
