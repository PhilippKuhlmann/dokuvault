<?php

namespace App\Support;

use App\Models\ADDomainHost;
use App\Models\CredentialLink;
use App\Models\NetworkShare;
use App\Models\Server;
use App\Models\VM;
use Illuminate\Support\Facades\DB;

/**
 * A server that is in truth a VM becomes one: everything that hangs on it
 * moves to the VM, the server goes to the trash (restorable there).
 *
 * Two ways in: by hand ("In VM umwandeln" on the server card) and by the
 * Windows agent, when the BIOS UUID shows it runs in a VM a hypervisor
 * agent documents.
 */
class ServerToVm
{
    /**
     * Why this server cannot become a VM - or null when it can. A rack slot
     * or guests of its own are things a VM does not have; moving them
     * silently would lose them.
     */
    public static function blocker(Server $server): ?string
    {
        if ($server->rackItem) {
            return __('Der Server ist in einem Serverschrank eingebaut. Nimm ihn dort zuerst heraus.');
        }

        if ($server->gaeste()->exists()) {
            return __('Auf dem Server laufen Gäste. Ein Host bleibt ein Server.');
        }

        return null;
    }

    /**
     * A new VM from the server: name, site, operating system, then
     * everything else as in into().
     */
    public static function asNewVm(Server $server, string $name, ?int $hostId = null, ?int $clusterId = null): VM
    {
        // Level "read" (CustomerAccess): checked here, before anything is
        // written - this path skips the model events or touches files first.
        CustomerAccess::assertWritable((int) $server->customer_id);

        return DB::transaction(function () use ($server, $name, $hostId, $clusterId) {
            $vm = VM::create([
                'customer_id' => $server->customer_id,
                'site_id' => $server->site_id,
                'name' => $name,
                'operating_system_id' => $server->operating_system_id,
                'server_id' => $hostId,
                'cluster_id' => $clusterId,
            ]);

            static::into($server, $vm);

            return $vm->fresh();
        });
    }

    /**
     * Moves what hangs on the server to the VM and trashes the server:
     * addresses (a duplicate is dropped), linked logins, AD roles, network
     * shares; services, remote access and the agent's identifier into empty
     * fields of the VM. Whatever the VM already has stays.
     */
    public static function into(Server $server, VM $vm): void
    {
        // Level "read" (CustomerAccess): checked here, before anything is
        // written - this path skips the model events or touches files first.
        CustomerAccess::assertWritable((int) $server->customer_id);

        DB::transaction(function () use ($server, $vm) {
            $vorhanden = $vm->ipAddresses()->pluck('address')->filter()->all();
            foreach ($server->ipAddresses()->get() as $adresse) {
                if ($adresse->address !== null && in_array($adresse->address, $vorhanden, true)) {
                    $adresse->delete();
                } else {
                    $adresse->ipable()->associate($vm)->save();
                }
            }

            foreach (CredentialLink::where('credentialable_type', $server->getMorphClass())->where('credentialable_id', $server->id)->get() as $link) {
                $doppelt = CredentialLink::where('credentialable_type', $vm->getMorphClass())->where('credentialable_id', $vm->id)
                    ->where('login_general_id', $link->login_general_id)->exists();
                $doppelt ? $link->delete() : $link->update(['credentialable_type' => $vm->getMorphClass(), 'credentialable_id' => $vm->id]);
            }

            foreach (ADDomainHost::where('host_type', $server->getMorphClass())->where('host_id', $server->id)->get() as $rolle) {
                $doppelt = ADDomainHost::where('host_type', $vm->getMorphClass())->where('host_id', $vm->id)
                    ->where('ad_domain_id', $rolle->ad_domain_id)->where('role', $rolle->role)->exists();
                $doppelt ? $rolle->delete() : $rolle->update(['host_type' => $vm->getMorphClass(), 'host_id' => $vm->id]);
            }

            NetworkShare::withTrashed()->where('host_type', $server->getMorphClass())->where('host_id', $server->id)
                ->update(['host_type' => $vm->getMorphClass(), 'host_id' => $vm->id]);

            // Raw values: the remote password is stored encrypted on both.
            // guest_identifier: the agent inside finds its machine by it on
            // the next run, instead of creating the server anew.
            $leer = fn (string $feld) => blank(DB::table('vms')->where('id', $vm->id)->value($feld));
            $vonServer = array_filter([
                'services' => $leer('services') ? $server->getRawOriginal('services') : null,
                'remoteID' => $leer('remoteID') ? $server->getRawOriginal('remoteID') : null,
                'remotePassword' => $leer('remotePassword') ? $server->getRawOriginal('remotePassword') : null,
                'guest_identifier' => $leer('guest_identifier') ? $server->agent_identifier : null,
            ], fn ($wert) => filled($wert));
            if ($vonServer) {
                DB::table('vms')->where('id', $vm->id)->update($vonServer);
            }

            $server->delete();
        });
    }
}
