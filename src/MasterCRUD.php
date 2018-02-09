<?php

namespace atk4\mastercrud;

class MasterCRUD extends \atk4\ui\View
{
    public $crumb;

    // the top-most model
    public $rootModel;

    // details
    public $details;

    public $_missingProperty = [];


    function setMissingProperty($property, $value) {
        $this->_missingProperty[$property] = $value;
    }

    function init() {
        $this->crumb = $this->add(['BreadCrumb', 'Unspecified', 'big']);
        $this->add(['ui'=>'divider']);

        parent::init();
    }

    public function setModel(\atk4\data\Model $m, $defs = null)
    {

        $this->rootModel = $m;

        $this->crumb->addCrumb($this->getCaption($m), $this->url());

        $this->path = explode('/', $this->stickyGet('path'));

        $defs = $this->traverseModel($this->path, $defs);

        $this->crumb->set($this->getCaption($m));

        $arg_name = $m->table.'_id';
        $arg_val = $this->stickyGet($arg_name);
        if ($arg_val && $m->tryLoad($arg_val)->loaded()) {


            $this->initTabs($defs);
        } else {
            $this->initCrud($defs);
        }


        $pp = array_pop($this->crumb->path);
        $this->crumb->set($pp['section']);

        return $this->rootModel;
    }

    function getCaption($m)
    {
        return isset($m->title) ? $m->title : preg_replace('|.*\\\|', '', get_class($m));
    }

    function initTabs($defs)
    {

        $m = $this->model; 

        $this->tabs = $this->add('Tabs');
        $this->tabs->stickyGet($this->model->table.'_id');
        $this->crumb->addCrumb($m[$m->title_field], $this->tabs->url());

        $form = $this->tabs->addTab('Details')->add('Form');
        $form->setModel($this->model);

        if (!$defs) {
            return;
        }


        foreach($defs as $ref=>$subdef) {
            $m = $this->model->ref($ref);

            $this->tabs->addTab($this->getCaption($m), function($p) use($subdef, $m, $ref) {


                $p->add(['Button', 'cickme'])->on('click', function() { return 'ouch'; });;
                $this->sub_crud = $p->add('CRUD');

                $this->sub_crud->setModel($m);
                $t = $p->urlTrigger ?: $p->name;
                $this->sub_crud->addDecorator($m->title_field, ['Link', [$t=>false, 'path'=>join('/',$this->path).'/'.$ref], [$m->table.'_id'=>'id']]);

            });
        }
    }

    function initCrud($defs, $p = null) {
        if ($p === null) {
            $p = $this;
        }

        $this->crud = $p->add($this->getCRUDSeed($defs));
        $this->crud->setModel($this->model);
        $this->crud->addDecorator($this->model->title_field, ['Link', [], [$this->model->table.'_id'=>'id']]);

    }

    function getCRUDSeed($defs)
    {
        $extra = isset($defs[0])? $defs[0]: [];
        return $this->mergeSeeds($this->_missingProperty, [
            'CRUD',
        ], $extra);
    }

    /**
     * Given a path and arguments, find and load the right 
     * model
     */
    public function traverseModel($path, $defs)
    {
        $m = $this->rootModel;

        foreach($path as $p) {

            if (!$p) {
                continue;
            }

            if (!isset($defs[$p])) {
                throw new Exception(['Path is not defined', 'path'=>$path, 'defs'=>$defs]);
            }

            $defs = $defs[$p];

            // argument of a current model should be passed if we are traversing
            $arg_name = $m->table.'_id';

            $arg_val = $this->app->stickyGet($arg_name);

            if ($arg_val === null) {
                throw new \atk4\ui\Exception(['Argument value is not specified', 'arg'=>$arg_name]);
            }

            // load record and traverse
            $m = $m->load($arg_val)->ref($p);
        }

        parent::setModel($m);

        return $defs;
    }
}
