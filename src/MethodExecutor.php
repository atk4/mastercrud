<?php

namespace atk4\mastercrud;

use atk4\data\Model;
use atk4\ui\Console;
use atk4\ui\Form;
use atk4\ui\FormField\AutoComplete;
use atk4\ui\View;

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
class MethodExecutor extends View
{
    use \atk4\core\SessionTrait;

    /** @var Model */
    public $model;

    /** @var string */
    public $method;

    /** @var array */
    public $defs;

    /**
     * Constructor.
     */
    public function __construct(Model $model, string $method, array $defs = [])
    {
        parent::__construct([
            'model' => $model,
            'method' => $method,
            'defs' => $defs,
        ]);
    }

    /**
     * Initialization.
     */
    public function init(): void
    {
        parent::init();

        $this->console = $this->add([Console::class, 'event' => false]); //->addStyle('display', 'none');
        $this->console->addStyle('max-height', '50em')->addStyle('overflow', 'scroll');

        $this->form = $this->add([Form::class]);

        foreach ($this->defs as $key => $val) {
            if (is_numeric($key)) {
                $key = 'Argument' . $key;
            }

            if (is_callable($val)) {
                continue;
            }

            if ($val instanceof Model) {
                $this->form->addField($key, [AutoComplete::class])->setModel($val);
            } else {
                $this->form->addField($key, null, $val);
            }
        }

        $this->form->buttonSave->set('Run');

        $this->form->onSubmit(function ($f) {
            $this->memorize('data', $f->model ? $f->model->get() : []);

            return [$this->console->js()->show(), $this->console->sse];
        });

        $this->console->set(function ($c) {
            $data = $this->recall('data');
            $args = [];

            foreach ($this->defs as $key => $val) {
                if (is_numeric($key)) {
                    $key = 'Argument' . $key;
                }

                if (is_callable($val)) {
                    $val = $val($this->model, $this->method, $data);
                } elseif ($val instanceof Model) {
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
