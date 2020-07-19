<?php

use Ozerich\FileStorage\Utils\ConfigHelper;

return [
    'defaultStorage' => ConfigHelper::temporaryStorage(),
    'defaultValidator' => ConfigHelper::defaultValidator(),

    'scenarios' => [
        'zip' => [
            'storage' => ConfigHelper::fileStorage('zip')
        ],
        'image' => [
            'storage' => ConfigHelper::fileStorage('products'),
            'validator' => ConfigHelper::imageValidator(),
            'thumbnails' => [
                'preview' => ConfigHelper::thumbWithWebpAnd2x(380, 250),
                'og' => ConfigHelper::thumbOpenGraph(),
            ]
        ]
    ]
];
