<?php

namespace Database\Seeders;

use App\Models\Accesspoint;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Computer;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\Firewall;
use App\Models\LicenseSoftware;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Printer;
use App\Models\Router;
use App\Models\Server;
use App\Models\Site;
use App\Models\VM;
use App\Models\Wifi;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Weitere Demo-Kunden (zusätzlich zu "Mustermann" aus LocalDatabaseSeeder),
     * jeweils mit einer ähnlichen Bandbreite an Gerätetypen befüllt.
     */
    public function run(): void
    {
        $customers = Customer::factory(5)->create();

        foreach ($customers as $customer) {

            $site1 = Site::factory()->create([
                'customer_id' => $customer->id,
                'name' => 'Hauptsitz',
            ]);

            $site2 = Site::factory()->create([
                'customer_id' => $customer->id,
                'name' => 'Filiale',
            ]);

            ContactPerson::factory(2)->create([
                'customer_id' => $customer->id,
            ]);

            // Netzwerk zuerst (wird von Wifi benötigt)
            $network = Network::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            Router::factory(1)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            // The firewall holds the VLAN's gateway, so the network map shows
            // the VLAN below it instead of below an undocumented gateway.
            Firewall::factory()->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ])->ipAddresses()->create([
                'customer_id' => $customer->id,
                'network_id' => $network->id,
                'address' => $network->gateway,
                'label' => 'Gateway',
            ]);

            NetworkSwitch::factory(2)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            Accesspoint::factory(2)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            Wifi::factory(1)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
                'network_id' => $network->id,
            ]);

            Server::factory(3)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            VM::factory(5)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            Server::factory(1)->create([
                'customer_id' => $customer->id,
                'site_id' => $site2->id,
            ]);

            VM::factory(3)->create([
                'customer_id' => $customer->id,
                'site_id' => $site2->id,
            ]);

            Computer::factory(8)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            Printer::factory(2)->create([
                'customer_id' => $customer->id,
                'site_id' => $site1->id,
            ]);

            ADUser::factory(5)->create([
                'customer_id' => $customer->id,
            ]);

            ADGroup::factory(3)->create([
                'customer_id' => $customer->id,
            ]);

            LicenseSoftware::factory(2)->create([
                'customer_id' => $customer->id,
            ]);

        }
    }
}
