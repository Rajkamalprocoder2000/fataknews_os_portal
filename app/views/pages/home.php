<?php
require_once BASE_PATH . '/includes/bootstrap.php';

$pageTitle = 'FatakNews: Breaking News, Latest Headlines & Live Updates';
$pageDesc = 'Read breaking news, latest headlines, trending stories, and live updates from India across politics, business, sports, technology, and entertainment on FatakNews.';
$postModel = new PostModel();
$catModel = new CategoryModel();
$storyModel = new StoryModel();
$isAuthenticatedHome = Auth::check();
$breaking = $isAuthenticatedHome
    ? $postModel->getBreaking()
    : Helper::cacheRemember('home_breaking_v1', 60, static function () use ($postModel): array {
        return $postModel->getBreaking();
    });
$featured = $isAuthenticatedHome
    ? $postModel->getFeatured()
    : Helper::cacheRemember('home_featured_v1', 120, static function () use ($postModel): array {
        return $postModel->getFeatured();
    });
$trending = $isAuthenticatedHome
    ? $postModel->getTrending()
    : Helper::cacheRemember('home_trending_v1', 120, static function () use ($postModel): array {
        return $postModel->getTrending();
    });
$categories = $isAuthenticatedHome
    ? $catModel->getTopLevel()
    : Helper::cacheRemember('home_categories_v1', 300, static function () use ($catModel): array {
        return $catModel->getTopLevel();
    });
$categoryTree = $isAuthenticatedHome
    ? $catModel->getTree()
    : Helper::cacheRemember('home_category_tree_v1', 300, static function () use ($catModel): array {
        return $catModel->getTree();
    });
$homeStoryRoles = ['super_admin', 'admin', 'manager', 'editor', 'reporter', 'hr'];
$storyGroups = $isAuthenticatedHome
    ? $storyModel->getActiveGroups(Auth::id(), 12, $homeStoryRoles)
    : Helper::cacheRemember('home_story_groups_v1', 60, static function () use ($storyModel, $homeStoryRoles): array {
        return $storyModel->getActiveGroups(null, 12, $homeStoryRoles);
    });
$page = max(1, (int)($_GET['page'] ?? 1));
$feed = $isAuthenticatedHome
    ? $postModel->getLatest($page)
    : Helper::cacheRemember('home_feed_v1_' . $page, 60, static function () use ($postModel, $page): array {
        return $postModel->getLatest($page);
    });
$db = Database::getInstance();
$renderMobileHome = Helper::isMobileClient();
$categoryTreeById = [];
foreach ($categoryTree as $treeNode) {
    $categoryTreeById[(int)$treeNode['id']] = $treeNode;
}
$collectCategoryIds = static function (array $node) use (&$collectCategoryIds): array {
    $ids = [(int)($node['id'] ?? 0)];
    foreach (($node['children'] ?? []) as $childNode) {
        $ids = array_merge($ids, $collectCategoryIds($childNode));
    }
    return $ids;
};
$categoryTopMap = [];
foreach ($categories as $categoryItem) {
    $categoryId = (int)($categoryItem['id'] ?? 0);
    $categoryNode = $categoryTreeById[$categoryId] ?? ($categoryItem + ['children' => []]);
    $categoryIds = array_values(array_filter(array_unique($collectCategoryIds($categoryNode))));
    foreach ($categoryIds as $descendantId) {
        $categoryTopMap[(int)$descendantId] = $categoryId;
    }
}

