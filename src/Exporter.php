<?php

namespace Export;

use Omeka\Api\Exception\NotFoundException;

class Exporter
{
    protected $application;
    protected $fileHandle;

    public function __construct($application)
    {
        $this->application = $application;
    }

    public function downloadOne($query, $format = 'CSV', $type = 'items')
    {
        $services = $this->application->getServiceManager();
        $api = $services->get('Omeka\ApiManager');

        $items = [];
        $itemsQueryResult = $api->search($type, ['id' => $query])->getContent();
        if (count($itemsQueryResult) < 1) {
            throw new NotFoundException(sprintf('No item with id %s found', $query));
        } // @ translate
        else {
            $items[] = $itemsQueryResult[0];
        }

        $this->transform($items, $format);
    }

    public function exportItemSet($query, $format = 'CSV')
    {
        $services = $this->application->getServiceManager();
        $api = $services->get('Omeka\ApiManager');

        $itemSetId = $query;
        $items = $api->search('items', ['item_set_id' => $itemSetId])->getContent();

        if (count($items) < 1) {
            throw new NotFoundException(sprintf('No item set with id %s found', $itemSetId));
        } // @ translate

        $this->transform($items, $format);
    }

    public function exportItemsQuery($query, $format = 'CSV')
    {
        $services = $this->application->getServiceManager();
        $api = $services->get('Omeka\ApiManager');

        $items = $api->search('items', $query)->getContent();

        $this->transform($items, $format);
    }

    protected function transform($items, $format = 'CSV')
    {
        if ($format == 'CSV') { // @TODO : make this better than strings
            $this->transformToCSV($items);
        } elseif ($format == 'JSON') {
            $this->transformToJSON($items);
        } elseif ($format == 'TXT') {
            $this->transformToTXT($items);
        } else {
            fwrite($this->getFileHandle(), sprintf('Error: unknown file format : "%s."', $format));
        }
    }

    protected function transformToJSON($items)
    {
        // the API already returns JSON
        fwrite($this->getFileHandle(), json_encode($items));
    }

    protected function transformToTXT($resources)
    {
        // string that will be printed out to file
        $resourcesStr = '';

        $resources = $this->formatData($resources);

        $services = $this->application->getServiceManager();
        $api = $services->get('Omeka\ApiManager');

        foreach ($resources as $resource) {
            // if not first resource we're printing, skip a line
            if ($resourcesStr != '') {
                $resourcesStr = $resourcesStr . PHP_EOL;
            }

            // look for resource template, in case property labels are renamed by it
            $bHasResourceTemplate = false;
            $resourceTemplate = null;

            if (array_key_exists('o:resource_template', $resource) && !is_null($resource['o:resource_template'])) {
                $bHasResourceTemplate = true;

                $resourceTemplate = $this->formatData($api->search('resource_templates',
                                        ['id' => $resource['o:resource_template']['o:id']])
                                    ->getContent()[0]);
            }

            // looping through properties to find something like dcterms:title
            // properties like dcterms:title are an array of all the values of the corresponding property
            // with each element of the array containing 'property_id' and 'property_label'
            foreach ($resource as $property_name => $propertyValue) {
                if (is_array($propertyValue)) {
                    // keep track of whether or no this is the first value of one property we're printin out
                    // because in that case we need to print 'PropertyLabel = ' and then we need separators
                    // ex: 'Date = 01-01-01 ; 01-02-01'
                    $bIsFirstPropertyOfLabel = true;
                    $bIsProperty = false;

                    foreach ($propertyValue as $propertyValueElement) {
                        if (is_array($propertyValueElement) &&
                            array_key_exists('property_id', $propertyValueElement)
                            && array_key_exists('property_label', $propertyValueElement)) {
                            $bIsProperty = true;

                            // we found a property like dcterms:title!

                            if ($bIsFirstPropertyOfLabel) {
                                // if first value of property, then add the "Title = " text.
                                // we must first check if the label was renamed or not by the resource template
                                if ($bHasResourceTemplate) {
                                    $correspondingResourceTemplateProperty = null;
                                    foreach ($resourceTemplate['o:resource_template_property'] as $resourceTemplateProperty) {
                                        if ($resourceTemplateProperty['o:property']['o:id'] == $propertyValueElement['property_id']) {
                                            if (array_key_exists('o:alternate_label', $resourceTemplateProperty) &&
                                            $resourceTemplateProperty['o:alternate_label']) {
                                                $correspondingResourceTemplateProperty = $resourceTemplateProperty;
                                            }
                                            break;
                                        }
                                    }
                                    if (!is_null($correspondingResourceTemplateProperty)) {
                                        $resourcesStr = $resourcesStr . $correspondingResourceTemplateProperty['o:alternate_label'] . ' = ';
                                    } else {
                                        $resourcesStr = $resourcesStr . $propertyValueElement['property_label'] . ' = ';
                                    }
                                } else {
                                    $resourcesStr = $resourcesStr . $propertyValueElement['property_label'] . ' = ';
                                }
                                $resourcesStr = $resourcesStr . $propertyValueElement['@value'];
                                $bIsFirstPropertyOfLabel = false;
                            } else {
                                $resourcesStr = $resourcesStr . " ; " . $propertyValueElement['@value'];
                            }
                        }
                    }
                    if ($bIsProperty) {
                        $resourcesStr = $resourcesStr . PHP_EOL;
                    }
                }
            }
        }

        fwrite($this->getFileHandle(), $resourcesStr);
    }

