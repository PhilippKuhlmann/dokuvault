<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\LoginGeneral;
use App\Models\Network;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Support\CredentialLinking;
use App\Support\IpAssignment;
use App\Support\MobilObjekte;
use App\Support\ScanTargetLinking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * What hangs on a device in the iOS app: linked credentials, IP addresses
 * and - on a scanner - scan targets. The same blocks the browser shows in the
 * modal, with the same rules (CredentialLinking, IpAssignment,
 * ScanTargetLinking).
 *
 * Everything here changes the device, so every call needs the right to edit
 * it - as in the browser. The GET calls return what the app needs to offer:
 * the current entries plus the choices.
 */
class DeviceController extends Controller
{
    public function __construct(private MobilObjekte $objekte) {}

    // --- Zugangsdaten ----------------------------------------------------------

    public function credentials(Request $request, string $typ, int $id, CredentialLinking $verknuepfung): JsonResponse
    {
        $geraet = $this->geraet($request, $typ, $id, 'credentialLinks');
        abort_if($verknuepfung->visibleKinds($request->user()) === [], 403);

        return response()->json([
            'eintraege' => $this->objekte->detail($geraet, $typ, $request->user())['zugangsdaten'],
            'verfuegbar' => $verknuepfung->available($geraet, $request->user())->map(fn (LoginGeneral $login) => [
                'id' => $login->id,
                'name' => $login->name,
                'benutzer' => $login->username,
                'art' => $login->istSchluessel() ? 'schluessel' : 'kennwort',
            ])->values(),
            'darf_neu' => Gate::allows('logingeneral_create'),
        ]);
    }

    /** Attach an existing login ("login_id") or create one ("name", ...) and attach it. */
    public function attachCredential(Request $request, string $typ, int $id, CredentialLinking $verknuepfung): JsonResponse
    {
        $geraet = $this->geraet($request, $typ, $id, 'credentialLinks');
        abort_if($verknuepfung->visibleKinds($request->user()) === [], 403);

        if ($request->filled('login_id')) {
            $daten = Validator::make($request->all(), $verknuepfung->attachRules($geraet->customer_id, $request->user()), [], $verknuepfung->attributeNames())->validate();
            $verknuepfung->attach($geraet, (int) $daten['login_id'], $daten['note'] ?? null);
        } else {
            Gate::authorize('logingeneral_create');
            $daten = Validator::make($request->all(), $verknuepfung->createRules(), [], $verknuepfung->attributeNames())->validate();
            $verknuepfung->create($geraet, $daten);
        }

        return response()->json($this->objekte->detail($geraet->fresh(), $typ, $request->user())['zugangsdaten'], 201);
    }

    /** Only the link goes - the login itself stays. */
    public function detachCredential(Request $request, string $typ, int $id, int $link, CredentialLinking $verknuepfung): JsonResponse
    {
        $geraet = $this->geraet($request, $typ, $id, 'credentialLinks');
        abort_if($verknuepfung->visibleKinds($request->user()) === [], 403);

        $verknuepfung->detach($geraet, $link);

        return response()->json(null, 204);
    }

    // --- IP-Adressen -----------------------------------------------------------

    public function ipAddresses(Request $request, string $typ, int $id): JsonResponse
    {
        $geraet = $this->geraet($request, $typ, $id, 'ipAddresses');

        return response()->json([
            'eintraege' => $this->objekte->detail($geraet, $typ, $request->user())['ip_adressen'],
            // With the next free address, as the browser proposes it when a
            // network is chosen.
            'netze' => Network::where('customer_id', $geraet->customer_id)->orderBy('vlanId')->get()
                ->map(fn (Network $netz) => [
                    'id' => $netz->id,
                    'name' => $netz->protokollName(),
                    'naechste_freie' => $netz->naechsteFreieAdresse(),
                ])->values(),
        ]);
    }

