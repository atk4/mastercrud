<?php

require '../vendor/autoload.php';
require 'db.php';

$app = new \atk4\ui\App('MasterCRUD Demo');
$app->initLayout('Centered');

// change this as needed
try {
    $app->dbConnect('pgsql://root:root@localhost/root');
} catch (\Exception $e) {
    $app->add(['Message', 'Database is not available', 'error'])->text
        ->addParagraph('Import file demos/mastercrud.pgsql and see demos/db.php')
        ->addParagraph($e->getMessage());
    exit;
}

class Client extends \atk4\data\Model
{
    public $table = 'client';

    public function init()
    {
        parent::init();

        $this->addField('name', ['required' => true]);
        $this->addField('address', ['type' => 'text']);

        $this->hasMany('Invoices', new Invoice());
        $this->hasMany('Payments', new Payment());
    }
}

class Invoice extends \atk4\data\Model
{
    public $table = 'invoice';
    public $title_field = 'ref_no';

    public function init()
    {
        parent::init();

        $this->hasOne('client_id', new Client());

        $this->addField('ref_no');
        $this->addField('status', ['enum' => ['draft', 'paid', 'partial']]);

        $this->hasMany('Lines', new Line())
            ->addField('total', ['aggregate' => 'sum']);

        $this->hasMany('Allocations', new Allocation());
    }
}

class Line extends \atk4\data\Model
{
    public $table = 'line';
    public $title_field = 'item';

    public function init()
    {
        parent::init();

        $this->hasOne('invoice_id', new Invoice());
        $this->addField('item');
        $this->addField('qty', ['type' => 'integer']);
        $this->addField('price', ['type' => 'money']);

        $this->addExpression('total', '[qty]*[price]');
    }
}

class Payment extends \atk4\data\Model
{
    public $table = 'payment';
    public $title_field = 'ref_no';

    public function init()
    {
        parent::init();

        $this->hasOne('client_id', new Client());

        $this->addField('ref_no');
        $this->addField('status', ['enum' => ['draft', 'allocated', 'partial']]);
        $this->addField('amount', ['type' => 'money']);

        $this->hasMany('Allocations', new Allocation());
    }
}

class Allocation extends \atk4\data\Model
{
    public $table = 'allocation';
    public $title_field = 'title';

    public function init()
    {
        parent::init();

        $this->addExpression('title', '\'Alloc \' || [id]');

        $this->hasOne('payment_id', new Payment());
        $this->hasOne('invoice_id', new Invoice());
        $this->addField('allocated', ['type' => 'money']);
    }
}
