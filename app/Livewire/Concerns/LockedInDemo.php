<?php

namespace App\Livewire\Concerns;

/**
 * A settings page that is only shown in the public demo: every request
 * after the first render - a changed field, a button - is refused there.
 * See App\Http\Middleware\BlockInDemo for why; the view disables the
 * fields with <x-demo-locked>.
 */
trait LockedInDemo
{
    public function hydrateLockedInDemo(): void
    {
        abort_if((bool) config('app.demo'), 403, __('In der Demo ist das gesperrt.'));
    }
}
