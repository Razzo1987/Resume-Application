<?php

// Solo ciò che cambia rispetto ai default di Livewire: caricamenti temporanei fino a 50 MB
// (zip dei temi e dei contenuti). Resta comunque il limite di PHP (upload_max_filesize, post_max_size).
return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:51200'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'bmp', 'svg', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
