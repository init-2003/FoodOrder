<?php
declare(strict_types=1);

function renderStaffNavigation(string $route, bool $canViewExpenses, string $sidebarMode = 'server'): string
{
    ob_start();
    require dirname(__DIR__, 2) . '/app/Views/Staff/navigation.php';
    return (string) ob_get_clean();
}

it('shows the report only to staff with permission', function () {
    expect(renderStaffNavigation('/staff/', false))->not->toContain('href="staff/expenses/"')
        ->and(renderStaffNavigation('/staff/', true))->toContain('href="staff/expenses/"');
});

it('marks the current staff route including the legacy menu route', function (string $route, string $href) {
    $html = renderStaffNavigation($route, true);
    expect($html)->toContain('href="' . $href . '" aria-current="page"')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
})->with([
    ['/staff/', 'staff/'],
    ['/staff/tables/', 'staff/tables/'],
    ['/staff/menus/', 'staff/menus/'],
    ['/staff/food-images/', 'staff/menus/'],
    ['/staff/expenses/', 'staff/expenses/'],
]);

it('keeps the ordering sidebar permission-controlled and table navigation interactive', function () {
    $html = renderStaffNavigation('/staff/tables/', false, 'alpine');
    expect($html)->toContain('x-show="staff?.canViewExpenses"', '@click.prevent="openTableSelector()"', 'location.pathname.includes');
});
