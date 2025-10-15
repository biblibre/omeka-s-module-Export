<?php

namespace Export;

use Omeka\Api\Exception\NotFoundException;

class Exporter
{
    protected $application;
    protected $fileHandle;
    protected $bibtexConfig;

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
        } elseif ($format == 'BibTex') {
            $this->transformToBibTex($items);
        }
        else {
            fwrite($this->getFileHandle(), sprintf('Error: unknown file format : "%s."', $format));
        }
    }

    /*
     * load the JSON config of DefaultBibtexMapping.json
     * or CustomBibtexMapping.json in $this->bibtexConfig
    */
    protected function loadBibtexConfig()
    {
        $this->bibtexConfig = false;

        if (file_exists(__DIR__ . "/../config/CustomBibtexMapping.json"))
            $this->bibtexConfig = file_get_contents(__DIR__ . "/../config/CustomBibtexMapping.json");

        if (!$this->bibtexConfig)
        {
            if (file_exists(__DIR__ . "/../config/DefaultBibtexMapping.json"))
                $this->bibtexConfig = file_get_contents(__DIR__ . "/../config/DefaultBibtexMapping.json");
        }

        if (!$this->bibtexConfig)
        {
            return false;
        }

        $this->bibtexConfig = json_decode($this->bibtexConfig, true);
            
        return true;
    }

    protected function transformToBibTex($resources)
    {
        if (!$this->loadBibtexConfig())
            return false;

        $resources = $this->formatData($resources);

        foreach ($resources as $resource) {
            // may be changed in the future
            $typeStr = '@misc';

            $bibtexStr = $typeStr . ' {' . PHP_EOL;
            foreach ($this->bibtexConfig as $bibtexPropertyName => $bibtexPropertyConfig) {
                if (array_key_exists(0, $bibtexPropertyConfig)) {
                    foreach ($bibtexPropertyConfig as $bibtexPropertyConfigElement) {

                        $textToAdd = $this->transformToBibtextReadMapping($bibtexPropertyConfigElement, $resource);
                        if (strlen($textToAdd) > 0) {

                            $bibtexStr = $bibtexStr . $this->transformToBibtexOpenValueBrackets($bibtexPropertyName) . $textToAdd . ' }' . PHP_EOL;
                            break;

                        }
                    }
                }
                $textToAdd = $this->transformToBibtextReadMapping($bibtexPropertyConfig, $resource);
                if (strlen($textToAdd) > 0) {
                    $bibtexStr = $bibtexStr . $this->transformToBibtexOpenValueBrackets($bibtexPropertyName) . $textToAdd . ' }' . PHP_EOL;
                }
            }
            $textToAdd = sprintf("Accessed on: %s", // @translate
                                date_format(date_create(), 'Y-d-m')
                            );
            $bibtexStr = $bibtexStr . $this->transformToBibtexOpenValueBrackets("note") . $textToAdd . ' }' . PHP_EOL;
            $textToAdd = sprintf("\\url{%s}", $resource["@id"]);
            $bibtexStr = $bibtexStr . $this->transformToBibtexOpenValueBrackets("howpublished") . $textToAdd . ' }' . PHP_EOL;
            $bibtexStr = $bibtexStr . '}' . PHP_EOL;
        }
        
        fwrite($this->getFileHandle(), $bibtexStr);
    }

    // reads a 'mapping' object from the json config
    // that contains info on how to map omeka properties into bibtex properties
    protected function transformToBibtextReadMapping($mappingObject, $resource)
    {
        $transformStr = '';

        if (!array_key_exists('mappings', $mappingObject)) {
            return '';
        }

        if (array_key_exists('type', $mappingObject))
        {
            return $this->transformToBibtextTypeHelper($mappingObject, $resource);
        }

        $separator = ' AND '; // @ translate
        if (array_key_exists('separator', $mappingObject) && is_string($mappingObject['separator'])) {
            $separator = $mappingObject['separator'];
        }

        $bFoundAtLeastOneMapping = false;
        foreach ($mappingObject['mappings'] as $mapping)
        {
            if (array_key_exists($mapping, $resource) && count($resource[$mapping]) > 0) {
                foreach ($resource[$mapping] as $foundMapping) {
                    if ($bFoundAtLeastOneMapping) {
                        $transformStr = $transformStr . $separator;
                    }

                    $bFoundAtLeastOneMapping = true;
                    $transformStr = $transformStr . $this->transformToBibtexEscapeString($this->extractStringValueFromProperty($foundMapping));
                }
            }
        }

        return $transformStr;
    }

    // read specified type in mapping object to do something custom with it
    protected function transformToBibtextTypeHelper($mappingObject, $resource)
    {
        $transformStr = '';

        $type = $mappingObject['type'];

        $separator = ' AND '; // @ translate
        if (array_key_exists('separator', $mappingObject) && is_string($mappingObject['separator'])) {
            $separator = $mappingObject['separator'];
        }

        if ($type == "month") {
            if (!is_array($mappingObject["mappings"]) || !(count($mappingObject["mappings"] > 0)))
                return '';

            $date = false;
            $mapping = $mappingObject["mappings"][0];
            if (array_key_exists($mapping, $resource)) {
                $bIsFirst = true;
                foreach ($ressource[$mapping] as $propertyElement)
                {
                    $value = $this->transformToBibtexEscapeString($this->extractStringValueFromProperty($propertyElement));
                    $date = date_create($value);
                    if (!$date)
                        continue;
                    if (!$bIsFirst)
                        $transformStr = $transformStr . $separator;

                    $transformStr = $transformStr . date_format($date, 'M');

                    $isFirst = false;
                }
            }

            return '';
        }
            
        else if ($type == "year") {
            if (!is_array($mappingObject["mappings"]) || !(count($mappingObject["mappings"]) > 0))
                return '';

            $date = false;
            $mapping = $mappingObject["mappings"][0];
            if (array_key_exists($mapping, $resource)) {
                $bIsFirst = true;
                foreach ($resource[$mapping] as $propertyElement)
                {
                    $value = $this->extractStringValueFromProperty($propertyElement);

                    // returns false if it fails
                    $date = date_create($value);
                    if (!$date)
                        continue;

                    if (!$bIsFirst)
                        $transformStr = $transformStr . $separator;

                    $transformStr = $transformStr . date_format($date, 'Y');

                    $isFirst = false;
                }
            }
        }

        else if ($type == "format") {
            // user must have specified a format 
            if (!array_key_exists('format', $mappingObject)) {
                return '';
            }

            $args = [];
            foreach ($mappingObject['mappings'] as $mapping) {
                
                // every argument must exist
                if (!array_key_exists($mapping, $resource))
                {
                    return '';
                }

                $currentArg = '';

                foreach ($resource[$mapping] as $resourceValue)
                {
                    // normally there should be only one value per mapping but just in case...
                    if ($currentArg != '')
                        $currentArg = $currentArg . $separator; // @ translate

                    $currentArg = $currentArg . $this->transformToBibtexEscapeString($this->extractStringValueFromProperty($resourceValue));
                }

                // add the current arg to the list of args
                $args[] = $currentArg;
            }

            // format may be ill-formated by user so be safe
            try {
                $transformStr = vsprintf($mappingObject['format'], $args);
            }
            catch (ValueError $e) {
                return '';
            }
        }

        return $transformStr;
    }

    // print "MyValue   = {" with the right spaces
    protected function transformToBibtexOpenValueBrackets($name)
    {
        $printedOut = "\t" . $name . ' = { ';
        return $printedOut;
    }

    // transform éric in {/'e}ric for example
    protected function transformToBibtexEscapeString($string)
    {
        $escapedString = "";

        $map = array( 
            "#", "\\#",
            "$", "\\$",
            "%", "\\%",
            "&", "\\&",
            "~", "\\~{}",
            "_", "\\_",
            "^", "\\^{}",
            "\\", "\\textbackslash",
            "{", "\\{",
            "}", "\\}",
            "é", "\\'{e}",
            "É", "\\'{E}",
            "È", "\\`{E}",
            "Ê", "\\^{E}",
            "ê", "\\^{e}",
            "è", "\\`{e}",
            "ë", "\\\"{e}",
            "Ë", "\\\"{E}",
            "à", "\\`{a}",
            "á", "\\'{a}",
            "â", "\\^{a}",
            "À", "\\`{A}",
            "Â", "\\^{A}",
            "Á", "\\'{A}",
            "ä", "\\\"{a}",
            "Ä", "\\\"{A}",
            "о́", "\\'{o}",
            "ò", "\\`{o}",
            "ô", "\\^{o}",
            "ö", "\\\"{o}",
            "Ö", "\\\"{O}",
            "Ó", "\\'{O}",
            "Ò", "\\`{O}",
            "Ô", "\\^{O}",
            "ù", "\\`{u}",
            "û", "\\^{u}",
            "ú", "\\'{u}",
            "ü", "\\\"{u}",
            "Ü", "\\\"{U}",
            "Ú", "\\'{U}",
            "Ù", "\\`{U}",
            "Û", "\\^{U}",
            "î", "\\^{i}",
            "í", "\\'{i}",
            "ì", "\\`{i}",
            "ï", "\\\"{i}",
            "Ï", "\\\"{I}",
            "Î", "\\^{I}",
            "Ì", "\\`{I}",
            "Í", "\\'{I}",
            "ŷ", "\\^{y}",
            "ý", "\\'{y}",
            "ỳ", "\\`{y}",
            "ÿ", "\\\"{y}",
            "Ÿ", "\\\"{Y}",
            "Ŷ", "\\^{Y}",
            "Ý", "\\'{Y}",
            "Ỳ", "\\`{Y}",
            "å", "\\aa",
            "Å", "\\AA",
            "œ", "\\oe",
            "Œ", "\\OE",
            "æ", "\\ae",
            "Æ", "\\AE",
            "ß", "\\ss",
            "ẞ", "\\SS",
            "ø", "\\o",
            "Ø", "\\O",
            "ł", "\\l",
            "Ł", "\\L",
        );

        $bFound = false;

        foreach (mb_str_split($string) as $char) {
            for ($i = 0; $i < count($map); $i = $i + 2)
            {
                if ($char == $map[$i])
                {
                    $escapedString = $escapedString . $map[$i + 1];
                    $bFound = true;
                    break;
                }
            }
            if (!$bFound)
                $escapedString = $escapedString . $char;
        }
        return $escapedString;
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

                                $bIsFirstPropertyOfLabel = false;
                            } else {
                                $resourcesStr = $resourcesStr . " ; ";
                            }
                            $resourcesStr = $resourcesStr . $this->extractStringValueFromProperty($propertyValueElement);
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

    // takes in a json (with arrays only) representing a resource property element like dcterms::title[0]
    // and extracts its value (so like "My Great Book" or if it's a URL then the label of the URL, etc.)
    protected function extractStringValueFromProperty($property)
    {
        if ($property['type'] == "literal")
            return $property['@value'];

        else if ($property['type'] == "uri")
            return $property['o:label'];

        else if ($property['type'] == "resource")
            return $property['display_title'];

        else if (str_contains($property['type'], "customvocab")) {
            if (array_key_exists('@value', $property))
            {
                return $property['@value'];
            }
            else if (array_key_exists('o:label', $property))
            {
                return $property['o:label'];
            }
            else if (array_key_exists('display_title', $property))
            {
                return $property['display_title'];
            }
        }

        return '';
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
