<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Verschickt Mail und verraet ueber die Antwort, ob es den Nutzer gibt.
    // Beides taugt zum Ausprobieren, also gedrosselt.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // Hier laesst sich das Zuruecksetz-Token raten - dieselbe Bremse.
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.store');

    // Zwischen Kennwort und Einmalcode ist der Nutzer nicht angemeldet -
    // deshalb steht der zweite Schritt hier bei den Gaesten und nicht bei den
    // angemeldeten Routen.
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.login');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

    Route::post('two-factor-challenge/abbrechen', [TwoFactorChallengeController::class, 'destroy'])
        ->name('two-factor.abbrechen');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    // Kennwortabfrage vor heiklen Schritten: mit uebernommener Sitzung koennte
    // man hier sonst das Kennwort des Opfers durchprobieren.
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:5,15');

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Die zweite Stufe im eigenen Profil. Nur fuer den eigenen Zugang - einen
    // fremden richtet niemand ein.
    Route::post('two-factor', [TwoFactorController::class, 'begin'])
        ->name('two-factor.begin');

    Route::post('two-factor/verwerfen', [TwoFactorController::class, 'cancel'])
        ->name('two-factor.verwerfen');

    Route::post('two-factor/bestaetigen', [TwoFactorController::class, 'confirm'])
        ->name('two-factor.confirm');

    Route::post('two-factor/wiederherstellungscodes', [TwoFactorController::class, 'regenerate'])
        ->name('two-factor.codes');

    Route::delete('two-factor', [TwoFactorController::class, 'destroy'])
        ->name('two-factor.destroy');
});
