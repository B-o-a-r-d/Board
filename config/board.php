<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invite-only registration
    |--------------------------------------------------------------------------
    |
    | When enabled, the public registration route is closed: an account can
    | only be created through a valid workspace invitation link. This keeps
    | bots and spam sign-ups out of the application.
    |
    */

    'registration_invite_only' => env('REGISTRATION_INVITE_ONLY', false),

];