$categorySpotlights = Helper::cacheRemember(
    'home_category_spotlights_v3_' . md5(json_encode(array_column($categories, 'id'))),
    300,
    static function () use ($categories, $db, $postModel, $categoryTopMap): array {
        if (empty($categories) || empty($categoryTopMap)) {
            return [];
        }

        $allCategoryIds = array_values(array_unique(array_map('intval', array_keys($categoryTopMap))));
        $placeholders = implode(',', array_fill(0, count($allCategoryIds), '?'));
        $rows = $db->fetchAll(
            "SELECT p.title, p.slug, p.category_id, p.views_count, p.published_at, c.slug AS category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published'
               AND " . $postModel->getHomeVisibilityFilter('p') . "
               AND p.type IN ('news','article','breaking')
               AND p.category_id IN ($placeholders)
             ORDER BY COALESCE(p.published_at, p.created_at) DESC",
            $allCategoryIds
        );

        $postsByTop = [];
        $remainingSlots = max(1, count($categories)) * 5;

        foreach ($rows as $row) {
            $topCategoryId = (int)($categoryTopMap[(int)($row['category_id'] ?? 0)] ?? 0);
            if ($topCategoryId <= 0) {
                continue;
            }

            $postsByTop[$topCategoryId] ??= [];
            if (count($postsByTop[$topCategoryId]) >= 5) {
                continue;
            }

            $postsByTop[$topCategoryId][] = $row;
            $remainingSlots--;

            if ($remainingSlots <= 0) {
                break;
            }
        }

        $spotlights = [];
        foreach ($categories as $categoryItem) {
            $categoryId = (int)($categoryItem['id'] ?? 0);
            $spotlights[] = [
                'category' => $categoryItem,
                'posts' => $postsByTop[$categoryId] ?? [],
            ];
        }

        return $spotlights;
    }
);
$exploreLatest = $isAuthenticatedHome
    ? $db->fetchAll(
        "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND p.location='explore'
         ORDER BY COALESCE(p.published_at, p.created_at) DESC
         LIMIT 5"
    )
    : Helper::cacheRemember('home_explore_latest_v1', 120, static function () use ($db): array {
        return $db->fetchAll(
            "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM posts p
             JOIN users u ON p.user_id=u.id
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published' AND p.location='explore'
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT 5"
        );
    });
$communityLatest = $isAuthenticatedHome
    ? $db->fetchAll(
        "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         JOIN users u ON p.user_id=u.id
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published' AND p.type IN ('community_post','thought')
         ORDER BY COALESCE(p.published_at, p.created_at) DESC
         LIMIT 5"
    )
    : Helper::cacheRemember('home_community_latest_v1', 120, static function () use ($db): array {
        return $db->fetchAll(
            "SELECT p.*, u.full_name, u.avatar, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM posts p
             JOIN users u ON p.user_id=u.id
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published' AND p.type IN ('community_post','thought')
             ORDER BY COALESCE(p.published_at, p.created_at) DESC
             LIMIT 5"
        );
    });
$suggestedUsers = $isAuthenticatedHome
    ? $db->fetchAll(
        "SELECT u.id,u.username,u.full_name,u.avatar,u.is_verified,u.badge_level,u.followers_count
         FROM users u
         WHERE u.role_id IN (1,2,3,4,5) AND u.is_active=1
         ORDER BY u.followers_count DESC
         LIMIT 5"
    )
    : Helper::cacheRemember('home_suggested_users_v1', 300, static function () use ($db): array {
        return $db->fetchAll(
            "SELECT u.id,u.username,u.full_name,u.avatar,u.is_verified,u.badge_level,u.followers_count
             FROM users u
             WHERE u.role_id IN (1,2,3,4,5) AND u.is_active=1
             ORDER BY u.followers_count DESC
             LIMIT 5"
        );
    });
$newsletterStatus = trim((string)($_GET['newsletter'] ?? ''));
$canonicalUrl = Helper::siteUrl();
$pageImage = Helper::thumbnailAssetUrl($breaking[0]['thumbnail'] ?? null) ?? Helper::thumbnailAssetUrl($featured[0]['thumbnail'] ?? null) ?? Helper::defaultShareImage();
$heroPreloadImage = Helper::thumbnailAssetUrl($breaking[0]['thumbnail'] ?? null)
    ?? Helper::thumbnailAssetUrl($featured[0]['thumbnail'] ?? null)
    ?? Helper::thumbnailAssetUrl($feed['data'][0]['thumbnail'] ?? null);
