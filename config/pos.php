<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

return [
    'mode' => getenv('POS_MODE') ?: 'live',
    'restaurant' => getenv('RESTAURANT_NAME') ?: 'อิ่มดี',
    'branch' => getenv('BRANCH_NAME') ?: 'สาขาสุขุมวิท',
    'branch_id' => getenv('DEFAULT_BRANCH_ID') ?: '',
];
