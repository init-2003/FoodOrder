<?php
declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    'id' => './',
    'name' => 'อันเกิม · อาหารเวียดนาม',
    'short_name' => 'อันเกิม',
    'description' => 'สั่งอาหารอันเกิม',
    'lang' => 'th',
    'dir' => 'ltr',
    'start_url' => './',
    'scope' => './',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#fb784c',
    'icons' => [
        ['src' => 'assets/images/pwa-192.png?v=2', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/images/pwa-512.png?v=2', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/images/pwa-maskable-512.png?v=2', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
