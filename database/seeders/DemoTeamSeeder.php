<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\CustomerAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Teams in the demo, so that every access level can be seen by signing in
 * as "techniker":
 *
 *   Mustermann       full            - the everyday case
 *   second customer  read            - visible, nothing can be changed
 *   third customer   read_no_secrets - visible, passwords stay hidden
 *   fourth customer  hidden          - only the management team sees it
 *   the rest         no team         - open to every staff user, as before
 *
 * "admin" is in the management team with full access everywhere, so the
 * admin login still sees and changes every customer.
 */
class DemoTeamSeeder extends Seeder
{
    public function run(): void
    {
        $mustermann = Customer::where('name', 'Mustermann')->firstOrFail();
        // The generated customers of CustomerSeeder, in the order they were made.
        [$readOnly, $noSecrets, $hidden] = Customer::whereKeyNot($mustermann->id)->orderBy('id')->limit(3)->get()->all();

        $admin = User::where('username', 'admin')->firstOrFail();
        $techniker = User::where('username', 'techniker')->firstOrFail();

        // The three technicians of the IT service provider who also work in
        // the activity log (DemoProtokollSeeder, which finds them by name).
        // They cannot sign in: random password.
        [$jonas, $malte, $rita] = collect(['Jonas Wieck' => 'jwieck', 'Malte Ruhnau' => 'mruhnau', 'Rita Sander' => 'rsander'])
            ->map(fn ($username, $name) => User::firstOrCreate(['username' => $username], [
                'name' => $name, 'email' => $username.'@systemhaus.example', 'password' => bcrypt(Str::random(40)),
                'role_id' => Role::IS_TECHNIKER,
            ]))->values()->all();

        $this->team('Geschäftsführung', 'Sieht und ändert alle Kunden.', [$admin], [
            [$mustermann, CustomerAccess::FULL],
            [$readOnly, CustomerAccess::FULL],
            [$noSecrets, CustomerAccess::FULL],
            [$hidden, CustomerAccess::FULL],
        ]);

        $this->team('Technik Nord', 'Betreut die Kunden in Hamburg.', [$techniker, $jonas, $malte, $rita], [
            [$mustermann, CustomerAccess::FULL],
            // Handed over to another team: still looked up, no longer changed.
            [$readOnly, CustomerAccess::READ],
        ]);

        $this->team('Hotline', 'Erste Anlaufstelle – sieht die Geräte, nicht die Kennwörter.', [$techniker, $rita], [
            [$noSecrets, CustomerAccess::READ_NO_SECRETS],
        ]);
    }

    /**
     * @param  list<User>  $members
     * @param  list<array{0: Customer, 1: string}>  $customers
     */
    private function team(string $name, string $description, array $members, array $customers): void
    {
        $team = Team::create(['name' => $name, 'description' => $description]);
        $team->members()->attach(collect($members)->pluck('id')->all());

        foreach ($customers as [$customer, $level]) {
            $team->customers()->attach($customer->id, ['level' => $level]);
        }
    }
}
