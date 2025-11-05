<?php
namespace Export\Service\Form;

use Export\Form\ImportForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImportFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ImportForm(null, $options ?? []);
        $settings = $services->get('Omeka\Settings\Site');

        $form->availableFormats = array_keys(\Export\Exporter::IMPLEMENTED_FORMATS);
        return $form;
    }
}
