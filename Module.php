<?php
namespace Export;

use Export\Form\SiteSettingsFieldset;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /*
     * returns the site config of the module but uses a static variable for optimization
    */
    protected function getModuleSiteConfig(): ?array
    {
        static $localConfig;

        if (!isset($localConfig)) {
            $localConfig = $this->getConfig();
            $localConfig = $localConfig['export'] ?? false;
        }

        if ($localConfig === false) {
            return null;
        }

        return $localConfig['site_settings'] ?? [];
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $acl->allow(null, ['Export\Controller\Site\Index']);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.sidebar',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.show.sidebar',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.before',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.before',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.show.before',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.after',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\ItemSet',
            'view.show.after',
            [$this, 'echoExportButtonHtml']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }

    public function echoExportButtonHtml($event)
    {
        $publicExportButtonPosition = $this->getServiceLocator()->get('Omeka\Settings\Site')->get('export_public_button', 'no');

        if ($event->getName() == 'view.show.after' && $publicExportButtonPosition == 'after'
        || $event->getName() == 'view.show.before' && $publicExportButtonPosition == 'before'
        || $event->getName() == 'view.show.sidebar')
        {
            $view = $event->getTarget();
            echo $view->exportButton();
        }

    }
    public function handleSiteSettings($event)
    {
        $settingsService = $this->getServiceLocator()->get('Omeka\Settings\Site');

        $defaultSettings = $this->getModuleSiteConfig();

        $currentSettings = [];
        foreach ($defaultSettings as $name => $value)
        {
            $currentSettings[$name] = $settingsService->get($name, $value);
        }

        $services = $this->getServiceLocator();
        $forms = $services->get('FormElementManager');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $fieldset = $forms->get(SiteSettingsFieldset::class);
        $fieldset->setName('export');
        $fieldset->populateValues($currentSettings);
        $form = $event->getTarget();
        $groups = $form->getOption('element_groups');
        if (isset($groups)) {
            $groups['export'] = $fieldset->getLabel();
            $form->setOption('element_groups', $groups);
            foreach ($fieldset->getElements() as $element) {
                $form->add($element);
            }
        } else {
            $form->add($fieldset);
        }
    } 
}
