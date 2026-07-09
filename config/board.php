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

    /*
    |--------------------------------------------------------------------------
    | iCal feeds
    |--------------------------------------------------------------------------
    |
    | Read-only calendar feeds (per board and per user) exposed at a signed,
    | revocable token URL so cards' dates can be subscribed to from an external
    | calendar. Set to false to remove the feature entirely for the instance.
    |
    */

    'ical_feeds' => env('ICAL_FEEDS', true),

    /*
    |--------------------------------------------------------------------------
    | Board backgrounds
    |--------------------------------------------------------------------------
    |
    | Preset backgrounds a board admin can pick from. The key is stored on the
    | board (boards.background); the value is the CSS applied behind the lists.
    | Keeping this an allow-list avoids storing arbitrary CSS from the client.
    |
    */

    'backgrounds' => [
        'indigo' => 'linear-gradient(135deg, #6366f1, #4338ca)',
        'ocean' => 'linear-gradient(135deg, #0ea5e9, #2563eb)',
        'sunset' => 'linear-gradient(135deg, #fb923c, #db2777)',
        'forest' => 'linear-gradient(135deg, #10b981, #047857)',
        'rose' => 'linear-gradient(135deg, #fb7185, #be123c)',
        'slate' => 'linear-gradient(135deg, #64748b, #1e293b)',
    ],

];
