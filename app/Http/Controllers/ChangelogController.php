<?php

namespace App\Http\Controllers;

class ChangelogController extends Controller
{
    public function index()
    {
        $changelog = file_get_contents(base_path('changelog.md'));

        preg_match('/## (\d{2}\.\d{2}\.\d{2})/', $changelog, $matches);

        $version = $matches[1] ?? 'Unbekannt';

        return view('changelog', compact('version', 'changelog'));
    }
}
