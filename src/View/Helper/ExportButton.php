<?php

namespace Export\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Laminas\Mvc\Application;
use Export\Form\ExportButtonForm;

class ExportButton extends AbstractHelper
{
    protected $application;

    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    public function __invoke()
    {
        $mvcEvent = $this->application->getMvcEvent();
        $routeMatch = $mvcEvent->getRouteMatch();
        $params = $routeMatch->getParams();

        $fromAdmin = false;

        $query = [
            'id' => $params['id'],
           ];

        if ($params['controller'] == 'Omeka\Controller\Admin\Item' ||
            $params['controller'] == 'Omeka\Controller\Admin\ItemSet' ||
            $params['controller'] == 'Omeka\Controller\Admin\Media') {
            $fromAdmin = true;
        }

        $url = null;

        if ($fromAdmin) {
            $url = $this->getView()->url('admin/export/download', [], ['query' => $query]);
        } else {
            $siteSlug = $this->getView()->getHelperPluginManager()->get('currentSite')()->slug();
            $url = $this->getView()->url('site/export', ['site-slug' => $siteSlug], ['query' => $query]);
        }

        // bug download list qui s'affiche pas ?
        $form = $this->application->getServiceManager()->get('FormElementManager')->get(ExportButtonForm::class);
        $form->setAttribute('action', $url);
        $form->get('controller')->setValue($params['controller']);

        return $this->getView()->partial('export/export-button', ['form' => $form]);
    }
}
