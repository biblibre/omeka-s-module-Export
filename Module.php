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

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $acl->allow(null, ['Export\Controller\Site\Index']);
    }

    public function upgrade($oldVersion, $newVersion, \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator)
    {
        if (version_compare($oldVersion, '2.0.0', '<')) {
            $store = $serviceLocator->get('Omeka\File\Store');

            $storePath = OMEKA_PATH . '/files/';
            $oldDir = 'CSV_Export';
            $newDir = 'Export';

            // not guaranteed to work! Pemission issues, etc. but it is not critical
            // and will only issue a warning
            if (!is_dir($storePath . $oldDir)) {
                mkdir($storePath . $newDir);
            } else {
                rename($storePath . $oldDir, $storePath . $newDir);
            }
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $controllers = ['ItemSet', 'Item', 'Media'];

        foreach ($controllers as $controller) {

            // Browse exports
            $adminController = "Omeka\Controller\Admin\\" . $controller;
            $sharedEventManager->attach(
                $adminController,
                'view.browse.before',
                [$this, 'echoExportAdminLink']
            );
            $sharedEventManager->attach(
                $adminController,
                'view.browse.before',
                [$this, 'addAdminExportJs']
            );
            // Sidebar resource exports
            $sharedEventManager->attach(
                $adminController,
                'view.show.sidebar',
                [$this, 'echoExportButtonHtml']
            );
            // Site resource exports
            $siteController = "Omeka\Controller\Site\\" . $controller;
            $sharedEventManager->attach(
                $siteController,
                'view.show.before',
                [$this, 'echoExportButtonHtml']
            );
            $sharedEventManager->attach(
                $siteController,
                'view.show.after',
                [$this, 'echoExportButtonHtml']
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }

    public function echoExportButtonHtml($event)
    {
        $params = $this->getServiceLocator()->get('Application')->getMvcEvent()->getRouteMatch()->getParams();

        $fromAdmin = false;

        $query = [
            'id' => $params['id'],
        ];

        if (
            $params['controller'] == 'Omeka\Controller\Admin\Item' ||
            $params['controller'] == 'Omeka\Controller\Admin\ItemSet' ||
            $params['controller'] == 'Omeka\Controller\Admin\Media'
        ) {
            $fromAdmin = true;
        }

        $publicExportButtonPosition = $this->getServiceLocator()->get('Omeka\Settings\Site')->get('export_public_button', 'no');

        if (
            $event->getName() == 'view.show.after' && $publicExportButtonPosition == 'after'
            || $event->getName() == 'view.show.before' && $publicExportButtonPosition == 'before'
            || $event->getName() == 'view.show.sidebar'
        ) {
            $view = $event->getTarget();
            echo $view->exportButton($fromAdmin, $params['controller'], $query);
        }
    }

    public function echoExportAdminLink($event)
    {
        $mvcEvent = $this->getServiceLocator()->get('Application')->getMvcEvent();
        $params = $mvcEvent->getRouteMatch()->getParams();
        $request = $mvcEvent->getRequest();

        $query = $request->getQuery()->toArray();
        $view = $event->getTarget();

        echo $view->exportButton(true, $params['controller'], $query, true);
    }

    public function addAdminExportJs($event): void
    {
        $view = $event->getTarget();
        $view->headScript()->appendFile($view->assetUrl('js/export-admin.js', 'Export'), 'text/javascript', ['defer' => 'defer']);
        $view->headLink()->appendStylesheet($view->assetUrl('css/export.css', 'Export'));
    }

    public function handleSiteSettings($event)
    {
        $services = $this->getServiceLocator();
        $forms = $services->get('FormElementManager');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $fieldset = $forms->get(SiteSettingsFieldset::class);
        $fieldset->setName('export');

        $elements = $fieldset->getElements();

        $currentSettings = [];
        foreach ($elements as $name => $element) {
            $services->get('Omeka\Logger')->debug(var_export($siteSettings->get($name, $element->getValue()), true));
            $currentSettings[$name] = $siteSettings->get($name, $element->getValue());
        }

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
