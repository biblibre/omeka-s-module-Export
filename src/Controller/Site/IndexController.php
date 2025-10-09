<?php
namespace Export\Controller\Site;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Stdlib\Message;
use Omeka\Api\Exception\NotFoundException;

class IndexController extends AbstractActionController
{
    protected $serviceLocator;
    public function __construct($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function downloadAction()
    {
        $exporter = $this->serviceLocator->get('Export\Exporter');
        $request = $this->getRequest();
        $postParams = $request->getPost();
        $queryParams = $request->getQuery();

        if ($queryParams['id']) {
            $fileTemp = tmpfile();
            $exporter->setFileHandle($fileTemp);

            $resource_type = '';
            if (empty($postParams['format_name'])) {
                $postParams['format_name'] = 'CSV';
            }
            if (empty($postParams['controller'])) {
                $resource_type = 'item_sets';
            } else {
                if ($postParams['controller'] == 'Omeka\Controller\Site\Item') {
                    $resource_type = 'items';
                } elseif ($postParams['controller'] == 'Omeka\Controller\Site\Media') {
                    $resource_type = 'media';
                } else { // should be 'Omeka\Controller\Admin\ItemSet'
                    $resource_type = 'item_sets';
                }
            }

            try {
                $exporter->downloadOne($queryParams['id'], $postParams['format_name'], $resource_type);
            } catch (NotFoundException $e) {
                $message = new Message($e->getMessage());
                $messenger = $this->serviceLocator->get('ControllerPluginManager')->get('messenger');
                $messenger->addError($message);
                return $this->redirect()->toRoute(null, [], true);
            }

            fseek($fileTemp, 0);
            $rows = '';
            while (! feof($fileTemp)) {
                $rows .= fread($fileTemp, 1024);
            }
            fclose($fileTemp);

            // get format extension
            $config = $this->serviceLocator->get('Config');
            if (empty($config['export_formats'])) {
                throw new ConfigException('In config file: no export_formats found.'); // @translate
            }

            if (empty($config['export_formats'][$postParams['format_name']])) {
                $file_extension = "";
            } else {
                $file_extension = $config['export_formats'][$postParams['format_name']];
            }

            $response = $this->getResponse();
            $response->setContent($rows);
            $response->getHeaders()->addHeaderLine('Content-type', 'text/' . strtolower($postParams['format_name']));
            $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="omekas_export' . $file_extension . '"');

            return $response;
        } else {
            return $this->redirect()->toRoute('site', ['site-slug' => $this->currentSite()->slug()]);
        }
    }
}
