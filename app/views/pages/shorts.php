<?php
$db = Database::getInstance();
$page = max(1, (int)($_GET['page'] ?? 1));
$shorts = $db->paginate(
    "SELECT p.*, u.username, u.full_name, u.avatar, u.is_verified,
            c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     JOIN users u ON p.user_id = u.id
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.status = 'published' AND COALESCE(p.location,'') = 'shorts'
     ORDER BY COALESCE(p.published_at, p.created_at) DESC",
    [],
    $page,
    12
);

$pageTitle = 'Shorts - Quick Video News | FatakNews';
$pageDesc = 'Watch short vertical video news and clips on FatakNews.';
$metaRobots = 'index,follow,max-image-preview:large';
$canonicalUrl = Helper::siteUrl('shorts' . ($page > 1 ? '?page=' . $page : ''));
$bodyClass = 'shorts-page';
$mHideTopBar = true;

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
];
include VIEW . 'layouts/header.php';
?>
<div class="m-shorts m-only">
  <a href="/" class="m-shorts-back" aria-label="Back"><i data-lucide="arrow-left"></i></a>
  <span class="m-shorts-title">Shorts</span>

  <?php if (empty($shorts['data'])): ?>
  <div class="m-shorts-empty">
    <i data-lucide="clapperboard"></i>
    <h3>No shorts yet</h3>
    <p>Short video news is on the way.</p>
    <?php if (Auth::check() && Auth::isEmployee()): ?>
    <a href="/employee/create?placement=shorts" class="m-btn-logout" style="background:var(--m-accent);margin-top:14px">Create a Short</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="m-shorts-feed" id="mShortsFeed">
    <?php foreach ($shorts['data'] as $post):
      $shortUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
      $poster = Helper::thumbnailAssetUrl($post['thumbnail'] ?? null);
    ?>
    <section class="m-short">
      <?= Helper::shortEmbedHtml($post['video_url'] ?? '', $poster) ?>
      <div class="m-short-shade"></div>
      <div class="m-short-overlay">
        <?php if (!empty($post['category_name'])): ?>
        <a href="/category/<?= $post['category_slug'] ?>" class="m-short-cat"><?= Helper::sanitize($post['category_name']) ?></a>
        <?php endif; ?>
        <a href="<?= $shortUrl ?>" class="m-short-headline"><?= Helper::sanitize($post['title']) ?></a>
        <a href="/@<?= $post['username'] ?>" class="m-short-author">
          <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="">
          <span><?= Helper::sanitize($post['full_name'] ?: ('@' . $post['username'])) ?></span>
          <?php if (!empty($post['is_verified'])): ?><i data-lucide="badge-check"></i><?php endif; ?>
        </a>
      </div>
      <div class="m-short-actions">
        <button type="button" class="m-short-act" data-react="<?= $post['id'] ?>" data-type="like">
          <i data-lucide="heart"></i><span class="react-count"><?= Helper::formatNumber((int)$post['likes_count']) ?></span>
        </button>
        <a class="m-short-act" href="<?= $shortUrl ?>#comments">
          <i data-lucide="message-circle"></i><span><?= Helper::formatNumber((int)$post['comments_count']) ?></span>
        </a>
        <button type="button" class="m-short-act" data-bookmark="<?= $post['id'] ?>">
          <i data-lucide="bookmark"></i><span>Save</span>
        </button>
        <button type="button" class="m-short-act" data-share-title="<?= Helper::sanitize($post['title']) ?>" data-share-url="<?= Helper::sanitize(Helper::siteUrl(ltrim($shortUrl, '/'))) ?>">
          <i data-lucide="share-2"></i><span>Share</span>
        </button>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
  <script>
  (function () {
    var vids = document.querySelectorAll('.m-short video.m-short-media');
    if (!('IntersectionObserver' in window) || !vids.length) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        var v = e.target;
        if (e.isIntersecting && e.intersectionRatio > 0.6) { v.play().catch(function(){}); }
        else { v.pause(); }
      });
    }, { threshold: [0, 0.6, 1] });
    vids.forEach(function (v) { io.observe(v); });
  })();
  </script>
  <?php endif; ?>
</div>

<div class="d-only" style="max-width:720px;margin:40px auto;padding:0 20px">
  <h1 style="font-size:28px;margin-bottom:16px">Shorts</h1>
  <?php if (empty($shorts['data'])): ?>
  <p style="color:var(--muted)">No shorts published yet.</p>
  <?php else: ?>
  <div class="news-feed">
    <?php foreach ($shorts['data'] as $post):
      $shortUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug']; ?>
    <article class="news-card">
      <a href="<?= $shortUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" class="news-card-img" alt="" width="160" height="110" loading="lazy"></a>
      <div style="flex:1"><div class="news-card-body">
        <h3 class="news-card-title"><a href="<?= $shortUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
        <div class="news-card-meta"><span><?= Helper::sanitize($post['full_name']) ?></span><span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span></div>
      </div></div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