if ($heroPreloadImage) {
    $extraHead = trim((string)($extraHead ?? '') . "\n" . '<link rel="preload" as="image" href="' . Helper::sanitize($heroPreloadImage) . '">');
}
$bodyClass = 'home-page';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'FatakNews.in',
        'url' => $canonicalUrl,
        'description' => $pageDesc,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => Helper::siteUrl('search') . '?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ],
    Helper::organizationSchema(),
    Helper::collectionItemListSchema(array_slice(array_values(array_merge($breaking ?: [], $featured ?: [], $feed['data'] ?? [])), 0, 12), $canonicalUrl),
];

include VIEW . 'layouts/header.php';
?>
<?php if ($renderMobileHome): ?>
<?php
$mLead = $breaking[0] ?? ($featured[0] ?? ($feed['data'][0] ?? null));
$mFeedRest = $feed['data'] ?? [];
if ($mLead && !empty($mFeedRest) && (int)($mFeedRest[0]['id'] ?? 0) === (int)($mLead['id'] ?? -1)) {
    $mFeedRest = array_slice($mFeedRest, 1);
}
$mChips = array_slice($categories, 0, 12);
$mTrending = array_slice($trending ?? [], 0, 6);
?>
<div class="m-chips m-only">
  <a href="/feed" class="m-chip active">For You</a>
  <?php foreach ($mChips as $c): ?>
  <a href="/category/<?= $c['slug'] ?>" class="m-chip"><?= Helper::sanitize($c['name']) ?></a>
  <?php endforeach; ?>
  <a href="/categories" class="m-chip" aria-label="All categories"><i data-lucide="menu"></i></a>
</div>

<?php $mStories = array_slice($storyGroups ?? [], 0, 12); if (!empty($mStories)): ?>
<div class="m-stories m-only">
  <?php foreach ($mStories as $sg): ?>
  <a href="/community" class="m-story <?= !empty($sg['has_unseen']) ? 'is-unseen' : '' ?>">
    <span class="m-story-ring"><img src="<?= Helper::avatarUrl($sg['avatar']) ?>" alt="<?= Helper::sanitize($sg['full_name'] ?? '') ?>" loading="lazy"></span>
    <span class="m-story-name"><?= Helper::sanitize(explode(' ', trim((string)($sg['full_name'] ?: $sg['username'])))[0]) ?></span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($mLead):
  $leadUrl = '/' . ($mLead['category_slug'] ?: 'news') . '/' . $mLead['slug'];
?>
<a href="<?= $leadUrl ?>" class="m-hero m-only">
  <span class="m-hero-media">
    <img src="<?= Helper::thumbnailUrl($mLead['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($mLead['image_alt'] ?? '', $mLead['title'] ?? '')) ?>" loading="eager" fetchpriority="high">
    <?php if (!empty($mLead['category_name'])): ?><span class="m-badge"><?= Helper::sanitize($mLead['category_name']) ?></span><?php endif; ?>
  </span>
  <span class="m-hero-body">
    <h3><?= Helper::sanitize($mLead['title']) ?></h3>
    <span class="m-meta">
      <span><i data-lucide="clock"></i> <?= Helper::timeAgo($mLead['published_at'] ?? $mLead['created_at']) ?></span>
      <span><i data-lucide="eye"></i> <?= Helper::formatNumber((int)($mLead['views_count'] ?? 0)) ?> views</span>
    </span>
  </span>
</a>
<?php endif; ?>

