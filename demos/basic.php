<?php

declare(strict_types=1);

namespace Atk4\MasterCrud\Demo;

include 'init.php';

use Atk4\MasterCrud\MasterCrud;
use Atk4\Ui\Crud;

$mc = MasterCrud::addTo($app, ['ipp' => 5, 'quickSearch' => ['name']]);
$mc->setModel(
    new Client($app->db),
    [
        'Invoices' => [
            'Lines' => [
                ['_crud' => [Crud::class, 'displayFields' => ['item', 'total']]],
            ],
            'Allocations' => [],
        ],
        'Payments' => [
            'Allocations' => [],
        ],
    ]
);
