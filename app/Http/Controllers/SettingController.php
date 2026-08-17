<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Einstellungen der Installation. Bisher genau eine: welche Fernwartung
 * benutzt wird.
 */
class SettingController extends Controller
{
    public function index()
    {
        return view('admin.setting.index', [
            'tools' => config('custom.remote_tools'),
            'aktuell' => Setting::wert(Setting::REMOTE_TOOL, 'rustdesk'),
            'muster' => Setting::wert(Setting::REMOTE_PATTERN),
        ]);
    }

    public function update(Request $request)
    {
        $daten = $request->validate([
            'remote_tool' => ['required', Rule::in(array_keys(config('custom.remote_tools')))],
            'remote_pattern' => ['nullable', 'max:255', 'required_if:remote_tool,custom'],
        ], [], [
            'remote_tool' => 'Fernwartungslösung',
            'remote_pattern' => 'URL-Muster',
        ]);

        if (($daten['remote_tool'] ?? null) === 'custom') {
            $this->musterPruefen($request);
        }

        Setting::setzen(Setting::REMOTE_TOOL, $daten['remote_tool']);
        Setting::setzen(Setting::REMOTE_PATTERN, $daten['remote_pattern'] ?? null);

        return redirect(route('admin.setting.index'))->with('success', __('Einstellungen gespeichert.'));
    }

    /**
     * Aus dem Muster wird ein anklickbarer Link. Ein Muster wie
     * "javascript:..." waere damit ausfuehrbarer Code in jeder Geraeteliste -
     * deshalb muss es mit einem Programm-Schema beginnen und darf keines der
     * Schemata sein, die der Browser selbst auswertet.
     */
    private function musterPruefen(Request $request): void
    {
        $muster = (string) $request->input('remote_pattern');
        $schema = strtolower(strtok($muster, ':'));

        $verboten = ['javascript', 'data', 'vbscript', 'file', 'about', 'blob'];

        if (! preg_match('/^[a-z][a-z0-9+.-]*:/i', $muster) || in_array($schema, $verboten, true)) {
            abort(redirect(route('admin.setting.index'))->withErrors([
                'remote_pattern' => __('Das Muster muss mit einem Programm-Schema beginnen, etwa "meintool://".'),
            ])->withInput());
        }

        if (! str_contains($muster, '{id}')) {
            abort(redirect(route('admin.setting.index'))->withErrors([
                'remote_pattern' => __('Im Muster fehlt {id} — ohne die Kennung führt der Knopf nirgendwohin.'),
            ])->withInput());
        }
    }
}
