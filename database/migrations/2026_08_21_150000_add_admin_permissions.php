<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rechte fuer die einzelnen Admin-Bereiche.
 *
 * Bis hierhin hing alles unter /admin an einer harten Pruefung auf die Rolle 1.
 * Wer eine zweite Technikergruppe wollte, die etwa den Papierkorb sieht, aber
 * keine Benutzer anlegt, konnte das nicht bauen.
 *
 * Die bestehenden Rollen behalten ihren Umfang: Admin und Techniker hatten
 * bisher alle Rechte und bekommen die neuen dazu. Alle anderen Rollen hatten
 * ohnehin keinen Zugang zum Admin-Bereich und bekommen nichts - sonst
 * bekaeme eine Kundenrolle ueber Nacht Rechte, die niemand ihr gegeben hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $neue = collect();

        foreach (array_merge(config('custom.admin_permissions'), config('custom.extra_permissions')) as $name => $beschreibung) {
            // forceFill, weil das Permission-Model nichts zum Fuellen freigibt -
            // der Seeder benutzt aus demselben Grund forceCreate.
            $recht = Permission::where('name', $name)->first() ?? new Permission;
            $recht->forceFill(['name' => $name, 'description' => $beschreibung])->save();

            $neue->push($recht);
        }

        // Die Rolle 1 darf ohnehin alles (Gate::before), bekommt die Rechte
        // aber zugewiesen, damit die Rollenverwaltung sie angehakt zeigt.
        foreach ([Role::IS_ADMIN, Role::IS_TECHNIKER] as $rollenId) {
            $rolle = Role::find($rollenId);

            if (! $rolle) {
                continue;
            }

            $rolle->permissions()->syncWithoutDetaching($neue->pluck('id')->all());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::whereIn('name', array_keys(array_merge(
            config('custom.admin_permissions'),
            config('custom.extra_permissions')
        )))->delete();
    }
};
