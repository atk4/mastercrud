<?php

namespace atk4\mastercrud;

/**
 * This component will display a form and a console. After filling out the form, the values
 * will be passed on to the model / method of your choice and the execution of that method
 * will be displayed in the console.
 *
 * $app->add(new MethodExecutor($user, 'generatePassword', ['integer']));
 *
 * Possible values of 3rd argument would be:
 *
 *  - string, would define 'type'=>$type explicitly, e.g. 'boolean' or 'date'.
 *  - callback, would be executed and return value used.  function() { return 123; }
 *  - array - use a seed for creating model field
 */

class MethodExecutor extends \atk4\ui\View
{
    use \atk4\core\SessionTrait;

    /** @var \atk4\data\Model */
    public $model = null;

    /** @var string */
    public $method = null;

    /** @var array */
    public $defs = null;

    /**
     * Constructor.
     *
     * @param \atk4\data\Model $model
     * @param string           $method
     * @param array            $defs
     */
    public function __construct(\atk4\data\Model $model, $method, $defs = [])
    {
        parent::__construct([
            'model' => $model,
            'method' => $method,
            'defs' => $defs
        ]);
    }

    /**
     * Initialization.
     */
    public function init(): void
    {
        parent::init();

        $this->console = $this->add(['Console', 'event'=>false]);//->addStyle('display', 'none');
        $this->console->addStyle('max-height', '50em')->addStyle('overflow', 'scroll');

        $this->form = $this->add('Form');

        foreach ($this->defs as $key=>$val) {
            if (is_numeric($key)) {
                $key = 'Argument' . $key;
            }

            if (is_callable($val)) {
                continue;
            }

            if ($val instanceof \atk4\data\Model) {
                $this->form->addField($key, ['AutoComplete'])->setModel($val);
            } else {
                $this->form->addField($key, null, $val);
            }
        }

        $this->form->buttonSave->set('Run');

        $this->form->onSubmit(function ($f) {
            $this->memorize('data', $f->model ? $f->model->get(): []);

            return [$this->console->js()->show(), $this->console->sse];
        });

        $this->console->set(function ($c) {
            $data = $this->recall('data');
            $args = [];

            foreach ($this->defs as $key=>$val) {
                if (is_numeric($key)) {
                    $key = 'Argument' . $key;
                }

                if (is_callable($val)) {
                    $val = $val($this->model, $this->method, $data);
                } elseif ($val instanceof \atk4\data\Model) {
                    $val->load($data[$key]);
                } else {
                    $val = $data[$key];
                }

                $args[] = $val;
            }

            $c->setModel($this->model, $this->method, $args);
        });
    }
}
