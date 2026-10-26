<?php

namespace App\Support;

use App\Models\Customer;

/**
 * Stored file paths point into one disk shared by all customers. A path that
 * once came from a form field (licence file_path) could point at another
 * customer's files or at the PDF exports with all their credentials - so
 * every download and delete checks that the path stays in the owner's folder.
 */
class StoragePath
{
    public static function belongsTo(?string $path, ?Customer $customer, ?string $folder = null): bool
    {
        if (blank($path) || ! $customer || blank($customer->slug)) {
            return false;
        }

        if (str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/')) {
            return false;
        }

        $prefix = $customer->slug.'/'.($folder !== null ? $folder.'/' : '');

        return str_starts_with($path, $prefix);
    }
}
