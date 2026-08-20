<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', File::class);

        // Dateien haben keinen Standortbezug (keine site_id-Spalte), daher bewusst
        // ohne getFilteredQuery: der Standortfilter der Seitenleiste gilt hier nicht.
        $files = File::where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->paginate(25);

        return view('file.index', compact('customer', 'files'));
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
