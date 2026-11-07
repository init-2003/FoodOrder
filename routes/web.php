<?php
declare(strict_types=1);
return [
    '/' => ['controller' => 'main'],
    '/staff/' => ['controller' => 'staff', 'view' => 'staff.php', 'base' => '../'],
    '/staff/menus/' => ['controller' => 'staff', 'view' => 'food-images.php', 'base' => '../../'],
    '/staff/food-images/' => ['controller' => 'staff', 'view' => 'food-images.php', 'base' => '../../'],
    '/staff/tables/' => ['controller' => 'staff', 'view' => 'tables.php', 'base' => '../../'],
];
