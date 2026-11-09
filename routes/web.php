<?php
declare(strict_types=1);
return [
    '/' => ['controller' => 'main'],
    '/staff/' => ['controller' => 'staff', 'view' => 'Staff/staff.php', 'base' => '../'],
    '/staff/menus/' => ['controller' => 'staff', 'view' => 'Staff/menus.php', 'base' => '../../'],
    '/staff/food-images/' => ['controller' => 'staff', 'view' => 'Staff/menus.php', 'base' => '../../'],
    '/staff/expenses/' => ['controller' => 'staff', 'view' => 'Staff/expenses.php', 'base' => '../../'],
    '/staff/tables/' => ['controller' => 'staff', 'page' => 'tables', 'base' => '../../'],
];
