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
        if (empty($config['export_formats'])) {
            throw new ConfigException('In config file: no export_formats found.'); // @translate
        }

        if (empty($config['export_formats'][$this->getArg('format_name')])) {
            $file_extension = "";
        } else {
            $file_extension = $config['export_formats'][$this->getArg('format_name')];
        }

        $exporter = $services->get('Export\Exporter');

        $logger->info('Job started');

        $now = date("Y-m-d_H-i-s");

        $filename = tempnam(sys_get_temp_dir(), 'omekas_export');
        $fileTemp = fopen($filename, 'w');
        $exporter->setFileHandle($fileTemp);

        $exporter->exportItemsQuery($this->getArg('query'), $this->getArg('format_name'));

        fclose($fileTemp);

        $store->put($filename, sprintf("CSV_Export/omekas_$now%s", $file_extension));

        unlink($filename);

        $logger->info(sprintf("Saved in files/CSV_Export/omekas_$now%s", $file_extension));
        $logger->info('Job ended');
    }
}
