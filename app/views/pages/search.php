<?php
$q = trim($_GET['q'] ?? '');
$pageTitle = $q !== '' ? 'Search results for "' . $q . '" - FatakNews' : 'Search - FatakNews';
$pageDesc = $q !== '' ? 'Search results on FatakNews for "' . $q . '".' : 'Search FatakNews stories, topics, and people.';
$metaRobots = 'noindex,follow';
$db = Database::getInstance();
$userModel = new UserModel();
$page = max(1, (int)($_GET['page'] ?? 1));
$results = ['data' => [], 'pages' => 1, 'page' => 1];
$users = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $results = $db->paginate(
        "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)
         ORDER BY p.published_at DESC",
        [$like, $like, $like],
        $page
    );
    $users = $userModel->search($q, 6);
}
$searchQueryString = [];
if ($q !== '') {
    $searchQueryString['q'] = $q;
}
if ($page > 1) {
    $searchQueryString['page'] = $page;
}
$canonicalUrl = Helper::siteUrl('search' . (!empty($searchQueryString) ? '?' . http_build_query($searchQueryString) : ''));
$prevQuery = $q !== '' ? ['q' => $q] : [];
$prevUrl = $page > 1 ? Helper::siteUrl('search' . '?' . http_build_query($prevQuery + ($page > 2 ? ['page' => $page - 1] : []))) : null;
$nextUrl = $page < (int)($results['pages'] ?? 1) ? Helper::siteUrl('search' . '?' . http_build_query(($q !== '' ? ['q' => $q] : []) + ['page' => $page + 1])) : null;
$bodyClass = 'search-page';
$mBarTitle = 'Search';
$mTrendingSearches = Helper::cacheRemember('m_trending_searches_v1', 600, static function () use ($db): array {
    return $db->fetchAll(
        "SELECT t.name FROM tags t
         JOIN post_tags pt ON pt.tag_id=t.id
         JOIN posts p ON p.id=pt.post_id AND p.status='published'
         GROUP BY t.id ORDER BY COUNT(*) DESC, MAX(p.published_at) DESC LIMIT 10"
    );
});
include VIEW . 'layouts/header.php';
?>
<div class="m-only m-searchscreen">
  <form action="/search" method="get" class="m-searchbar">
    <i class="fa fa-magnifying-glass"></i>
    <input type="text" name="q" value="<?= Helper::sanitize($q) ?>" placeholder="Search news, topics or keywords..." autocomplete="off" <?= $q === '' ? 'autofocus' : '' ?>>
    <?php if ($q !== ''): ?><a href="/search" class="m-searchbar-clear" aria-label="Clear"><i class="fa fa-xmark"></i></a><?php endif; ?>
  </form>

  <?php if ($q === ''): ?>
  <?php if (!empty($mTrendingSearches)): ?>
  <div class="m-section-head"><h2><i class="fa fa-arrow-trend-up"></i> Trending Searches</h2></div>
  <div class="m-searchtags">
    <?php foreach ($mTrendingSearches as $ts): ?>
    <a href="/search?q=<?= urlencode($ts['name']) ?>" class="m-chip"><?= Helper::sanitize($ts['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="m-section-head" id="mRecentHead" hidden><h2><i class="fa fa-clock-rotate-left"></i> Recent Searches</h2><a href="#" id="mRecentClear">Clear</a></div>
  <div class="m-searchtags" id="mRecentTags"></div>
  <script>
  (function(){
    try{
      var r=JSON.parse(localStorage.getItem('fn-recent-search')||'[]');
      if(r.length){
        document.getElementById('mRecentHead').hidden=false;
        document.getElementById('mRecentTags').innerHTML=r.slice(0,8).map(function(q){
          return '<a class="m-chip" href="/search?q='+encodeURIComponent(q)+'">'+q.replace(/[<>&]/g,'')+'</a>';
        }).join('');
      }
      document.getElementById('mRecentClear').addEventListener('click',function(e){
        e.preventDefault();localStorage.removeItem('fn-recent-search');location.reload();
      });
    }catch(e){}
  })();
  </script>
  <?php else: ?>
  <script>try{var q=<?= json_encode($q) ?>;var r=JSON.parse(localStorage.getItem('fn-recent-search')||'[]');r=[q].concat(r.filter(function(x){return x!==q;})).slice(0,10);localStorage.setItem('fn-recent-search',JSON.stringify(r));}catch(e){}</script>
  <?php if (!empty($users)): ?>
  <div class="m-section-head"><h2><i class="fa fa-user"></i> People</h2></div>
  <div class="m-list">
    <?php foreach ($users as $u): ?>
    <a href="/@<?= $u['username'] ?>" class="m-item" style="align-items:center">
      <img class="m-item-thumb" style="width:44px;height:44px;border-radius:50%" src="<?= Helper::avatarUrl($u['avatar']) ?>" alt="">
      <span class="m-item-body"><h3 style="-webkit-line-clamp:1"><?= Helper::sanitize($u['full_name']) ?></h3><span class="m-meta">@<?= Helper::sanitize($u['username']) ?></span></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="m-section-head"><h2><i class="fa fa-newspaper"></i> Results</h2></div>
  <div class="m-list">
    <?php foreach ($results['data'] as $post):
      $pu = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
    ?>
    <a href="<?= $pu ?>" class="m-item">
      <img class="m-item-thumb" src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize($post['title'] ?? '') ?>" loading="lazy" decoding="async">
      <span class="m-item-body">
        <?php if (!empty($post['category_name'])): ?><span class="m-kicker"><?= Helper::sanitize($post['category_name']) ?></span><?php endif; ?>
        <h3><?= Helper::sanitize($post['title']) ?></h3>
        <span class="m-meta"><span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span><span><i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($post['views_count'] ?? 0)) ?></span></span>
      </span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($results['data']) && empty($users)): ?>
    <div class="empty-state"><i class="fa fa-magnifying-glass"></i><h3>No results for "<?= Helper::sanitize($q) ?>"</h3><p>Try broader keywords or check spelling.</p></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="home-grid d-only">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px">
      <div class="widget-title"><i class="fa fa-magnifying-glass"></i> Search</div>
      <form action="/search" method="get">
        <input class="form-control" type="text" name="q" value="<?= Helper::sanitize($q) ?>" placeholder="Search posts, topics, and people...">
      </form>
    </section>

    <?php if ($q === ''): ?>
    <div class="empty-state"><i class="fa fa-search"></i><h3>Type something to search</h3><p>Results will appear for matching posts and people.</p></div>
    <?php else: ?>
      <?php if (!empty($users)): ?>
      <section class="sidebar-widget" style="margin-bottom:24px">
        <div class="widget-title"><i class="fa fa-users"></i> People</div>
        <?php foreach ($users as $user): ?>
        <div class="suggest-item">
          <a href="/@<?= $user['username'] ?>"><img src="<?= Helper::avatarUrl($user['avatar']) ?>" alt="<?= Helper::sanitize($user['full_name']) ?>" class="suggest-avatar" width="40" height="40" loading="lazy" decoding="async"></a>
          <div class="suggest-info">
            <strong><a href="/@<?= $user['username'] ?>"><?= Helper::sanitize($user['full_name']) ?></a></strong>
            <span>@<?= $user['username'] ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <div class="news-feed">
        <?php foreach ($results['data'] as $index => $post):
          $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
        ?>
        <article class="news-card">
          <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img" width="160" height="110" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async"></a>
          <div style="flex:1">
            <div class="news-card-body">
              <?php if (!empty($post['category_name'])): ?>
              <a href="/category/<?= $post['category_slug'] ?>" class="news-card-cat" style="color:<?= $post['category_color'] ?>"><?= Helper::sanitize($post['category_name']) ?></a>
              <?php endif; ?>
              <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
              <div style="font-size:13px;color:var(--muted)"><?= Helper::sanitize(Helper::excerpt($post['excerpt'] ?: $post['content'], 140)) ?></div>
            </div>
            <div class="news-card-actions">
              <button class="action-btn" data-react="<?= $post['id'] ?>" data-type="like"><i class="fa fa-heart"></i><span class="react-count"><?= Helper::formatNumber($post['likes_count']) ?></span></button>
              <a href="<?= $postUrl ?>#comments" class="action-btn"><i class="fa fa-comment"></i><span><?= Helper::formatNumber($post['comments_count']) ?></span></a>
              <button class="action-btn" data-bookmark="<?= $post['id'] ?>"><i class="fa fa-bookmark"></i></button>
              <span class="action-btn" style="margin-left:auto;cursor:default"><i class="fa fa-eye"></i> <?= Helper::formatNumber($post['views_count']) ?></span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <?php if (empty($results['data']) && empty($users)): ?>
      <div class="empty-state"><i class="fa fa-ban"></i><h3>No results for "<?= Helper::sanitize($q) ?>"</h3><p>Try broader keywords or check spelling.</p></div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-lightbulb"></i> Search Tips</div>
      <p style="font-size:14px;color:var(--muted)">Use names, topics, or category words. The local search uses title, excerpt, content, and usernames.</p>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
