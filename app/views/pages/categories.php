<?php
$cats = Helper::cacheRemember('m_categories_page_v1', 300, static function (): array {
    return (new CategoryModel())->getTopLevel();
});

$pageTitle = 'Categories | FatakNews';
$pageDesc = 'Browse FatakNews by topic - politics, world, business, technology, sports, entertainment, health, and lifestyle.';
$canonicalUrl = Helper::siteUrl('categories');
$mBarTitle = 'Categories';
$mBarActions = '<a href="/search" class="m-iconbtn" aria-label="Search"><i class="fa fa-search"></i></a>';
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Categories', 'url' => $canonicalUrl],
];
$structuredData = [Helper::breadcrumbSchema($breadcrumbItems)];
include VIEW . 'layouts/header.php';
?>
<h1 class="m-screen-title">Categories</h1>
<div class="m-catlist">
  <?php foreach ($cats as $cat): ?>
  <a href="/category/<?= $cat['slug'] ?>" class="m-catrow">
    <span class="m-catrow-icon" style="<?= !empty($cat['color']) ? 'background:' . Helper::sanitize($cat['color']) . '1f;color:' . Helper::sanitize($cat['color']) : '' ?>">
      <i class="fa <?= Helper::sanitize($cat['icon'] ?: 'fa-newspaper') ?>"></i>
    </span>
    <span class="m-catrow-body">
      <strong><?= Helper::sanitize($cat['name']) ?></strong>
      <span><?= Helper::sanitize($cat['description'] ?: 'Latest ' . $cat['name'] . ' news and updates') ?></span>
    </span>
    <i class="fa fa-chevron-right"></i>
  </a>
  <?php endforeach; ?>
  <?php if (empty($cats)): ?>
  <div class="empty-state"><i class="fa fa-folder-open"></i><h3>No categories yet</h3></div>
  <?php endif; ?>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
