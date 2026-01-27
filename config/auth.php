<?php

return [

	/*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

	'defaults' => [
		'guard' => 'web',
		'passwords' => 'users',
	],

	/*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "session", "token", "jwt"
    */

	'guards' => [
		'web' => [
			'driver'  => 'session',
			'provider' => 'users',
		],

		'api' => [
			'driver'  => 'jwt',
			'provider' => 'users',
			'hash'    => false,
		],
	],

	/*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Supported: "database", "eloquent"
    */

	'providers' => [
		'users' => [
			'driver' => 'eloquent',
			'model'  => App\Models\User::class,
		],

		// Exemple si tu voulais utiliser la table directement :
		// 'users' => [
		//     'driver' => 'database',
		//     'table'  => 'users',
		// ],
	],

	/*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

	'passwords' => [
		'users' => [
			'provider' => 'users',
			'table'    => 'password_resets',
			'expire'   => 60,
			'throttle' => 60,
		],
	],

	/*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

	'password_timeout' => 10800,

];
