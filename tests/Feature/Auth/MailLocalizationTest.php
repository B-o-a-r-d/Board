<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

test('the email verification notification is in French', function () {
    $user = User::factory()->create();

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Vérifiez votre adresse e-mail')
        ->and($mail->actionText)->toBe('Vérifier mon adresse e-mail')
        ->and($mail->introLines[0])->toContain('Merci de votre inscription');
});

test('the password reset notification is in French', function () {
    $user = User::factory()->create();

    $mail = (new ResetPassword('token-123'))->toMail($user);

    expect($mail->subject)->toBe('Réinitialisation de votre mot de passe')
        ->and($mail->actionText)->toBe('Réinitialiser le mot de passe')
        ->and($mail->introLines[0])->toContain('réinitialisation');
});
