<?php
namespace Export\Service\Form;

use Export\Form\ExportButtonForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ExportButtonFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ExportButtonForm(null, $options ?? []);
        $config = $services->get('Config');
        if (empty($config['export_formats'])) {
            throw new ConfigException('In config file: no export_formats found.'); // @translate
        }
        $form->availableFormats = array_keys($config['export_formats']);
        return $form;
    }
}
