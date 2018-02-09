<?php
include'init.php';

$app->cdn['atk'] = '../public';
$app->add([
    '\atk4\mastercrud\MasterCRUD',
    'ipp'=>5,
    'quickSearch'=>['name'],
])->setModel(new Client($app->db),
    ['Invoices'=>[]]
);
