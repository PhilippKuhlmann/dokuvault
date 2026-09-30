<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die vier Rechte fuer Scanner und fuer Scan-Ziele, fuer bestehende Installationen. Frische
 * Installationen bekommen sie ueber den PermissionRoleSeeder (config
 * custom.permissions), der vorhandene Namen wiederverwendet.
 *
 * Wer ein Drucker-Recht hat, bekommt das entsprechende Scanner- und
 * Scan-Ziel-Recht: Alles steht unter "Clients" nebeneinander, und eine Rolle,
 * die Drucker pflegt, soll den Scanner daneben nicht erst freigeschaltet
 * bekommen muessen.
 */
return new class extends Migration
{
    private const TYPEN = ['scanner' => 'Scanner', 'scantarget' => 'ScanTarget'];

    private const RECHTE = [
        'viewAny' => 'sehen',
        'create' => 'erstellen',
        'update' => 'bearbeiten',
        'delete' => 'löschen',
    ];

    public function up(): void
    {
        foreach (self::TYPEN as $typ => $model) {
            foreach (self::RECHTE as $recht => $beschreibung) {
                $this->rechtAnlegen($typ, $model, $recht, $beschreibung);
            }
        }
    }

    private function rechtAnlegen(string $typ, string $model, string $recht, string $beschreibung): void
    {
        $id = DB::table('permissions')->where('name', "{$typ}_{$recht}")->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => "{$typ}_{$recht}",
                'description' => "{$model} {$beschreibung}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $vorbild = DB::table('permissions')->where('name', "printer_{$recht}")->value('id');

        if (! $vorbild) {
            return;
        }

        $rollen = DB::table('permission_role')->where('permission_id', $vorbild)->pluck('role_id');
        $schonDa = DB::table('permission_role')->where('permission_id', $id)->pluck('role_id');

        DB::table('permission_role')->insert(
            $rollen->diff($schonDa)
                ->map(fn ($rolle) => ['permission_id' => $id, 'role_id' => $rolle])
                ->values()->all()
        );
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->where(fn ($q) => $q->where('name', 'like', 'scanner\_%')->orWhere('name', 'like', 'scantarget\_%'))
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
