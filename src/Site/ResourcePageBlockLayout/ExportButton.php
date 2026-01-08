<?php
namespace Export\Site\ResourcePageBlockLayout;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;
use Laminas\View\Renderer\PhpRenderer;

class ExportButton implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Export button'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return ['items', 'item_sets', 'media'];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        $query = [
            'id' => $resource->id(),
        ];

        $resourceController = '';

        switch ($resource->resourceName()) {
            case 'media':
                $resourceController = 'Omeka\Controller\Site\Media';
                break;
            case 'item_sets':
                $resourceController = 'Omeka\Controller\Site\ItemSet';
                break;
            case 'items':
                $resourceController = 'Omeka\Controller\Site\Item';
                break;
            default:
                return '';
        }

        return $view->exportButton(false /* fromAdmin */, $resourceController, $query);
    }
}