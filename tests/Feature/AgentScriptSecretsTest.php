<?php

use Symfony\Component\Finder\Finder;

// Security audit candidates around the agent scripts: the bash agents put
// their token on the curl command line (visible to every local user via ps),
// and on Windows PowerShell 5.1 -ZertifikatIgnorieren switched certificate
// checks off for the upload to DokuVault as well. Guards for scripts to come.

test('no bash agent script passes a secret header on the command line', function () {
    $found = [];

    foreach (Finder::create()->files()->in(resource_path('agents'))->name('*.sh') as $file) {
        foreach (file($file->getRealPath()) as $number => $line) {
            if (preg_match('/-H\s+["\']Authorization:/i', $line)) {
                $found[] = $file->getRelativePathname().':'.($number + 1);
            }
        }
    }

    expect($found)->toBe([], 'Use -K <(printf ...) instead of -H for: '.implode(', ', $found));
});

test('a PowerShell script that can ignore certificates restores the check before the upload', function () {
    foreach (Finder::create()->files()->in(resource_path('agents'))->name('*.ps1') as $file) {
        $script = $file->getContents();

        if (! str_contains($script, 'New-Object AlleZertifikate')) {
            continue;
        }

        $restore = strpos($script, '[System.Net.ServicePointManager]::CertificatePolicy = $OriginalCertificatePolicy');
        $upload = strpos($script, 'Invoke-RestMethod -Method Post -Uri $ApiUrl');

        expect($restore)->not->toBeFalse($file->getFilename().' never restores the certificate policy')
            ->and($restore)->toBeLessThan($upload, $file->getFilename().' restores it only after the upload');
    }
});
