<?php

declare(strict_types=1);

require '../vendor/autoload.php';
require 'db.php';

use atk4\data\Model;
use atk4\data\Persistence;
use atk4\ui\App;
use atk4\ui\Layout;
use atk4\ui\Message;

$app = new App('MasterCRUD Demo');
$app->initLayout([Layout\Centered::class]);

// change this as needed
try {
    $app->db = new Persistence\Sql('pgsql://root:root@localhost/root');
} catch (\Exception $e) {
    $app->add([Message::class, 'Database is not available', 'error'])->text
        ->addParagraph('Import file demos/mastercrud.pgsql and see demos/db.php')
        ->addParagraph($e->getMessage());
    exit;
}

class Client extends Model
{
    public $table = 'client';

    protected function init(): void
    {
        parent::init();

        $this->addField('name', ['required' => true]);
        $this->addField('address', ['type' => 'text']);

        $this->hasMany('Invoices', new Invoice());
        $this->hasMany('Payments', new Payment());
    }
}

class Invoice extends Model
{
    public $table = 'invoice';
    public $title_field = 'ref_no';

    protected function init(): void
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

class Line extends Model
{
    public $table = 'line';
    public $title_field = 'item';

    protected function init()
    {
        parent::init();

        $this->hasOne('invoice_id', new Invoice());
        $this->addField('item');
        $this->addField('qty', ['type' => 'integer']);
        $this->addField('price', ['type' => 'money']);

        $this->addExpression('total', '[qty]*[price]');
    }
}

class Payment extends Model
{
    public $table = 'payment';
    public $title_field = 'ref_no';

    protected function init()
    {
        parent::init();

        $this->hasOne('client_id', new Client());

        $this->addField('ref_no');
        $this->addField('status', ['enum' => ['draft', 'allocated', 'partial']]);
        $this->addField('amount', ['type' => 'money']);

        $this->hasMany('Allocations', new Allocation());
    }
}

class Allocation extends Model
{
    public $table = 'allocation';
    public $title_field = 'title';

    protected function init()
    {
        parent::init();

        $this->addExpression('title', '\'Alloc \' || [id]');

        $this->hasOne('payment_id', new Payment());
        $this->hasOne('invoice_id', new Invoice());
        $this->addField('allocated', ['type' => 'money']);
    }
}