<div class="m-only">
  <div class="m-section-head"><h2><i data-lucide="zap"></i> Top Stories</h2><a href="/feed">See all</a></div>
  <div class="m-list">
    <?php foreach ($mFeedRest as $post):
      $pu = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
    ?>
    <a href="<?= $pu ?>" class="m-item">
      <img class="m-item-thumb" src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" loading="lazy" decoding="async">
      <span class="m-item-body">
        <?php if (!empty($post['category_name'])): ?><span class="m-kicker"><?= Helper::sanitize($post['category_name']) ?></span><?php endif; ?>
        <h3><?= Helper::sanitize($post['title']) ?></h3>
        <span class="m-meta">
          <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
          <span><i data-lucide="eye"></i> <?= Helper::formatNumber((int)($post['views_count'] ?? 0)) ?></span>
        </span>
      </span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($mFeedRest) && !$mLead): ?>
    <div class="empty-state"><i class="fa fa-newspaper"></i><h3>No news yet</h3><p>Check back soon for the latest updates.</p></div>
    <?php endif; ?>
  </div>

  <?php if (!empty($mTrending)): ?>
  <div class="m-section-head"><h2><i data-lucide="flame"></i> Trending</h2><a href="/trending">See all</a></div>
  <div class="m-list">
    <?php foreach ($mTrending as $i => $post):
      $pu = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
    ?>
    <a href="<?= $pu ?>" class="m-rankitem">
      <span class="m-rank-num"><?= $i + 1 ?></span>
      <img class="m-item-thumb" src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize($post['title'] ?? '') ?>" loading="lazy" decoding="async">
      <span class="m-item-body">
        <?php if (!empty($post['category_name'])): ?><span class="m-kicker"><?= Helper::sanitize($post['category_name']) ?></span><?php endif; ?>
        <h3><?= Helper::sanitize($post['title']) ?></h3>
        <span class="m-meta">
          <span><i data-lucide="eye"></i> <?= Helper::formatNumber((int)($post['views_count'] ?? 0)) ?></span>
          <span><?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?></span>
        </span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?= Helper::paginationNav((int)($feed['page'] ?? $page), (int)($feed['pages'] ?? 1), '/') ?>
