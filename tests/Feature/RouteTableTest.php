<?php
declare(strict_types=1);

it('registers every public API route and its controller', function () {
    $root = dirname(__DIR__, 2);
    $routes = require $root . '/routes/api.php';
    expect(array_keys($routes))->toContain('/api/auth.php', '/api/order.php', '/api/food-image.php', '/api/apiopenTalbe.php');
    foreach ($routes as $definition) {
        expect(is_file($root . '/app/Controllers/' . $definition['controller'] . '.php'))->toBeTrue();
        if ($definition['controller'] === 'api') {
            expect(is_file($root . '/app/Services/endpoint-' . $definition['handler'] . '.php'))->toBeTrue();
        }
    }
});