    /** {address, network_id?, label?} or {dhcp: true, network_id, label?}. */
    public function addIpAddress(Request $request, string $typ, int $id, IpAssignment $ips): JsonResponse
    {
        $geraet = $this->geraet($request, $typ, $id, 'ipAddresses');
        $dhcp = $request->boolean('dhcp');

        $daten = Validator::make($request->all(), $ips->rules($geraet->customer_id, $dhcp), $ips->messages(), $ips->attributeNames())->validate();
        $ips->add($geraet, $daten, $dhcp);

        return response()->json($this->objekte->detail($geraet->fresh(), $typ, $request->user())['ip_adressen'], 201);
    }

    public function removeIpAddress(Request $request, string $typ, int $id, int $ip, IpAssignment $ips): JsonResponse
    {
        $ips->remove($this->geraet($request, $typ, $id, 'ipAddresses'), $ip);

        return response()->json(null, 204);
    }

    // --- Scan-Ziele ------------------------------------------------------------

    public function scanTargets(Request $request, int $id): JsonResponse
    {
        $scanner = $this->geraet($request, 'scanner', $id, 'scanTargets');

        return response()->json([
            'eintraege' => $this->objekte->detail($scanner, 'scanner', $request->user())['scan_ziele'],
            // Zur Auswahl nur, was noch nicht verknuepft ist - und nur fuer
            // den, der die Liste der Ziele sehen darf.
            'verfuegbar' => Gate::allows('scantarget_viewAny')
                ? ScanTarget::where('customer_id', $scanner->customer_id)
                    ->whereNotIn('id', $scanner->scanTargets()->pluck('scan_targets.id'))
                    ->orderBy('name')->get()
                    ->map(fn (ScanTarget $ziel) => $this->objekte->scanZiel($ziel))->values()
                : [],
            'arten' => collect(ScanTarget::ARTEN)->map(fn ($label, $wert) => ['wert' => $wert, 'label' => __($label)])->values(),
            'logins' => Gate::allows('logingeneral_viewAny')
                ? LoginGeneral::where('customer_id', $scanner->customer_id)->orderBy('name')->get()
                    ->map(fn (LoginGeneral $login) => ['id' => $login->id, 'name' => $login->name])->values()
                : [],
            'darf_neu' => Gate::allows('scantarget_create'),
            'darf_verknuepfen' => Gate::allows('scantarget_viewAny'),
        ]);
    }

    /** Link an existing target ("ziel_id") or create one ("kind", "target", ...) and link it. */
    public function addScanTarget(Request $request, int $id, ScanTargetLinking $ziele): JsonResponse
    {
        $scanner = $this->geraet($request, 'scanner', $id, 'scanTargets');
        $neu = ! $request->filled('ziel_id');

        if ($neu) {
            Gate::authorize('scantarget_create');
        }

        // The block names it zielId; the app sends ziel_id like the other ids.
        $eingabe = [...$request->all(), 'zielId' => $request->input('ziel_id')];
        $daten = Validator::make($eingabe, $ziele->rules($scanner->customer_id, $neu), [], $ziele->attributeNames())->validate();

        $ziele->add($scanner, $daten, $neu);

        return response()->json($this->objekte->detail($scanner->fresh(), 'scanner', $request->user())['scan_ziele'], 201);
    }

    public function removeScanTarget(Request $request, int $id, int $ziel, ScanTargetLinking $ziele): JsonResponse
    {
        $ziele->remove($this->geraet($request, 'scanner', $id, 'scanTargets'), $ziel);

        return response()->json(null, 204);
    }

    /**
     * The device - with permission and customer, as the detail view - and
     * the right to edit it. 404 if the type has no such block: a printer
     * has no scan targets.
     */
    private function geraet(Request $request, string $typ, int $id, string $block): Model
    {
        $geraet = $this->objekte->finden($typ, $id, $request->user());

        abort_unless(method_exists($geraet, $block), 404);
        abort_if($block === 'scanTargets' && ! $geraet instanceof Scanner, 404);

        Gate::authorize($this->objekte->typen()[$typ][1].'_update');

        return $geraet;
    }
}
