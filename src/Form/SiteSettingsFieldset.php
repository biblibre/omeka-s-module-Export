<?php declare(strict_types=1);

namespace Export\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Export\Form\Element\OptionalMultiCheckbox;

class SiteSettingsFieldset extends Fieldset
{
    /*
     * @var string
     */
    protected $label = 'Export'; // @translate

    public function init(): void
    {
        $this->setLabel($this->label);

        $formats = array_keys(\Export\Exporter::IMPLEMENTED_FORMATS);
        $valueOptions = [];

        foreach ($formats as $format) {
            $valueOptions[] = [
                'label' => $format,
                'value' => $format,
            ];
        }

        $this->add([
                'name' => 'export_enabled_formats',
                'type' => OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'export',
                    'label' => 'Export formats enabled for this site', // @translate
                    'value_options' => $valueOptions,
                ],
                'attributes' => [
                    'id' => 'export_enabled_formats',
                ],
            ]);
    }
}
