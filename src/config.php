<?php

$validator = [
    'maxSize' => 20 * 1024 * 1024,
    'checkExtensionByMimeType' => true,
    'extensions' => ['jpg', 'jpeg', 'png']
];

function fileStorage($folder)
{
    return [
        'type' => 'file',
        'saveOriginalFilename' => false,
        'uploadDirPath' => __DIR__ . '/../storage/app/public/uploads/' . $folder,
        'uploadDirUrl' => '/uploads/' . $folder,
    ];
}

return [
    'scenarios' => [
        'default' => [
            'storage' => fileStorage('default'),
            'validator' => $validator,
            'thumbnails' => [
                'preview' => [
                    'width' => 380,
                    'height' => 250,
                    'crop' => true,
                    '2x' => true,
                    'webp' => true,
                    'quality' => 100
                ],
            ]
        ]
    ]
];
