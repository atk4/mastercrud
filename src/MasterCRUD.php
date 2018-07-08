<?php

namespace atk4\mastercrud;

class MasterCRUD extends \atk4\ui\View
{
    public $crumb = null;

    // the top-most model
    public $rootModel;

    // Tab Label for detail.
    public $detailLabel = 'Details';

    public $_missingProperty = [];


    function setMissingProperty($property, $value) {
        $this->_missingProperty[$property] = $value;
    }

    function init() {
        if (!$this->crumb) {
            $this->crumb = $this->add(['BreadCrumb', 'Unspecified', 'big']);
        }
        $this->add(['ui'=>'divider']);

        parent::init();
    }

    public function setModel(\atk4\data\Model $m, $defs = null)
    {

        $this->rootModel = $m;

        $this->crumb->addCrumb($this->getCaption($m), $this->url());

        $this->path = explode('/', $this->stickyGet('path'));
        if ($this->path[0] == '') {
            unset($this->path[0]);
        }

        $defs = $this->traverseModel($this->path, $defs);

        $arg_name = $this->model->table.'_id';
        $arg_val = $this->stickyGet($arg_name);
        if ($arg_val && $this->model->tryLoad($arg_val)->loaded()) {
            $this->initTabs($defs);
        } else {
            $this->initCrud($defs);
        }

        $this->crumb->popTitle();

        return $this->rootModel;
    }

    function getCaption($m)
    {
        return $m->getModelCaption();
    }

    function getTitle($m)
    {
        return $m[$m->title_field];
    }

    function initTabs($defs)
    {

        $m = $this->model; 

        $this->tabs = $this->add('Tabs');

        //var_Dump($this->url());
        //var_Dump($this->tabs->url());
        $this->tabs->stickyGet($this->model->table.'_id');

        if ($this->getCaption($this->rootModel) !== $this->getCaption($m)) {
            $this->crumb->addCrumb($this->getCaption($m), $this->tabs->url());
        }
        $this->crumb->addCrumb($this->getTitle($m), $this->tabs->url());

        $form = $this->tabs->addTab($this->detailLabel)->add('Form');
        $form->setModel($this->model);

        if (!$defs) {
            return;
        }


        foreach($defs as $ref=>$subdef) {
            $m = $this->model->ref($ref);

            $this->tabs->addTab($this->getCaption($m), function($p) use($subdef, $m, $ref) {

                $this->sub_crud = $p->add($this->getCRUDSeed($subdef));

                $this->sub_crud->setModel($m);
                $t = $p->urlTrigger ?: $p->name;
                $this->sub_crud->addDecorator($m->title_field, ['Link', [$t=>false, 'path'=>$this->getPath($ref)], [$m->table.'_id'=>'id']]);

            });
        }
    }

    /**
     * Provided with a relative path, add it to the current one
     * and return string
     */
    function getPath($rel) {
        $path = $this->path;

        if (!is_array($rel)) {
            $rel = explode('/', $rel);
        }

        foreach($rel as $rel_one) {
            if ($rel_one == '..') {
                array_pop($path);
                continue;
            }

            if ($rel_one == '') {
                $path = [];
                continue;
            }

            $path[] = $rel_one;
        }


        $res = join('/', $path);
        return $res == '' ? false : $res;
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
        $seed = isset($defs[0])? $defs[0]: [];
        $result= $this->mergeSeeds(
            $seed,
            $this->_missingProperty, 
            [ 'CRUD', ]
        );
        return $result;
    }

    /**
     * Given a path and arguments, find and load the right 
     * model
     */
    public function traverseModel($path, $defs)
    {
        $m = $this->rootModel;

        $path_part = [''];

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
            $m->load($arg_val);

            if ($this->getCaption($this->rootModel) !== $this->getCaption($m)) {
                $this->crumb->addCrumb($this->getCaption($m), $this->url(['path'=>$this->getPath($path_part)]));
            }
            $this->crumb->addCrumb($this->getTitle($m), $this->url([
                'path'=>$this->getPath($path_part)
            ]));


            $m = $m->ref($p);


            $path_part[]=$p;
        }

        parent::setModel($m);

        return $defs;
    }
}
