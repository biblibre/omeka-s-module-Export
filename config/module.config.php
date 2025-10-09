<?php
namespace Export;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'factories' => [
            'Export\Form\ImportForm' => Service\Form\ImportFormFactory::class,
            'Export\Form\ExportButtonForm' => Service\Form\ExportButtonFormFactory::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Export\Controller\List' => Controller\ListController::class,
        ],
        'factories' => [
            'Export\Controller\Index' => Service\Controller\IndexControllerFactory::class,
            'Export\Controller\Site\Index' => Service\Controller\Site\IndexControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'export' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/export',
                            'defaults' => [
                                '__NAMESPACE__' => 'Export\Controller',
                                'controller' => 'Index',
                                'action' => 'export',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'download' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/download',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Export\Controller',
                                        'controller' => 'Index',
                                        'action' => 'download',
                                    ],
                                ],
                            ],
                            'list' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/list',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Export\Controller',
                                        'controller' => 'List',
                                        'action' => 'list',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'site' => [
                'child_routes' => [
                    'export' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/export',
                            'defaults' => [
                                '__NAMESPACE__' => 'Export\Controller\Site',
                                'controller' => 'Index',
                                'action' => 'download',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Export',
                'route' => 'admin/export',
                'resource' => 'Export\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Download', // @translate
                        'route' => 'admin/export/download',
                        'resource' => 'Export\Controller\Index',
                        'visible' => false,
                    ],
                    [
                        'label' => 'Download List', // @translate
                        'route' => 'admin/export/list',
                        'controller' => 'List',
                        'action' => 'list',
                        'resource' => 'Export\Controller\List',
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'exportButton' => Service\ViewHelper\ExportButtonFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Export\Exporter' => Service\ExporterFactory::class,
        ],
    ],
    'export_formats' => [
        'CSV' => '.csv',
        'JSON' => '.json',
        'TXT' => '.txt',
    ],
];
