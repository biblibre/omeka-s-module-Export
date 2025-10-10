<?php declare(strict_types=1);

namespace Export\Form;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Export'; // @translate
    
    public function init(): void
    {
        $this->setLabel($this->label);

        $this->add([
                'name' => 'export_public_button',
                'type' => Element\Radio::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Export button placement', // @translate
                    'value_options' => [
                        'no' => 'No', // @translate
                        'before' => 'Before', // @translate
                        'after' => 'After' // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'export_public_button'
                ],
            ]);
    }
}
