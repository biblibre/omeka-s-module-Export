<?php
namespace Export\Controller;

use Export\Form\ImportForm;
use Export\Job\ExportJob;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{
    protected $serviceLocator;
    protected $jobId;
    protected $jobUrl;

    public function __construct($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    public function exportAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(ImportForm::class);
        $view->form = $form;
        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();

                $args['query'] = ['item_set_id' => $formData['item_set']];
                $args['format_name'] = $formData['format_name'];
                $this->sendJob($args);

                $message = new Message(
                    'Export started in %sjob %s%s', // @translate
                    sprintf('<a href="%s">', htmlspecialchars($this->getJobUrl(),
                )),
                    $this->getJobId(),
                    '</a>'
                );

                $message->setEscapeHtml(false);
                $this->messenger()->addSuccess($message);

                return $this->redirect()->toRoute(null, [], [], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        return $view;
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
                if ($postParams['controller'] == 'Omeka\Controller\Admin\Item') {
                    $resource_type = 'items';
                } elseif ($postParams['controller'] == 'Omeka\Controller\Admin\Media') {
                    $resource_type = 'media';
                } else { // should be 'Omeka\Controller\Admin\ItemSet'
                    $resource_type = 'item_sets';
                }
            }
            $exporter->downloadOne($queryParams['id'], $postParams['format_name'], $resource_type);

            fseek($fileTemp, 0);
            $rows = '';
            while (! feof($fileTemp)) {
                $rows .= fread($fileTemp, 1024);
            }
            fclose($fileTemp);

            $fileExtension = \Export\Exporter::IMPLEMENTED_FORMATS[$postParams['format_name']][0]; // extension
            $fileMime = \Export\Exporter::IMPLEMENTED_FORMATS[$postParams['format_name']][1]; // MIME

            $response = $this->getResponse();
            $response->setContent($rows);
            $response->getHeaders()->addHeaderLine('Content-type', $fileMime);
            $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="omekas_export' . $fileExtension . '"');

            return $response;
        } else {
            $args = $queryParams->toArray();
            unset($args['page']);

            $this->sendJob(['query' => $args]);

            $message = new Message(
                'Export started in %sjob %s%s', // @translate
                sprintf('<a href="%s">', htmlspecialchars($this->getJobUrl(),
            )),
                $this->getJobId(),
                '</a>'
            );

            $message->setEscapeHtml(false);
            $this->messenger()->addSuccess($message);

            return $this->redirect()->toRoute('admin/export/list', ['controller' => 'list', 'action' => 'list'], []);
        }
    }

    public function deleteAction()
    {
        $request = $this->getRequest();
        $queryParams = $request->getQuery();

        if ($queryParams['file_name']) {
            $store = $this->serviceLocator->get('Omeka\File\Store');

            $store->delete('Export/' . $queryParams['file_name']);
        }
        return $this->redirect()->toRoute('admin/export/list', ['controller' => 'list', 'action' => 'list'], []);
    }

    protected function sendJob($args)
    {
        $job = $this->jobDispatcher()->dispatch(ExportJob::class, $args);

        $jobUrl = $this->url()->fromRoute('admin/id', [
                    'controller' => 'job',
                    'action' => 'show',
                    'id' => $job->getId(),
                ]);

        $this->setJobId($job->getId());
        $this->setJobUrl($jobUrl);
    }

    protected function getJobId()
    {
        return $this->jobId;
    }

    protected function setJobId($id)
    {
        $this->jobId = $id;
    }

    protected function getJobUrl()
    {
        return $this->jobUrl;
    }

    protected function setJobUrl($url)
    {
        $this->jobUrl = $url;
    }
}
