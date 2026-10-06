<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // ADMIN Bereich
    public function index()
    {
        $roles = Role::paginate(Setting::seiteAdmin());
        $roleCount = Role::all()->count();
        $roleLastAdded = Role::latest('created_at')->first();

        return view('admin.role.index', compact('roles', 'roleCount', 'roleLastAdded'));
    }

    public function create()
    {
        [$matrix, $others, $actions, $adminRechte] = $this->groupPermissions();

        return view('admin.role.create', compact('matrix', 'others', 'actions', 'adminRechte'));
    }

    /**
     * Gruppiert die Berechtigungen nach Ressource zu einer Matrix
     * (Zeile = Bereich, Spalten = Sehen/Erstellen/Bearbeiten/Löschen).
     * Rechte, die nicht in dieses Schema passen, kommen nach $others; die des
     * Admin-Bereichs stehen als eigener Block in $adminRechte.
     */
    private function groupPermissions(): array
    {
        $actions = ['viewAny' => 'Sehen', 'create' => 'Erstellen', 'update' => 'Bearbeiten', 'delete' => 'Löschen'];
        $matrix = [];
        $others = [];
        $adminRechte = [];

        // Die Rechte des Admin-Bereichs stehen fuer sich. Sie greifen nicht auf
        // die Daten eines Kunden, sondern auf die Installation - wer sie
        // vergibt, sollte das nicht zwischen zwei Geraetezeilen tun.
        $adminNamen = array_keys(config('custom.admin_permissions'));

        // German names per resource (permission prefix = slug of the trash
        // list). "file" is not trashable - it gets its menu name.
        $anzeigenamen = collect(config('custom.trashables'))
            ->mapWithKeys(fn ($eintrag, $slug) => [$slug => $eintrag[1]])
            ->put('file', 'Datei')
            ->all();

        foreach (Permission::orderBy('description')->get() as $p) {
            $pos = strrpos($p->name, '_');
            $resource = $pos !== false ? substr($p->name, 0, $pos) : $p->name;
            $action = $pos !== false ? substr($p->name, $pos + 1) : '';

            if (in_array($p->name, $adminNamen, true)) {
                $adminRechte[] = $p;
            } elseif (array_key_exists($action, $actions)) {
                if (! isset($matrix[$resource])) {
                    // The German name the user knows from the menu and the
                    // trash ("Windows-Lizenz"), not the class name stored in
                    // the description ("LicenseWindows sehen").
                    $label = $anzeigenamen[$resource]
                        ?? trim(preg_replace('/\s+(sehen|erstellen|bearbeiten|löschen)$/ui', '', $p->description));
                    $matrix[$resource] = ['label' => $label !== '' ? $label : ucfirst($resource), 'perms' => []];
                }
                $matrix[$resource]['perms'][$action] = $p;
            } else {
                $others[] = $p;
            }
        }

        // As in the customer sidebar: section by section, in menu order -
        // alphabetical put "Accesspoint" next to "AD-Benutzer".
        $position = [];
        foreach (config('custom.rechte_menue') as $gruppe => $bereiche) {
            foreach ($bereiche as $bereich) {
                $position[$bereich] = [count($position), $gruppe];
            }
        }
        foreach ($matrix as $resource => &$zeile) {
            [$zeile['position'], $zeile['gruppe']] = $position[$resource] ?? [PHP_INT_MAX, __('Weitere')];
        }
        unset($zeile);
        uasort($matrix, fn ($a, $b) => [$a['position'], $a['label']] <=> [$b['position'], $b['label']]);

        return [$matrix, $others, $actions, $adminRechte];
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'description' => '',
        ]);

        $role = Role::create($validatedData);

        $role->permissions()->attach($request->permissions);

        return redirect()->route('admin.role.index');
    }

    public function edit(Role $role)
    {
        [$matrix, $others, $actions, $adminRechte] = $this->groupPermissions();
        $selected = $role->permissions->pluck('id')->all();

        return view('admin.role.edit', compact('role', 'matrix', 'others', 'actions', 'selected', 'adminRechte'));
    }

    public function update(Role $role, Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'description' => '',
        ]);

        $role->update($validatedData);

        $role->permissions()->sync($request->permissions);

        return redirect(route('admin.role.index'));
    }
}
