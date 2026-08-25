<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\File;
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

        // Validierung: 'file' ist Pflicht, 'name' wird begrenzt und beim
        // Aufbau des Dateipfads zusaetzlich auf unbedenkliche Zeichen
        // reduziert (kein Path-Traversal ueber den Anzeigenamen moeglich).
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $validated['name']);
        $fileName = time().'_'.$safeName.'.'.$file->getClientOriginalExtension();
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

        return Storage::download($file->file_path, $name);
    }

    public function destroy(Customer $customer, File $file)
    {
        $this->authorize('delete', File::class);

        Storage::delete($file->file_path);
        $file->delete();

        return redirect('/'.$customer->slug.'/file');
    }
}
