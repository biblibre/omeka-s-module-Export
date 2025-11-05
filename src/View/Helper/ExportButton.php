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

    public function __invoke(bool $admin, string $controller, array $query)
    {
        $url = null;

        $form = null;

        if ($admin) {
            $url = $this->getView()->url('admin/export/download', [], ['query' => $query]);
            $form = $this->application->getServiceManager()->get('FormElementManager')->get(ExportButtonForm::class, ["admin" => true]);
        } else {
            $siteSlug = $this->getView()->getHelperPluginManager()->get('currentSite')()->slug();
            $url = $this->getView()->url('site/export', ['site-slug' => $siteSlug], ['query' => $query]);
            $form = $this->application->getServiceManager()->get('FormElementManager')->get(ExportButtonForm::class, ["admin" => false]);
        }

        if (empty($form->availableFormats)) {
            return '';
        }

        $form->setAttribute('action', $url);
        $form->get('controller')->setValue($controller);

        return $this->getView()->partial('export/export-button', ['form' => $form]);
    }
}