</div>
<?php else: ?>
<div class="home-grid home-desktop-only">
  <main class="feed-col">
    <h1 class="sr-only">FatakNews latest breaking news, trending stories, and category updates</h1>
    <?php if (!empty($breaking)): ?>
    <section class="hero-section" aria-label="Breaking News">
      <?php foreach ($breaking as $i => $b): ?>
      <?php
        $heroThumb = $b['thumbnail'] ?? '';
        $heroCategorySlug = $b['category_slug'] ?? 'news';
        $heroTitle = $b['title'] ?? 'Untitled story';
        $heroSlug = $b['slug'] ?? '';
        $heroAuthor = $b['full_name'] ?? 'FatakNews Desk';
      ?>
      <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>">
        <?php if ($heroThumb !== ''): ?>
        <img src="<?= Helper::thumbnailUrl($heroThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($b['image_alt'] ?? '', $heroTitle)) ?>" width="1200" height="675" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"<?= $i === 0 ? ' fetchpriority="high"' : '' ?> decoding="async">
        <?php else: ?>
        <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--bg3),var(--card2))"></div>
        <?php endif; ?>
        <div class="hero-overlay"></div>
        <div class="hero-content">
          <span class="hero-badge"><i class="fa fa-bolt"></i> BREAKING</span>
          <h2 class="hero-title">
            <a href="/<?= $heroCategorySlug ?>/<?= $heroSlug ?>"><?= Helper::sanitize($heroTitle) ?></a>
          </h2>
          <div class="hero-meta">
            <span><i class="fa fa-user"></i> <?= Helper::sanitize($heroAuthor) ?></span>
            <span><i class="fa fa-clock"></i> <?= !empty($b['published_at']) ? Helper::timeAgo($b['published_at']) : 'Just now' ?></span>
            <span><i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($b['views_count'] ?? 0)) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="hero-dots">
        <?php foreach ($breaking as $i => $b): ?>
        <div class="hero-dot <?= $i === 0 ? 'active' : '' ?>"></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($featured)): ?>
    <section>
      <div class="widget-title"><i class="fa fa-star"></i> Featured</div>
      <div class="featured-grid">
        <?php foreach (array_slice($featured, 0, 3) as $f): ?>
        <?php
          $featuredCategorySlug = $f['category_slug'] ?? 'news';
          $featuredCategoryName = $f['category_name'] ?? 'General';
          $featuredCategoryColor = $f['category_color'] ?? 'var(--red)';
          $featuredTitle = $f['title'] ?? 'Untitled story';
          $featuredSlug = $f['slug'] ?? '';
        ?>
        <article class="featured-card">
          <a href="/<?= $featuredCategorySlug ?>/<?= $featuredSlug ?>">
            <img src="<?= Helper::thumbnailUrl($f['thumbnail'] ?? '') ?>" alt="<?= Helper::sanitize(Helper::imageAlt($f['image_alt'] ?? '', $featuredTitle)) ?>" class="featured-card-img" width="480" height="270" loading="lazy" decoding="async">
          </a>
          <div class="featured-card-body">
            <a href="/category/<?= $featuredCategorySlug ?>" class="featured-card-cat" style="color:<?= $featuredCategoryColor ?>"><?= Helper::sanitize($featuredCategoryName) ?></a>
            <h3 class="featured-card-title">
              <a href="/<?= $featuredCategorySlug ?>/<?= $featuredSlug ?>"><?= Helper::sanitize($featuredTitle) ?></a>
            </h3>
            <div class="featured-card-meta">
              <span><?= Helper::sanitize($f['full_name'] ?? 'FatakNews Desk') ?></span>
              <span>&middot;</span>
              <span><?= !empty($f['published_at']) ? Helper::timeAgo($f['published_at']) : 'Just now' ?></span>
              <span>&middot;</span>
              <span><?= (int)($f['reading_time'] ?? 1) ?> min read</span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="category-tabs">
      <div class="cat-tab active" data-cat="">All</div>
      <?php foreach ($categories as $cat): ?>
      <div class="cat-tab" data-cat="<?= $cat['id'] ?>" style="--cat-color:<?= $cat['color'] ?>">
        <i class="fa <?= $cat['icon'] ?>" style="color:<?= $cat['color'] ?>"></i>
        <?= Helper::sanitize($cat['name']) ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="home-latest-grid">
      <div class="home-latest-main">
        <div class="widget-title"><i class="fa fa-newspaper"></i> Latest Articles</div>
        <div class="news-feed" id="newsFeed">
          <?php foreach ($feed['data'] as $post): ?>
          <?php
            $postThumb = $post['thumbnail'] ?? '';
            $postCategorySlug = $post['category_slug'] ?? 'news';
            $postCategoryName = $post['category_name'] ?? '';
            $postCategoryColor = $post['category_color'] ?? 'var(--red)';
            $postTitle = $post['title'] ?? 'Untitled story';
            $postSlug = $post['slug'] ?? '';
            $postShareUrl = Helper::siteUrl($postCategorySlug . '/' . $postSlug);
          ?>
          <article class="news-card" style="--card-cat-color:<?= Helper::sanitize($postCategoryColor) ?>">
            <?php if ($postThumb !== ''): ?>
            <a href="/<?= $postCategorySlug ?>/<?= $postSlug ?>">
              <img src="<?= Helper::thumbnailUrl($postThumb) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $postTitle)) ?>" class="news-card-img" width="160" height="110" loading="lazy" decoding="async">
            </a>
            <?php endif; ?>
            <div class="news-card-body">
              <?php if ($postCategoryName !== ''): ?>
              <a href="/category/<?= $postCategorySlug ?>" class="news-card-cat" style="color:<?= $postCategoryColor ?>"><?= Helper::sanitize($postCategoryName) ?></a>
              <?php endif; ?>
              <h3 class="news-card-title">
                <a href="/<?= $postCategorySlug ?>/<?= $postSlug ?>"><?= Helper::sanitize($postTitle) ?></a>
              </h3>
              <div class="news-card-meta">
                <img src="<?= Helper::avatarUrl($post['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($post['full_name'] ?? 'FatakNews Desk') ?>" width="20" height="20" decoding="async">
                <span><?= Helper::sanitize($post['full_name'] ?? 'FatakNews Desk') ?></span>
                <span>&middot;</span>
                <span><?= !empty($post['published_at']) ? Helper::timeAgo($post['published_at']) : 'Just now' ?></span>
                <span>&middot;</span>
                <span><?= (int)($post['reading_time'] ?? 1) ?> min read</span>
              </div>
              <div class="news-card-actions">
                <button type="button" class="action-btn <?= Auth::check() ? 'liked' : '' ?>" data-react="<?= (int)$post['id'] ?>" data-type="like">
                  <i class="fa fa-heart"></i>
                  <span class="react-count"><?= Helper::formatNumber((int)($post['likes_count'] ?? 0)) ?></span>
                </button>
                <a href="/<?= $postCategorySlug ?>/<?= $postSlug ?>#comments" class="action-btn">
                  <i class="fa fa-comment"></i>
                  <span><?= Helper::formatNumber((int)($post['comments_count'] ?? 0)) ?></span>
                </a>
                <button type="button" class="action-btn" data-bookmark="<?= (int)$post['id'] ?>">
                  <i class="fa fa-bookmark"></i>
                </button>
                <button
                  type="button"
                  class="action-btn"
                  data-share-title="<?= Helper::sanitize($postTitle) ?>"
                  data-share-url="<?= Helper::sanitize($postShareUrl) ?>"
                  aria-label="Share story"
                >
                  <i class="fa fa-share-alt"></i>
                </button>
                <span class="action-btn" style="margin-left:auto;cursor:default">
                  <i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($post['views_count'] ?? 0)) ?>
                </span>
              </div>
            </div>
          </article>
          <?php endforeach; ?>

          <?php if (empty($feed['data'])): ?>
          <div class="empty-state">
            <i class="fa fa-newspaper"></i>
            <h3>No news yet</h3>
            <p>Check back soon for the latest updates!</p>
          </div>
          <?php endif; ?>
        </div>

        <?php if (($feed['pages'] ?? 0) > 1): ?>
        <div class="pagination">
          <?php if (($feed['page'] ?? 1) > 1): ?>
          <a href="?page=<?= $feed['page'] - 1 ?>" class="page-btn"><i class="fa fa-chevron-left"></i></a>
          <?php endif; ?>
          <?php for ($i = max(1, ($feed['page'] ?? 1) - 2); $i <= min(($feed['pages'] ?? 1), ($feed['page'] ?? 1) + 2); $i++): ?>
          <a href="?page=<?= $i ?>" class="page-btn <?= $i === ($feed['page'] ?? 1) ? 'active' : '' ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if (($feed['page'] ?? 1) < ($feed['pages'] ?? 1)): ?>
          <a href="?page=<?= $feed['page'] + 1 ?>" class="page-btn"><i class="fa fa-chevron-right"></i></a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <aside class="home-latest-sidebar">
        <div class="sidebar-widget home-sidebar-feed-widget">
          <div class="widget-title"><i class="fa fa-fire" style="color:var(--red)"></i> Trending Now</div>
          <?php foreach ($trending as $i => $t): ?>
          <?php
            $trendCategorySlug = $t['category_slug'] ?? 'news';
            $trendCategoryColor = $t['category_color'] ?? 'var(--red)';
          ?>
          <div class="trending-item">
            <span class="trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <div>
              <div class="trending-title">
                <a href="/<?= $trendCategorySlug ?>/<?= $t['slug'] ?? '' ?>"><?= Helper::sanitize($t['title'] ?? 'Untitled story') ?></a>
              </div>
              <div class="trending-meta">
                <span style="color:<?= $trendCategoryColor ?>"><?= Helper::sanitize($t['category_name'] ?? 'General') ?></span>
                &middot; <?= Helper::formatNumber((int)($t['views_count'] ?? 0)) ?> views
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="sidebar-widget home-sidebar-feed-widget">
          <div class="widget-title"><i class="fa fa-compass" style="color:#5A6475"></i> Explore Latest</div>
          <?php if (!empty($exploreLatest)): ?>
            <?php foreach ($exploreLatest as $i => $item): ?>
            <?php
              $exploreCategorySlug = $item['category_slug'] ?? 'news';
              $exploreCategoryColor = $item['category_color'] ?? '#5A6475';
            ?>
            <div class="trending-item">
              <span class="trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div>
                <div class="trending-title">
                  <a href="/<?= $exploreCategorySlug ?>/<?= $item['slug'] ?? '' ?>"><?= Helper::sanitize($item['title'] ?? 'Untitled post') ?></a>
                </div>
                <div class="trending-meta">
                  <span style="color:<?= $exploreCategoryColor ?>"><?= Helper::sanitize($item['category_name'] ?? 'Explore') ?></span>
                  &middot; <?= Helper::timeAgo($item['published_at'] ?? $item['created_at'] ?? null) ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <p style="font-size:13px;color:var(--muted);margin:0">No Explore posts yet.</p>
          <?php endif; ?>
        </div>

        <div class="sidebar-widget home-sidebar-feed-widget">
          <div class="widget-title"><i class="fa fa-users" style="color:#6F5DA8"></i> Community Latest</div>
          <?php if (!empty($communityLatest)): ?>
            <?php foreach ($communityLatest as $i => $item): ?>
            <?php
              $communityCategorySlug = $item['category_slug'] ?? 'community';
              $communityCategoryColor = $item['category_color'] ?? '#6F5DA8';
            ?>
            <div class="trending-item">
              <span class="trending-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div>
                <div class="trending-title">
                  <a href="/<?= $communityCategorySlug ?>/<?= $item['slug'] ?? '' ?>"><?= Helper::sanitize($item['title'] ?? 'Untitled post') ?></a>
                </div>
                <div class="trending-meta">
                  <span style="color:<?= $communityCategoryColor ?>"><?= Helper::sanitize($item['full_name'] ?? 'Community') ?></span>
                  &middot; <?= Helper::timeAgo($item['published_at'] ?? $item['created_at'] ?? null) ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
          <p style="font-size:13px;color:var(--muted);margin:0">No Community posts yet.</p>
          <?php endif; ?>
        </div>

        <div class="sidebar-widget" id="homeNewsletter" style="background:linear-gradient(135deg,rgba(255,45,45,0.15),rgba(255,107,26,0.1));border-color:rgba(255,45,45,0.2)">
          <div class="widget-title" style="color:var(--red)"><i class="fa fa-envelope"></i> Newsletter</div>
          <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Get breaking news delivered to your inbox daily.</p>
          <?php if ($newsletterStatus === 'success'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--success">Subscribed successfully.</div>
          <?php elseif ($newsletterStatus === 'exists'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--success">This email is already subscribed.</div>
          <?php elseif ($newsletterStatus === 'invalid'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--error">Please enter a valid email address.</div>
          <?php elseif ($newsletterStatus === 'csrf'): ?>
          <div class="newsletter-inline-msg newsletter-inline-msg--error">Session expired. Please try again.</div>
          <?php endif; ?>
          <form action="<?= Helper::siteUrl('api/newsletter') ?>" method="POST">
            <?= Csrf::field() ?>
            <input type="hidden" name="newsletter_context" value="desktop">
            <input type="email" name="email" class="form-control" placeholder="your@email.com" style="margin-bottom:10px" required>
            <button type="submit" class="btn-block" style="padding:10px">Subscribe</button>
          </form>
        </div>
      </aside>
    </div>

    <section class="home-topics-section">
      <div class="sidebar-widget home-topics-widget">
        <div class="widget-title"><i class="fa fa-hashtag"></i> Topics</div>
        <div class="home-topics-row">
          <?php foreach ($categories as $cat): ?>
          <a href="/category/<?= Helper::sanitize($cat['slug'] ?? '') ?>" class="tag-chip" style="--topic-color:<?= Helper::sanitize($cat['color'] ?? '#111') ?>;border-left:3px solid <?= Helper::sanitize($cat['color'] ?? '#111') ?>">
            <?= Helper::sanitize($cat['name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <?php if (!empty($categorySpotlights)): ?>
    <section class="home-category-rail-section">
      <div class="widget-title"><i class="fa fa-layer-group"></i> Category Top 5</div>
      <div class="home-category-rail-wrap">
        <button type="button" class="home-category-rail-btn home-category-rail-btn-prev" data-rail-prev="categoryRail" aria-label="Scroll categories left">
          <i class="fa fa-chevron-left"></i>
        </button>
        <div class="home-category-rail" id="categoryRail">
          <?php foreach ($categorySpotlights as $spotlight): ?>
          <?php
            $spotlightCategory = $spotlight['category'];
            $spotlightPosts = $spotlight['posts'];
            $spotlightColor = $spotlightCategory['color'] ?? '#FF6B1A';
            $spotlightIcon = $spotlightCategory['icon'] ?? 'fa-newspaper';
          ?>
          <article class="home-category-card" style="--category-card-accent:<?= Helper::sanitize($spotlightColor) ?>">
            <div class="home-category-card-head">
              <div>
                <span class="home-category-card-kicker"><i class="fa <?= Helper::sanitize($spotlightIcon) ?>"></i> Category</span>
                <h3>
                  <a href="/category/<?= Helper::sanitize($spotlightCategory['slug'] ?? '') ?>"><?= Helper::sanitize($spotlightCategory['name'] ?? 'Category') ?></a>
                </h3>
              </div>
              <a href="/category/<?= Helper::sanitize($spotlightCategory['slug'] ?? '') ?>" class="home-category-card-link">View All</a>
            </div>
            <div class="home-category-card-list">
              <?php if (!empty($spotlightPosts)): ?>
                <?php foreach ($spotlightPosts as $index => $spotlightPost): ?>
                <a href="/<?= Helper::sanitize($spotlightPost['category_slug'] ?? ($spotlightCategory['slug'] ?? 'news')) ?>/<?= Helper::sanitize($spotlightPost['slug'] ?? '') ?>" class="home-category-card-item">
                  <span class="home-category-card-rank"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                  <span class="home-category-card-title"><?= Helper::sanitize($spotlightPost['title'] ?? 'Untitled story') ?></span>
                </a>
                <?php endforeach; ?>
              <?php else: ?>
              <div class="home-category-card-item home-category-card-item-empty">
                <span class="home-category-card-rank">--</span>
                <span class="home-category-card-title">Stories will appear here soon.</span>
              </div>
              <?php endif; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        <button type="button" class="home-category-rail-btn home-category-rail-btn-next" data-rail-next="categoryRail" aria-label="Scroll categories right">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($suggestedUsers)): ?>
    <section class="home-follow-section">
      <div class="sidebar-widget home-follow-widget">
        <div class="widget-title"><i class="fa fa-users"></i> Who to Follow</div>
        <div class="home-follow-row">
        <?php foreach ($suggestedUsers as $su): ?>
        <div class="suggest-item">
          <a href="/@<?= Helper::sanitize($su['username'] ?? '') ?>">
            <img src="<?= Helper::avatarUrl($su['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($su['full_name'] ?? 'FatakNews User') ?>" class="suggest-avatar" width="40" height="40" loading="lazy" decoding="async">
          </a>
          <div class="suggest-info">
            <strong>
              <a href="/@<?= Helper::sanitize($su['username'] ?? '') ?>"><?= Helper::sanitize($su['full_name'] ?? 'FatakNews User') ?></a>
              <?php if (!empty($su['is_verified'])): ?><i class="fa fa-check-circle verified-icon"></i><?php endif; ?>
            </strong>
            <span>@<?= Helper::sanitize($su['username'] ?? '') ?> &middot; <?= Helper::formatNumber((int)($su['followers_count'] ?? 0)) ?> followers</span>
          </div>
          <?php if (Auth::check() && Auth::id() !== (int)($su['id'] ?? 0)): ?>
          <button class="btn-follow" data-follow="<?= (int)($su['id'] ?? 0) ?>">Follow</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>
  </main>
</div>
<?php endif; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
