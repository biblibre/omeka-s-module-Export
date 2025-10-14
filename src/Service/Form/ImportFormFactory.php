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
        $config = $services->get('Config');
        if (empty($config['export']['formats'])) {
            throw new ConfigException('In config file: no [exports][formats] found.'); // @translate
        }
        $form->availableFormats = array_keys($config['export']['formats']);
        return $form;
    }
}
