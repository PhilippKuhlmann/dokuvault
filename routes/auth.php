<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InitialSetupController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Sends mail and gives away through the response whether the user
    // exists. Both are worth trying out, so it is throttled.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // The reset token can be guessed here - the same brake.
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('password.store');

    // Invitation: the new user sets their own password. Throttled like the
    // reset - the same guessable token is behind it.
    Route::get('invitation/{token}', [InvitationController::class, 'create'])
        ->name('invitation.form');

    // The address was 'einladung/{token}' until 31 October 2026. Invitations
    // already sent are valid for a week; their links must not lead nowhere.
    Route::permanentRedirect('einladung/{token}', 'invitation/{token}');

    Route::post('invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('invitation.store');

    // First administrator of a fresh install - only while setup is pending,
    // otherwise 404. Throttled: the setup code can be guessed.
    Route::get('setup', [InitialSetupController::class, 'create'])
        ->name('setup');

    Route::post('setup', [InitialSetupController::class, 'store'])
        ->middleware('throttle:5,15')
        ->name('setup.store');

    // Between password and one-time code the user is not signed in - hence
    // the second step stands here with the guests and not with the
    // authenticated routes.
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.login');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

    Route::post('two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'destroy'])
        ->name('two-factor.cancel');
});

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // The second factor in the own profile. Only for the own account -
    // nobody sets one up for someone else.
    Route::post('two-factor', [TwoFactorController::class, 'begin'])
        ->name('two-factor.begin');

    Route::post('two-factor/cancel', [TwoFactorController::class, 'cancel'])
        ->name('two-factor.discard');

    // Throttled like every other place a code is typed: without it, whoever
    // holds the session could guess the six digits for a pending secret.
    Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])
        ->middleware('throttle:5,15')
        ->name('two-factor.confirm');

    Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])
        ->name('two-factor.codes');

    Route::delete('two-factor', [TwoFactorController::class, 'destroy'])
        ->name('two-factor.destroy');
});
