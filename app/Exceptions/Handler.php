<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Psr\Log\LogLevel;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        // Were commented out for the old object forms, so a documented
        // password survived a validation error. Those forms are Livewire now
        // and flash nothing; what is left are account passwords - which then
        // sat in plain text in the session after every failed validation.
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // Every secret column as well (BMC password, mail password, ...): a
        // classic form with such a field would otherwise flash it, and the
        // session would hold it in plain text.
        $this->dontFlash = array_values(array_unique([
            ...$this->dontFlash,
            ...config('custom.secret_columns', []),
        ]));

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
