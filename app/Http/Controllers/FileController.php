<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\File;
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

        $filePath = '';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $request->name . '.' . $file->getClientOriginalExtension();
            $filePath = $request->file('file')->storeAs($customer->slug . '/files', $fileName, 'local');

            $customer->files()->create([
                'file_path' => $filePath,
                'name' => $request->name,
                'extension' => $request->file->getClientOriginalExtension(),
            ]);
        }else{
            return redirect('/' . $customer->slug . '/file')->withErrors('Fehler');
        }



        return redirect('/' . $customer->slug . '/file');
    }

    public function download(Customer $customer, File $file)
    {
        $this->authorize('viewAny', File::class);

        $name = $file->name . '.' . $file->extension;

        return Storage::download($file->file_path, $name);
    }

    public function destroy(Customer $customer, File $file)
    {
        $this->authorize('delete', File::class);

        Storage::delete($file->file_path);
        $file->delete();

        return redirect('/' . $customer->slug . '/file');
    }
}
