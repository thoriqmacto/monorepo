<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | This is an API-only backend: the frontend is a separate Next.js app, and
    | Laravel renders no Blade views of its own. The app view path list is
    | therefore deliberately empty -- there is no resources/views directory to
    | point at, and declaring that here is what keeps `view:cache` (and the
    | `optimize` command that calls it) working instead of failing with
    | "The .../resources/views directory does not exist".
    |
    | The framework's own templates still resolve: the mail markdown views used
    | by the password-reset and email-verification notifications live under the
    | `mail::` namespace, registered as package hints at boot rather than found
    | through these paths.
    |
    | If you ever do need a Blade view of your own (a status page, a customised
    | mail template), create resources/views and add the path back:
    |
    |     'paths' => [resource_path('views')],
    |
    */

    'paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Where the Blade compiler writes the framework templates it compiles.
    | Still required: mail rendering compiles vendor views into this directory.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

];
