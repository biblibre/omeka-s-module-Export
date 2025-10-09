<?php
namespace Export\Form;

use Laminas\Form\Form;

class ExportButtonForm extends Form
{
    /**
     * @var array[String]
     */
    public $availableFormats = ["CSV"];

    public function init()
    {
        $this->availableFormats = array_combine($this->availableFormats, $this->availableFormats);

        $this->add([
                    'name' => 'controller',
                    'type' => 'text',
                    'attributes' => [
                        'hidden' => true,
                    ],
        ]);

        $this->add([
                    'name' => 'format_name',
                    'type' => 'Select',
                    'options' => [
                        'label' => 'Select format to export', // @translate
                        'value_options' => $this->availableFormats,
                    ],
                    'attributes' => [
                        'required' => true,
                    ],
        ]);
    }
}
