<?php

return [
    'batch' => env('MELAVA_BATCH', 'october_2026'),
    'batch_label' => env('MELAVA_BATCH_LABEL', "Melava 3 \u{00B7} October 2026"),
    'currency' => env('MELAVA_CURRENCY', 'INR'),
    'fee' => (float) env('MELAVA_FEE', 1200),
    'booklet_fee' => (float) env('MELAVA_BOOKLET_FEE', 300),
    'qr_image_url' => env('MELAVA_QR_IMAGE_URL', 'https://aaryansmanomilan.com/website/Manomilan QR.jpeg'),
];
