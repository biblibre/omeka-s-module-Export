<?php
namespace Export\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ListController extends AbstractActionController
{
    public function listAction()
    {
        $downloads = [];

        $directoryPath = 'files/Export';
        if (is_dir($directoryPath)) {
            $downloads = array_diff(scandir($directoryPath, SCANDIR_SORT_DESCENDING), ['..', '.']);
        }

        $view = new ViewModel;
        $view->setVariable('downloads', $downloads);

        return $view;
    }
}
