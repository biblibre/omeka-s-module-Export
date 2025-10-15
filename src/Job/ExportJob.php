<?php

namespace Export\Job;

use Omeka\Job\AbstractJob;

class ExportJob extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $store = $this->getServiceLocator()->get('Omeka\File\Store');

        $config = $services->get('Config');
        if (empty($config['export']['formats'])) {
            throw new ConfigException('In config file: no [export][formats] found.'); // @translate
        }

        $fileExtension = $config['export']['formats'][$this->getArg('format_name')][0]; // file extension

        $exporter = $services->get('Export\Exporter');

        $logger->info('Job started');

        $now = date("Y-m-d_H-i-s");

        $filename = tempnam(sys_get_temp_dir(), 'omekas_export');
        $fileTemp = fopen($filename, 'w');
        $exporter->setFileHandle($fileTemp);

        $exporter->exportItemsQuery($this->getArg('query'), $this->getArg('format_name'));

        fclose($fileTemp);

        $store->put($filename, sprintf("CSV_Export/omekas_$now%s", $fileExtension));

        unlink($filename);

        $logger->info(sprintf("Saved in files/CSV_Export/omekas_$now%s", $fileExtension));
        $logger->info('Job ended');
    }
}
