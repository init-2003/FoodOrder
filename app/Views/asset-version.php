<?php
declare(strict_types=1);

/** Version public assets by their contents so copied timestamps cannot reuse stale CDN entries. */
function assetVersion(string $path): string {
    static $versions = [];
    if (!array_key_exists($path, $versions)) {
        $file = dirname(__DIR__, 2) . '/public/' . $path;
        $versions[$path] = hash_file('sha256', $file);
        if ($versions[$path] === false) throw new RuntimeException('Cannot version public asset: ' . $path);
    }
    return $versions[$path];
}
