<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $invitation = WorkspaceInvitation::pendingFromToken(session('invitation_token'));

        if (config('board.registration_invite_only') && ! $invitation) {
            throw ValidationException::withMessages([
                'email' => "L'inscription se fait uniquement sur invitation.",
            ]);
        }

        if ($invitation && strcasecmp($invitation->email, $input['email']) !== 0) {
            throw ValidationException::withMessages([
                'email' => "Cette invitation est destinée à {$invitation->email}.",
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        if ($invitation) {
            // The invitee proved control of this address by following the e-mail
            // link, so mark it verified and add them to the workspace.
            $user->forceFill(['email_verified_at' => now()])->save();

            $invitation->workspace->members()->syncWithoutDetaching([
                $user->id => ['role' => $invitation->role],
            ]);
            $invitation->update(['accepted_at' => now()]);

            session()->forget('invitation_token');
        }

        return $user;
    }
}
