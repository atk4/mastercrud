<?php

declare(strict_types=1);

include 'init.php';

use atk4\mastercrud\MasterCRUD;
use atk4\ui\CRUD;

$app->cdn['atk'] = '../public';
$mc = $app->add([
    MasterCRUD::class,
    'ipp' => 5,
    'quickSearch' => ['name'],
]);
$mc->setModel(
    new Client($app->db),
    [
        'Invoices' => [
            'Lines' => [
                ['_crud' => [CRUD::class, 'displayFields' => ['item', 'total']]],
            ],
            'Allocations' => [],
        ],
        'Payments' => [
            'Allocations' => [],
        ],
    ]
);