    protected function transformToCSV($items)
    {
        $items = $this->formatData($items);
        $itemMedia = [];
        foreach ($items as $item) {
            if (array_key_exists('o:media', $item) && !empty($item['o:media'])) {
                $mediaIds = $item['o:media'];
                $mediaOut = "";
                $mediaJson = "";
                foreach ($mediaIds as $mediaId) {
                    $id = $mediaId['o:id'];
                    $media = $this->getData($id, 'id', 'media');
                    foreach ($media as $medium) {
                        $mediaOut = $mediaOut . $medium['o:filename'] . ";";
                        $mediaJson = $mediaJson . json_encode($medium) . ";";
                        $item['media:link'] = $mediaOut;
                        $item['media:full'] = $mediaJson;
                    }
                }
            } else {
                $item['media:link'] = "";
                $item['media:full'] = "";
            }
            array_push($itemMedia, $item);
        }

        $properties = $this->getData("", 'term', 'properties');
        $propertyNames = [];
        foreach ($properties as $property) {
            $p = $property['o:term'];
            array_push($propertyNames, $p);
        }
        $collection = $itemMedia;
        $properties = $propertyNames;

        $output = $this->getFileHandle();

        $resultCount = sizeOf($collection);
        if ($resultCount > 0) {
            $collectionHeaders = array_keys($collection[0]);
            $header = array_merge($collectionHeaders, $properties);
            fputcsv($output, $header);
            foreach ($collection as $item) {
                if (is_array($item)) {
                    $outputItem = [];
                    foreach ($header as $column) {
                        if (array_key_exists($column, $item)) {
                            $row = $item[$column];
                            if (is_array($row)) {
                                if (array_key_exists('o:id', $row)) {
                                    array_push($outputItem, $row['o:id']);
                                } elseif (array_key_exists('@value', $row)) {
                                    array_push($outputItem, $row['@value']);
                                } else {
                                    //Row has multiple values
                                    $multiRow = "";
                                    foreach ($row as $single) {
                                        if (is_array($single)) {
                                            if (array_key_exists('o:id', $single)) {
                                                $multiRow = $multiRow . ";" . $single['o:id'] ;
                                            } elseif (array_key_exists('@value', $single)) {
                                                $multiRow = $multiRow . ";" . $single['@value'] ;
                                            } elseif (array_key_exists('@id', $single)) {
                                                $multiRow = $multiRow . ";" . $single['@id'] ;
                                            }
                                        } else {
                                            $multiRow = $multiRow . ";" . $single ;
                                        }
                                    }
                                    $multiRow = substr($multiRow, 1);
                                    array_push($outputItem, $multiRow);
                                }
                            } else {
                                array_push($outputItem, $row);
                            }
                        } else {
                            array_push($outputItem, "");
                        }
                    }
                    unset($item['media:full']);
                    array_push($outputItem, json_encode($item));
                    fputcsv($output, $outputItem);
                }
            }
        }
    }

    protected function getData($criteria, $field, $type)
    {
        $services = $this->application->getServiceManager();
        $api = $services->get('Omeka\ApiManager');

        $query[$field] = $criteria;
        $items = $api->search($type, $query)->getContent();
        $out = $this->formatData($items);

        return $out;
    }

    protected function formatData($rawData)
    {
        $arr = json_encode($rawData, true);
        $items = json_decode($arr, true);
        return $items ;
    }

    public function setFileHandle($fileHandle)
    {
        $this->fileHandle = $fileHandle;
    }

    public function getFileHandle()
    {
        return $this->fileHandle;
    }
}
