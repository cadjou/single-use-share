<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'settings#get', 'url' => '/shares/{shareId}', 'verb' => 'GET', 'requirements' => ['shareId' => '\d+']],
		['name' => 'settings#save', 'url' => '/shares/{shareId}', 'verb' => 'PUT', 'requirements' => ['shareId' => '\d+']],
	],
];
