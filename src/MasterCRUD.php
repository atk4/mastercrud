<?php

namespace atk4\mastercrud;

use atk4\data\Model;
use atk4\ui\Card;
use atk4\ui\CardTable;
use atk4\ui\CRUD;
use atk4\ui\Tabs;
use atk4\ui\View;

class MasterCRUD extends View
{
    /** @var BreadCrumb object */
    public $crumb = null;

    /** @var \atk4\data\Model the top-most model */
    public $rootModel;

    /** @var string Tab Label for detail */
    public $detailLabel = 'Details';

    /** @var array of properties which are reserved for MasterCRUD and can't be used as model names */
    protected $reserved_properties = ['_crud', '_tabs', '_card', 'caption'];

    /** @var View Tabs view*/
    protected $tabs;

    /** @var array Default Crud for all model. You may override this value per model using $def['_crud'] in setModel */
    public $defaultCrud = [CRUD::class, 'ipp' => 25];

    /** @var array Default Tabs for all model. You may override this value per model using $def['_tabs'] in setModel */
    public $defaultTabs = [Tabs::class];

    /** @var array Default Card for all model. You may override this value per model using $def['_card'] in setModel */
    public $defaultCard = [CardTable::class];

    /**
     * Initialization.
     */
    public function init()
    {
        // add BreadCrumb view
        if (!$this->crumb) {
            $this->crumb = $this->add(['BreadCrumb', 'Unspecified', 'big']);
        }
        $this->add(['ui'=>'divider']);

        parent::init();
    }

    /**
     * Sets model.
     *
     * Use $defs['_crud'] to set seed properties for CRUD view.
     * Use $defs['_tabs'] to set seed properties for Tabs view.
     * Use $defs['_card'] to set seed properties for Card view.
     *
     * For example setting different seeds for Client and Invoice model passing seeds value in array 0.
     * $mc->setModel(new Client($app->db),
     *   [
     *       ['_crud' => ['CRUD', 'ipp' => 50]],
     *       'Invoices'=>[
     *           [
     *               '_crud' =>['CRUD', 'ipp' => 25, 'displayFields' => ['reference', 'total']],
     *               '_card' =>['Card', 'useLabel' => true]
     *           ],
     *           'Lines'=>[],
     *           'Allocations'=>[]
     *       ],
     *       'Payments'=>[
     *           'Allocations'=>[]
     *       ]
     *   ]
     * );
     *
     *
     * @param Model $m
     * @param array $defs
     *
     * @return \atk4\data\Model
     * @throws \atk4\core\Exception
     * @throws \atk4\data\Exception
     * @throws \atk4\ui\Exception
     */
    public function setModel(Model $m, array $defs = null)
    {
        $this->rootModel = $m;

        $this->crumb->addCrumb($this->getCaption($m), $this->url());

        // extract path
        $this->path = explode('/', $this->app->stickyGet('path'));
        if ($this->path[0] == '') {
            unset($this->path[0]);
        }

        $defs = $this->traverseModel($this->path, $defs);

        $arg_name = $this->model->table.'_id';
        $arg_val = $this->app->stickyGet($arg_name);
        if ($arg_val && $this->model->tryLoad($arg_val)->loaded()) {
            // initialize Tabs
            $this->initTabs($defs);
        } else {
            // initialize CRUD
            $this->initCrud($defs);
        }

        $this->crumb->popTitle();

        return $this->rootModel;
    }

    /**
     * Return model caption.
     *
     * @param \atk4\data\Model $m
     *
     * @return string
     */
    public function getCaption($m)
    {
        return $m->getModelCaption();
    }

    /**
     * Return title field value.
     *
     * @param \atk4\data\Model
     *
     * @return string
     */
    public function getTitle($m)
    {
        return $m->getTitle();
    }

    /**
     * Initialize tabs.
     *
     * @param array $defs
     * @param \atk4\ui\View $view Parent view
     *
     * @throws \atk4\core\Exception
     */
    public function initTabs(array $defs, View $view = null)
    {
        if ($view === null) {
            $view = $this;
        }

        $this->tabs = $view->add($this->getTabsSeed($defs));
        $this->app->stickyGet($this->model->table.'_id');

        $this->crumb->addCrumb($this->getTitle($this->model), $this->tabs->url());

        // Use callback to refresh detail tabs when related model is changed.
        $this->tabs->addTab($this->detailLabel, function($p) use ($defs) {
           $card = $p->add($this->getCardSeed($defs));
           $card->setModel($this->model);
        });

        if (!$defs) {
            return;
        }

        foreach ($defs as $ref => $subdef) {
            if (is_numeric($ref) || in_array($ref, $this->reserved_properties)) {
                continue;
            }
            $m = $this->model->ref($ref);

            $caption = $ref; // $this->getCaption($m);

            $this->tabs->addTab($caption, function($p) use($subdef, $m, $ref) {

                $this->sub_crud = $p->add($this->getCRUDSeed($subdef));

                $this->sub_crud->setModel(clone $m);
                $t = $p->urlTrigger ?: $p->name;

                if (isset($this->sub_crud->table->columns[$m->title_field])) {
                    $this->sub_crud->addDecorator($m->title_field, ['Link', [$t=>false, 'path'=>$this->getPath($ref)], [$m->table.'_id'=>'id']]);
                }

            });
        }
    }

    /**
     * Provided with a relative path, add it to the current one
     * and return string.
     *
     * @param string|array $rel
     *
     * @return false|string
     */
    public function getPath($rel)
    {
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

    /**
     * Initialize CRUD.
     *
     * @param array $defs
     * @param \atk4\ui\View $view Parent view
     *
     * @throws \atk4\core\Exception
     */
    public function initCrud(array $defs, View $view = null)
    {
        if ($view === null) {
            $view = $this;
        }

        $this->crud = $view->add($this->getCRUDSeed($defs));
        $this->crud->setModel($this->model);

        if (isset($this->crud->table->columns[$this->model->title_field])) {
            $this->crud->addDecorator($this->model->title_field, ['Link', [], [$this->model->table.'_id'=>'id']]);
        }
    }

    /**
     * Return seed for CRUD.
     *
     * @param array $defs
     *
     * @return array|View
     * @throws \atk4\core\Exception
     */
    protected function getCRUDSeed(array $defs)
    {
        return $defs[0]['_crud'] ?? $this->defaultCrud;
    }

    /**
     * Return seed for Tabs.
     *
     * @param array $defs
     *
     * @return array|View
     * @throws \atk4\core\Exception
     */
    protected function getTabsSeed(array $defs)
    {
        return $defs[0]['_tabs'] ?? $this->defaultTabs;
    }

    /**
     * Return seed for Card.
     *
     * @param array $defs
     *
     * @return array|View
     * @throws \atk4\core\Exception
     */
    protected function getCardSeed(array $defs)
    {
        return $defs[0]['_card'] ?? $this->defaultCard;
    }

    /**
     * Given a path and arguments, find and load the right model.
     *
     * @param array $path
     * @param array $defs
     *
     * @return array
     * @throws \atk4\core\Exception
     * @throws \atk4\data\Exception
     * @throws \atk4\ui\Exception
     */
    public function traverseModel(array $path, array $defs) :array
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

            $this->crumb->addCrumb(
                $this->getTitle($m),
                $this->url(['path'=>$this->getPath($path_part)])
            );

            $m = $m->ref($p);
            $path_part[]=$p;
        }

        parent::setModel($m);

        return $defs;
    }
}
