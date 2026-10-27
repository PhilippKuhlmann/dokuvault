<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

// Security audit candidate: the link in the "forgot password" mail was built
// from the Host header of the request. Whoever sent the request with their
// own domain as Host got a mail to the victim whose link pointed to them -
// one click and the reset token was theirs.

test('the reset link points to APP_URL, whatever Host the request names', function () {
    Notification::fake();
    config(['app.url' => 'https://doku.example.test']);
    $user = User::factory()->create(['email' => 'opfer@example.test']);

    $this->post('http://evil.example/forgot-password', ['username' => $user->username]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        expect($url)->toStartWith('https://doku.example.test/')
            ->not->toContain('evil.example');

        return true;
    });
});
