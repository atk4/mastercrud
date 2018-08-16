[ATK UI](https://github.com/atk4/ui) is a UI library for building UI interfaces that has a built-in [CRUD](http://ui.agiletoolkit.org/demos/crud.php) component. It can be used to create complex admin systems, but it requires you to populate multiple pages and inter-link them together yourself. 

![mastercrud](docs/images/mastercrud.png)

**MasterCRUD** is an add-on for ATK UI and ATK Data, which will orchestrate navigation between multiple CRUD pages by respecting relations and conditions. You can use **MasterCRUD** to:

-   Manage list of clients, and their individual invoices and payments.
-   Manage user groups and users within them
-   Manage multi-level catalogue and products in them

The syntax of **MasterCRUD** is incredibly simple and short. It automatically takes care of many details like:

-   record and track `id` of various records you have clicked on (BreadCrumb)
-   display multi-Tab pages with model details and optional relations
-   support `hasOne` and `hasMany` relations
-   allow flexible linking to a higher tree level (user - invoice - allocated_payment -> payment (drops invoice_id))

**MasterCRUD** can also be extended to contain your own views, you can interact with the menu and even place **MasterCRUD** inside a more complex layouts.

### Example Use Case (see demos/clients.php for full demo):

MasterCRUD operates like a regular CRUD, and you can easily substitute it in:

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client');
```

You'll noticed that you can now click on the client name to get full details about this client. Next, we want to be able to see and manage Client invoices:

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client', ['Invoices'=>[]]);
```

This will add 2nd tab to the "Client Details" screen listing invoices of said client. If you invoice is further broken down into "Lines", you can go one level deeper:

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client', ['Invoices'=>['Lines'=>[]]]);
```

If `Client hasMany('Payments')` then you can also add that relation:

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client', ['Invoices'=>['Lines'=>[]], 'Payments'=>[]]);
```

So far I've shown you examples of "hasMany" relations, but it's possible to also traverse "hasOne". I am going to clean up the above example into this:

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client', [
  'Invoices'=>[
    'Lines'=>[]
  ], 
  'Payments'=>[]
]);
```

Suppose that `Invoice hasMany(Allocation)`and `Payment hasMany(Allocation)` while allocation can have one Payment and one Invoice.

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client', [
  'Invoices'=>[
    'Lines'=>[],
    'Allocations'=>[]
  ], 
  'Payments'=>[
    'Allocations'=>[]
  ]
]);
```

That's cool, but if you go through the route of `Invoice -> allocation ->` you should be able to click on the "payment":

``` php
$crud = $app->add('\atk4\mastercrud\MasterCRUD');
$crud->setModel('Client', [
  'Invoices'=>[
    'Lines'=>[],
    'Allocations'=>[
      'payment_id'=>['path'=>'Payments', 'payment_id'=>'payment_id']
    ]
  ], 
  'Payments'=>[
    'Allocations'=>[
      'invoice_id'=>['path'=>'Invoices', 'invoice_id'=>'invoice_id']
    ]
  ]
]);
```

Now you will be able to jump from `Invoice->allocation` to `Payment` and other way around.

### Installation

Install through composer (`composer require atk4/mastercrud`).


