<?php

return [
	'paths' => ['api/*', 'sanctum/csrf-cookie'],

	'allowed_methods' => ['*'],

	'allowed_origins' => ['*'], // Izinkan semua asal (Frontend HTML)

	'allowed_origins_patterns' => [],

	'allowed_headers' => ['*'],
];