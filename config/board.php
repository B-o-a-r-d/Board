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

    /*
    |--------------------------------------------------------------------------
    | Public board sharing
    |--------------------------------------------------------------------------
    |
    | When enabled, board admins can generate a public, read-only share link
    | (a URL token) that lets anyone view the board and its cards without an
    | account. Set to false to remove the feature entirely for the instance.
    |
    */

    'public_sharing' => env('PUBLIC_SHARING', true),

];
