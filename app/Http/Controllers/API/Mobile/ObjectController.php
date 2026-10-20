<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\MobilObjekte;
use App\Support\ObjectForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creating, editing and deleting from the iOS app - every type of
 * config/forms.php, as the modal in the browser does it.
 *
 * The app builds its form from GET .../forms/{typ}: the fields with their
 * kind, group and choices. A new field in config/forms.php thus reaches the
 * app without a release. Validation and storing are the modal's, see
 * ObjectForm.
 *
 * Secrets never go out with the form values - only whether one is set. An
 * update changes only the fields it sends, so a password the app never saw
 * stays as it is.
 */
class ObjectController extends Controller
{
    /** Kinds the app does not get: hidden fields are not shown in the browser either. */
    private const OHNE = ['versteckt'];

    public function __construct(private MobilObjekte $objekte) {}

    /** The empty form for a new object. */
    public function createForm(Customer $customer, string $typ): JsonResponse
    {
        $form = new ObjectForm($typ, $customer->id);
        Gate::authorize($typ.'_create');

        return response()->json($this->form($form, $form->emptyValues()));
    }

    /** The form of a stored object, with its values. */
    public function editForm(Request $request, string $typ, int $id): JsonResponse
    {
        [$form, $objekt] = $this->holen($request, $typ, $id);
        Gate::authorize($typ.'_update');

        return response()->json([
            ...$this->form($form, $form->values($objekt), $objekt),
            'id' => $objekt->getKey(),
            'darf_loeschen' => Gate::allows($typ.'_delete'),
        ]);
    }

    public function store(Request $request, Customer $customer, string $typ): JsonResponse
    {
        $form = new ObjectForm($typ, $customer->id);
        Gate::authorize($typ.'_create');

        $werte = [...$form->emptyValues(), ...$this->eingabe($request, $form)];
        $objekt = $form->save($form->validate($werte), null, $request->file('datei'));

        return response()->json($this->objekte->detail($objekt->fresh(), $typ, $request->user()), 201);
    }

    /**
     * Only the fields sent are written. The rest is validated with its stored
     * value - a rule like required_if needs the whole picture.
     */
    public function update(Request $request, string $typ, int $id): JsonResponse
    {
        [$form, $objekt] = $this->holen($request, $typ, $id);
        Gate::authorize($typ.'_update');

        $eingabe = $this->eingabe($request, $form);
        $geprueft = $form->validate([...$form->values($objekt), ...$eingabe]);

        $form->save(array_intersect_key($geprueft, $eingabe), $objekt, $request->file('datei'));

        return response()->json($this->objekte->detail($objekt->fresh(), $typ, $request->user()));
    }

    public function destroy(Request $request, string $typ, int $id): JsonResponse
    {
        [, $objekt] = $this->holen($request, $typ, $id);
        Gate::authorize($typ.'_delete');

        $objekt->delete();

        return response()->json(null, 204);
    }

    /**
     * Values from a generator, where the type names one (SSH key pair).
     * Nothing is stored - the app puts them into its form, as the modal does.
     */
    public function generate(Request $request, Customer $customer, string $typ): JsonResponse
    {
        $form = new ObjectForm($typ, $customer->id);
        abort_unless(Gate::any([$typ.'_create', $typ.'_update']), 403);

        $klasse = $form->settings()['erzeuger'] ?? abort(404);

        try {
            return response()->json(app($klasse)->erzeugen($this->eingabe($request, $form)));
        } catch (\RuntimeException $fehler) {
            // A missing algorithm is an input one can correct, not a server error.
            throw ValidationException::withMessages(['key_type' => $fehler->getMessage()]);
        }
    }

    /** Object and form - with permission and customer, as the detail view. */
    private function holen(Request $request, string $typ, int $id): array
    {
        abort_unless(array_key_exists($typ, config('forms')), 404);

        $objekt = $this->objekte->finden($typ, $id, $request->user());

        return [new ObjectForm($typ, $objekt->customer_id), $objekt];
    }

    /** What the app sent, limited to the fields of the form. */
    private function eingabe(Request $request, ObjectForm $form): array
    {
        $namen = collect($form->fields())
            ->reject(fn ($feld) => in_array($feld['type'], self::OHNE, true))
            ->pluck('name')
            ->all();

        return $request->only($namen);
    }

    private function form(ObjectForm $form, array $werte, ?Model $objekt = null): array
    {
        $optionen = $form->options();
        $geheim = config('custom.secret_columns');
        $regeln = $form->rules($werte);

        $felder = collect($form->displayFields())
            ->reject(fn ($feld) => in_array($feld['type'], self::OHNE, true))
            ->map(fn ($feld) => array_filter([
                'name' => $feld['name'],
                'label' => __($feld['label']),
                'art' => $feld['type'],
                'gruppe' => __($feld['gruppe'] ?? 'Allgemein'),
                'pflicht' => $this->pflicht($regeln[$feld['name']] ?? null),
                'geheim' => in_array($feld['name'], $geheim, true),
                'mehrfach' => ($feld['mehrfach'] ?? false) || $feld['type'] === 'verknuepfung',
                'einheit' => $feld['einheit'] ?? null,
                'zeilen' => $feld['zeilen'] ?? null,
                'platzhalter' => $feld['platzhalter'] ?? null,
                // Shown only while other fields have these values, as in the modal.
                'sichtbar_wenn' => $feld['sichtbar_wenn'] ?? null,
                'optionen' => isset($optionen[$feld['name']])
                    ? collect($optionen[$feld['name']])->map(fn ($text, $wert) => ['wert' => (string) $wert, 'label' => $text])->values()->all()
                    : null,
            ], fn ($wert) => $wert !== null))
            ->values();

        // Secrets: never the value, only whether one is stored.
        $gesetzt = [];
        foreach ($felder->where('geheim', true) as $feld) {
            $gesetzt[$feld['name']] = $objekt !== null && filled($objekt->getRawOriginal($feld['name']));
            $werte[$feld['name']] = null;
        }

        return [
            'typ' => $form->type,
            'einzahl' => __($form->settings()['einzahl']),
            'kunde' => $this->objekte->kunde($form->customer()),
            'felder' => $felder,
            'werte' => (object) array_intersect_key($werte, $felder->keyBy('name')->all()),
            'geheim_gesetzt' => (object) $gesetzt,
            'hat_datei' => $felder->contains('art', 'datei'),
            'hat_erzeuger' => isset($form->settings()['erzeuger']),
        ];
    }

    private function pflicht(mixed $regel): bool
    {
        $teile = is_string($regel) ? explode('|', $regel) : (array) $regel;

        return in_array('required', $teile, true);
    }
}
