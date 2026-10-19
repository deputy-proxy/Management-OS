<?php

return [

    'models' => [
        'role' => \Hexters\HexaLite\Models\HexaRole::class,
        'user' => \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Super administrator role
    |--------------------------------------------------------------------------
    |
    | This exact role name (matched case-insensitively) bypasses individual
    | permission keys. Keep it restricted to trusted system owners.
    |
    */
    'super_admin_role' => env('HEXA_SUPER_ADMIN_ROLE', 'Owner'),

    /*
    |--------------------------------------------------------------------------
    | Navigation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the navigation label and group for the Role & Permissions menu item.
    | You can use translation keys (e.g., 'navigation.groups.settings') or plain strings.
    | Use closures for dynamic translation: fn() => __('navigation.groups.settings')
    |
    */

    'navigation' => [
        'label' => 'Role & Permissions',
        'group' => 'System',
        'order'  => '4'
    ],

];
