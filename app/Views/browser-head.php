<?php
declare(strict_types=1);
require_once __DIR__ . '/asset-version.php';
$headAssetBase = $publicBase ?? $base ?? './';
?>
<title>อันเกิม - ร้านอาหารเวียดนาม</title>
<link rel="icon" href="<?= htmlspecialchars($headAssetBase . 'assets/images/restaurant-logo.svg?v=' . assetVersion('assets/images/restaurant-logo.svg'), ENT_QUOTES, 'UTF-8') ?>" type="image/svg+xml">
<style>
<?php foreach ([
    ['Noto Sans Thai', 'noto-sans-thai-400.ttf', 400], ['Noto Sans Thai', 'noto-sans-thai-500.ttf', 500],
    ['Noto Sans Thai', 'noto-sans-thai-600.ttf', 600], ['Noto Sans Thai', 'noto-sans-thai-700.ttf', 700],
    ['Noto', 'noto-sans-thai-400.ttf', 400], ['Noto', 'noto-sans-thai-700.ttf', 700],
    ['Staff Header Noto', 'noto-sans-thai-400.ttf', 400], ['Staff Header Noto', 'noto-sans-thai-600.ttf', 600], ['Staff Header Noto', 'noto-sans-thai-700.ttf', 700],
    ['SOV Patigon', 'sov-patigon.ttf', 400], ['SP KinKuSi', 'SP_KinKuSi.ttf', 400],
] as [$family, $font, $weight]):
    $fontUrl = $headAssetBase . 'assets/fonts/' . $font . '?v=' . assetVersion('assets/fonts/' . $font);
?>@font-face{font-family:'<?= $family ?>';font-style:normal;font-weight:<?= $weight ?>;font-display:swap;src:url('<?= htmlspecialchars($fontUrl, ENT_QUOTES, 'UTF-8') ?>') format('truetype')}
<?php endforeach ?>
</style>
