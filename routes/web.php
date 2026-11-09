<?php
declare(strict_types=1);
return [
    '/' => ['controller' => 'main'],
    '/staff/' => ['controller' => 'staff', 'view' => 'Staff/staff.php', 'base' => '../'],
    '/staff/menus/' => ['controller' => 'staff', 'view' => 'Staff/food-images.php', 'base' => '../../'],
    '/staff/food-images/' => ['controller' => 'staff', 'view' => 'Staff/food-images.php', 'base' => '../../'],
    '/staff/tables/' => ['controller' => 'staff', 'view' => 'Staff/tables.php', 'base' => '../../'],
];
