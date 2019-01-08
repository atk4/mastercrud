<?php

namespace atk4\mastercrud;

class MasterCRUD extends \atk4\ui\View
{
    /** @var BreadCrumb object */
    public $crumb = null;

    /** @var \atk4\data\Model the top-most model */
    public $rootModel;

    /** @var string Tab Label for detail */
    public $detailLabel = 'Details';

   /** @var array of properties which are reserved for MasterCRUD and can't be used as model names */
    protected $reserved_properties = ['_crud', '_tabs', 'menuActions', 'caption', 'columnActions'];

    /**
     * Initialization.
     */
    public function init() {
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
     *
     * @param \atk4\data\Model $m
     * @param array            $defs
     *
     * @return \atk4\data\Model
     */
    public function setModel(\atk4\data\Model $m, $defs = null)
    {
        $this->rootModel = $m;

        $this->crumb->addCrumb($this->getCaption($m), $this->url());

        // extract path
        $this->path = explode('/', $this->stickyGet('path'));
        if ($this->path[0] == '') {
            unset($this->path[0]);
        }

        $defs = $this->traverseModel($this->path, $defs);

        $arg_name = $this->model->table.'_id';
        $arg_val = $this->stickyGet($arg_name);
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
     */
    public function initTabs($defs, $view = null)
    {
        if ($view === null) {
            $view = $this;
        }

        $this->tabs = $view->add($this->getTabsSeed($defs));
        $this->tabs->stickyGet($this->model->table.'_id');

        $this->crumb->addCrumb($this->getTitle($this->model), $this->tabs->url());

        // Imants: BUG HERE - WE DON'T RESPECT PROPERTIES SET IN DEFS. FOR EXAMPLE $defs[_crud]=>['fieldsDefault'=>[only,these,fields]]
        // Should take some ideas from CRUD->initCreate and CRUD->initUpdate how to limit fields for this form.
        $form = $this->tabs->addTab($this->detailLabel)->add('Form');
        $form->setModel($this->model);

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

                $this->sub_crud->setModel($m);
                $t = $p->urlTrigger ?: $p->name;

                if (isset($this->sub_crud->table->columns[$m->model->title_field])) {
                    $this->sub_crud->addDecorator($m->title_field, ['Link', [$t=>false, 'path'=>$this->getPath($ref)], [$m->table.'_id'=>'id']]);
                }

                $this->addActions($this->sub_crud, $subdef);
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
     * @param array         $defs
     * @param \atk4\ui\View $view Parent view
     */
    public function initCrud($defs, $view = null)
    {
        if ($view === null) {
            $view = $this;
        }

        $this->crud = $view->add($this->getCRUDSeed($defs));
        $this->crud->setModel($this->model);

        if (isset($this->crud->table->columns[$this->model->title_field])) {
            $this->crud->addDecorator($this->model->title_field, ['Link', [], [$this->model->table.'_id'=>'id']]);
        }

        $this->addActions($this->crud, $defs);

        /*
        $named_args = array_filter($defs, function($k) {
            return !is_numeric($k);
        },  ARRAY_FILTER_USE_KEY);
        */
    }

    /**
     * Adds CRUD action buttons.
     *
     * @param \atk4\ui\CRUD $crud
     * @param array         $defs
     */
    public function addActions($crud, $defs)
    {
        if ($ma = $defs['menuActions'] ?? null) {

            is_array($ma) || $ma = [$ma];

            foreach($ma as $key => $action) {
                if (is_numeric($key)) {
                    $key = $action;
                }

                if (is_string($action)) {
                    $crud->menu->addItem($key)->on(
                        'click',
                        new \atk4\ui\jsModal('Executing '.$key, $this->add('VirtualPage')->set(function($p) use($key, $action) {

                            // TODO: this does ont work within a tab :(
                            $p->add(new MethodExecutor($crud->model, $key));
                        }))
                    );
                }

                if ($action instanceof \Closure) {
                    $crud->menu->addItem($key)->on(
                        'click',
                        new \atk4\ui\jsModal('Executing '.$key, $this->add('VirtualPage')->set(function($p) use($key, $action) {
                            $action($p, $this->model, $key);
                        }))
                    );
                }
            }
        }

        if ($ca = $defs['columnActions'] ?? null) {

            is_array($ca) || $ca = [$ca];

            foreach($ca as $key => $action) {

                if (is_numeric($key)) {
                    $key = $action;
                }

                if (is_string($action)) {
                    $label = ['icon'=>$action];
                }

                is_array($action) || $action = [$action];

                if (isset($action['icon'])) {
                    $label = ['icon'=>$action['icon']];
                    unset($action['icon']);
                }

                if (isset($action[0]) && $action[0] instanceof \Closure) {
                    $crud->addModalAction($label ?: $key, $key, function($p, $id) use($action, $key, $crud) {
                        call_user_func($action[0], $p, $crud->model->load($id));
                    });
                } else {
                    $crud->addModalAction($label ?: $key, $key, function($p, $id) use($action, $key, $crud) {
                        $p->add(new MethodExecutor($crud->model->load($id), $key, $action));
                    });
                }
            }
        }
    }

    /**
     * Return seed for CRUD.
     *
     * @param array $defs
     *
     * @return array
     */
    protected function getCRUDSeed($defs)
    {
        $seed = isset($defs[0]) ? $defs[0] : [];
        $result= $this->mergeSeeds(
            $seed,
            isset($defs['_crud']) ? $defs['_crud'] : [],
            [ 'CRUD', ]
        );

        return $result;
    }

    /**
     * Return seed for Tabs.
     *
     * @param array $defs
     *
     * @return array
     */
    protected function getTabsSeed($defs)
    {
        $seed = isset($defs[0]) ? $defs[0] : [];
        $result= $this->mergeSeeds(
            $seed,
            isset($defs['_tabs']) ? $defs['_tabs'] : [],
            [ 'Tabs', ]
        );

        return $result;
    }

    /**
     * Given a path and arguments, find and load the right model.
     *
     * @param array $path
     * @param array $defs
     *
     * @return array
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
