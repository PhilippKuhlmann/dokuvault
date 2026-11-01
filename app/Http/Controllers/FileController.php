<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\File;
use App\Models\Setting;
use App\Support\FileName;
use App\Support\StoragePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Uploading, downloading and deleting files.
 *
 * The list itself is Livewire (App\Livewire\FileList) - it has search,
 * filters and sorting, and that cannot be built without a re-render.
 */
class FileController extends Controller
{
    /**
     * The page - the list in it is Livewire (App\Livewire\FileList). The
     * controller supplies only the layout, which needs the customer.
     */
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', File::class);

        return view('file.index', compact('customer'));
    }

    public function store(Customer $customer, Request $request)
    {
        $this->authorize('create', File::class);

        // The display name is capped; for the storage path it is reduced to
        // harmless characters in FileName::fuer() - there together with the
        // extension, which comes from the browser just the same.
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.Setting::uploadMaxKb(),
                'mimes:'.implode(',', Setting::fileFormats()),
            ],
            'name' => ['required', 'string', 'max:255'],
        ], [], ['file' => __('Datei'), 'name' => __('Name')]);

        $file = $request->file('file');
        $fileName = FileName::fuer($file, $validated['name']);
        $filePath = $file->storeAs($customer->slug.'/files', $fileName, 'local');

        $customer->files()->create([
            'file_path' => $filePath,
            'name' => $validated['name'],
            'extension' => FileName::extension($file),
            // Written along on upload: reading it from disk later would be a
            // file access per row of the list.
            'size' => $file->getSize(),
        ]);

        return redirect('/'.$customer->slug.'/file');
    }

    public function download(Customer $customer, File $file)
    {
        $this->authorize('viewAny', File::class);

        abort_unless(StoragePath::belongsTo($file->file_path, $customer, 'files'), 404);

        $name = $file->name.'.'.$file->extension;

        // nosniff: the browser should not interpret the content itself. With
        // "attachment" the file ends up in the download anyway, but the header
        // costs nothing and stands at the other output places too.
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
