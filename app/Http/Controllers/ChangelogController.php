<?php

namespace App\Http\Controllers;

use App\Support\Changelog;

class ChangelogController extends Controller
{
    public function index()
    {
        return view('changelog', [
            'version' => Changelog::version(),
            'changelog' => Changelog::roh(),
        ]);
    }
}
