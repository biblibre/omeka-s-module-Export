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

        $exporter = $services->get('Export\Exporter');

        $logger->info('Job started');

        $now = date("Y-m-d_H-i-s");

        $filename = tempnam(sys_get_temp_dir(), 'omekas_export');
        $fileTemp = fopen($filename, 'w');
        $exporter->setFileHandle($fileTemp);

        $exporter->exportItemsQuery($this->getArg('query'), $this->getArg('format_name'));

        fclose($fileTemp);

        $fileExtension = \Export\Exporter::IMPLEMENTED_FORMATS[$this->getArg('format_name')] ?? "";

        $store->put($filename, sprintf("Export/omekas_$now%s", $fileExtension));

        unlink($filename);

        $logger->info(sprintf("Saved in files/Export/omekas_$now%s", $fileExtension));
        $logger->info('Job ended');
    }
}
