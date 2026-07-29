<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backend Projects
    |--------------------------------------------------------------------------
    |
    | List of backend repositories in "organization/repo-name" format.
    | Each must have a corresponding cloned directory at the same level
    | as sei-deploy, named with the repo basename.
    |
    | To add a new backend, add it to this array and commit.
    | The webhook deploy will pick up the change automatically.
    |
    */

    'backends' => [
        'moonslayers/cef-backend',
        'moonslayers/pmr-backend',
        'moonslayers/sagem-backend',
        'moonslayers/sidi-backend',
        'moonslayers/vdin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backend Path Mappings (for monorepos)
    |--------------------------------------------------------------------------
    |
    | Map "organization/repo" to a subdirectory where the backend lives.
    | Only needed when the backend is not in the repo root.
    | Key: the full repo name as listed in 'backends'.
    | Value: the subdirectory relative to the repo root.
    |
    | Example: 'moonslayers/vdin' => 'vdin/vdin-api'
    | means the repo is cloned as ../vdin/ and the backend is in ../vdin/vdin-api/.
    |
    */
    'backend_paths' => [
        'moonslayers/vdin' => 'vdin/vdin-api',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backend Custom Commands
    |--------------------------------------------------------------------------
    |
    | Override the default artisan migrate command per backend.
    | If a backend is not listed here, it will use the default:
    |   php artisan migrate --force
    |
    | Key: the full repo name as listed in 'backends'.
    | Value: the artisan migrate command (without "php artisan" prefix).
    |
    | Example: 'moonslayers/vdin' => 'migrate:fresh --seed'
    | will run: php artisan migrate:fresh --seed
    |
    */

    'backend_commands' => [
        'moonslayers/vdin' => 'migrate:fresh --seed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Projects
    |--------------------------------------------------------------------------
    |
    | List of frontend folder names inside ../sei-compilados.
    | Each will be rsynced to /var/www/<folder-name>.
    |
    | To add a new frontend, add it to this array and commit.
    |
    */

    'frontends' => [
        'cef-frontend',
        'pmr-frontend',
        'sagem-frontend',
        'sidi-frontend',
        'vdin-ui',
    ],

];
