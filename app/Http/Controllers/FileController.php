<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\File;
use App\Models\Setting;
use App\Support\Dateiname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Hochladen, Herunterladen und Loeschen von Dateien.
 *
 * Die Liste selbst ist Livewire (App\Livewire\DateiListe) - sie hat Suche,
 * Filter und Sortierung, und das laesst sich ohne Rerender nicht bauen.
 */
class FileController extends Controller
{
    /**
     * Die Seite - die Liste darin ist Livewire (App\Livewire\DateiListe).
     * Der Controller liefert nur das Layout, das den Kunden braucht.
     */
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', File::class);

        return view('file.index', compact('customer'));
    }

    public function store(Customer $customer, Request $request)
    {
        $this->authorize('create', File::class);

        // Der Anzeigename wird begrenzt; fuer den Ablagepfad wird er in
        // Dateiname::fuer() auf unbedenkliche Zeichen reduziert - dort
        // zusammen mit der Endung, die genauso aus dem Browser kommt.
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.Setting::uploadMaxKb(),
                'mimes:'.implode(',', config('custom.datei_formate')),
            ],
            'name' => ['required', 'string', 'max:255'],
        ], [], ['file' => __('Datei'), 'name' => __('Name')]);

        $file = $request->file('file');
        $fileName = Dateiname::fuer($file, $validated['name']);
        $filePath = $file->storeAs($customer->slug.'/files', $fileName, 'local');

        $customer->files()->create([
            'file_path' => $filePath,
            'name' => $validated['name'],
            'extension' => $file->getClientOriginalExtension(),
            // Beim Hochladen mitschreiben: Sie spaeter von der Platte zu lesen
            // waere ein Dateizugriff je Zeile der Liste.
            'size' => $file->getSize(),
        ]);

        return redirect('/'.$customer->slug.'/file');
    }

    public function download(Customer $customer, File $file)
    {
        $this->authorize('viewAny', File::class);

        $name = $file->name.'.'.$file->extension;

        // nosniff: Der Browser soll den Inhalt nicht selbst deuten. Mit
        // "attachment" landet die Datei ohnehin im Download, aber die Angabe
        // kostet nichts und steht an den anderen Ausgabestellen auch.
        return Storage::download($file->file_path, $name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Customer $customer, File $file)
    {
        $this->authorize('delete', File::class);

        Storage::delete($file->file_path);
        $file->delete();

        return redirect('/'.$customer->slug.'/file');
    }
}
