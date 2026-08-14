<?php declare(strict_types=1);
/** @var string $pageTitle @var string $pageIcon @var array $pageJs @var string $pageSlug @var array $activeUser @var array $slcJs */
$assetBase = $slcJs['base'] . '/public/assets';
$assetVer = (string) time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e($slcJs['csrfToken']) ?>">
<title><?= e($pageTitle) ?> · Shree Label Digital Sales Person</title>
<link rel="stylesheet" href="<?= e($assetBase) ?>/css/app.css?v=<?= $assetVer ?>">
</head>
<body>
<div class="app">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="main" id="main">
    <?php require __DIR__ . '/header.php'; ?>
    <main class="content" id="pageContent">
      <?php require __DIR__ . '/' . $pageTemplate; ?>
    </main>
  </div>
</div>

<!-- toast / modal roots -->
<div class="toast-wrap" id="toasts"></div>
<div class="modal-root" id="modalRoot"></div>
<div class="slideover-root" id="slideoverRoot"></div>

<script>
  window.SLC = <?= je($slcJs) ?>;
</script>
<script src="<?= e($assetBase) ?>/js/app.js?v=<?= $assetVer ?>"></script>
<?php foreach ($pageJs as $f): ?>
  <script src="<?= e($assetBase) ?>/js/<?= e($f) ?>?v=<?= $assetVer ?>"></script>
<?php endforeach; ?>
</body>
</html>
