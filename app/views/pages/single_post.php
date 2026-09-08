<?php
$postModel = new PostModel();
$post = $postModel->getBySlug($dynamic['slug']);
if (!$post || (!empty($dynamic['cat']) && !empty($post['category_slug']) && $dynamic['cat'] !== $post['category_slug'])) {
    http_response_code(404);
    include VIEW . 'pages/404.php';
    return;
}
$postModel->incrementViews((int)$post['id']);
$post['views_count']++;
$isExplorePost = (($post['location'] ?? '') === 'explore');
$sourceUrlRaw = trim((string)($post['source_url'] ?? ''));
$sourceUrl = filter_var($sourceUrlRaw, FILTER_VALIDATE_URL) ? $sourceUrlRaw : '';
$sourceLabel = Helper::socialPlatformLabel($sourceUrl);
$seoTitle = trim((string)($post['seo_title'] ?? ''));
$seoDescription = trim((string)($post['seo_description'] ?? ''));
$coverImage = Helper::thumbnailAssetUrl($post['thumbnail'] ?? null) ?? Helper::firstImageFromHtml($post['content'] ?? '');
$coverAlt = Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '');
$preparedContent = Helper::prepareArticleHtml($post['content'] ?? '', $post['title'] ?? '');
$defaultTitleSuffix = $isExplorePost
    ? ' | Explore | FatakNews'
    : (!empty($post['category_name'])
        ? ' | ' . $post['category_name'] . ' News | FatakNews'
        : ' | FatakNews');
$pageTitle = $seoTitle !== ''
    ? $seoTitle
    : ($post['title'] . $defaultTitleSuffix);
$pageDesc = $seoDescription !== ''
    ? Helper::metaDescription($seoDescription)
    : Helper::metaDescription(
        $isExplorePost
            ? trim(($sourceLabel !== 'Social' ? $sourceLabel . ' post. ' : '') . ($post['excerpt'] ?: Helper::excerpt($post['content'], 180)) . ' Explore the full update on FatakNews.')
            : trim(($post['excerpt'] ?: Helper::excerpt($post['content'], 180)) . ' Read the full story on FatakNews.')
    );
$canonicalUrl = Helper::siteUrl(($post['category_slug'] ?: 'news') . '/' . $post['slug']);
$pageImage = $coverImage ?: Helper::defaultShareImage();
$ogType = 'article';
$db = Database::getInstance();
$commentModel = new CommentModel();
$comments = $commentModel->getByPost((int)$post['id']);
$tags = $db->fetchAll(
    "SELECT t.* FROM post_tags pt JOIN tags t ON pt.tag_id=t.id WHERE pt.post_id=?",
    [$post['id']]
);
$related = $db->fetchAll(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
     FROM posts p
     LEFT JOIN categories c ON p.category_id=c.id
     WHERE p.status='published' AND p.id != ? AND (p.category_id <=> ?)
     ORDER BY COALESCE(p.published_at, p.created_at) DESC
     LIMIT 5",
    [$post['id'], $post['category_id']]
);
$excludedIds = [(int)$post['id']];
foreach ($related as $relatedItem) {
    $excludedIds[] = (int)($relatedItem['id'] ?? 0);
}
$excludedIds = array_values(array_unique(array_filter($excludedIds)));
$readNext = [];
if (!empty($tags)) {
    $tagIds = array_values(array_filter(array_map('intval', array_column($tags, 'id'))));
    if (!empty($tagIds)) {
        $tagPlaceholders = implode(',', array_fill(0, count($tagIds), '?'));
        $excludePlaceholders = implode(',', array_fill(0, count($excludedIds), '?'));
        $readNext = $db->fetchAll(
            "SELECT DISTINCT p.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
             FROM post_tags pt
             JOIN posts p ON pt.post_id=p.id
             LEFT JOIN categories c ON p.category_id=c.id
             WHERE p.status='published'
               AND COALESCE(p.location,'') <> 'explore'
               AND pt.tag_id IN ($tagPlaceholders)
               AND p.id NOT IN ($excludePlaceholders)
             ORDER BY p.views_count DESC, COALESCE(p.published_at, p.created_at) DESC
             LIMIT 4",
            array_merge($tagIds, $excludedIds)
        );
    }
}
if (count($readNext) < 4) {
    $readNextIds = $excludedIds;
    foreach ($readNext as $nextItem) {
        $readNextIds[] = (int)($nextItem['id'] ?? 0);
    }
    $readNextIds = array_values(array_unique(array_filter($readNextIds)));
    $excludePlaceholders = implode(',', array_fill(0, count($readNextIds), '?'));
    $fallbackNext = $db->fetchAll(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color
         FROM posts p
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.status='published'
           AND COALESCE(p.location,'') <> 'explore'
           AND p.id NOT IN ($excludePlaceholders)
         ORDER BY p.views_count DESC, COALESCE(p.published_at, p.created_at) DESC
         LIMIT " . max(1, 4 - count($readNext)),
        $readNextIds
    );
    $readNext = array_slice(array_merge($readNext, $fallbackNext), 0, 4);
}
$topicHubLinks = [];
if (!empty($post['category_name']) && !empty($post['category_slug'])) {
    $topicHubLinks[] = [
        'label' => $post['category_name'] . ' News',
        'url' => '/category/' . $post['category_slug'],
        'accent' => $post['category_color'] ?: '#FF6B1A',
    ];
}
foreach (array_slice($tags, 0, 5) as $tag) {
    $topicHubLinks[] = [
        'label' => '#' . ($tag['name'] ?? ''),
        'url' => '/tag/' . ($tag['slug'] ?? ''),
        'accent' => $post['category_color'] ?: '#4B7BFF',
    ];
}
$keywordParts = array_filter([
    $post['category_name'] ?? '',
    $post['source_name'] ?? '',
    $sourceLabel !== 'Social' ? $sourceLabel : '',
    trim((string)($post['seo_keywords'] ?? '')),
    'FatakNews',
    $isExplorePost ? 'Explore' : 'News',
]);
$keywords = implode(', ', array_unique(array_map('trim', $keywordParts)));
$pageAuthor = $post['full_name'] ?: 'FatakNews Desk';
$articleWordCount = str_word_count(strip_tags($preparedContent));
$categoryBreadcrumbUrl = !empty($post['category_slug'])
    ? Helper::siteUrl('category/' . $post['category_slug'])
    : Helper::siteUrl('feed');
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => $post['category_name'] ?: 'News', 'url' => $categoryBreadcrumbUrl],
    ['name' => $post['title'], 'url' => $canonicalUrl],
];
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $post['title'],
        'description' => $pageDesc,
        'url' => $canonicalUrl,
        'mainEntityOfPage' => $canonicalUrl,
        'datePublished' => date('c', strtotime($post['published_at'] ?: $post['created_at'])),
        'dateModified' => date('c', strtotime($post['updated_at'] ?: $post['published_at'] ?: $post['created_at'])),
        'image' => [$pageImage],
        'author' => Helper::authorSchema($post),
        'publisher' => Helper::publisherSchema(),
        'articleSection' => $post['category_name'] ?: 'News',
        'keywords' => $keywords,
        'inLanguage' => 'en-IN',
        'isAccessibleForFree' => true,
        'wordCount' => max(1, $articleWordCount),
        'commentCount' => (int)($post['comments_count'] ?? count($comments)),
        'speakable' => Helper::speakableSchema(),
        'interactionStatistic' => [
            [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/ReadAction',
                'userInteractionCount' => max(0, (int)($post['views_count'] ?? 0)),
            ],
            [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/LikeAction',
                'userInteractionCount' => max(0, (int)($post['likes_count'] ?? 0)),
            ],
            [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/CommentAction',
                'userInteractionCount' => max(0, (int)($post['comments_count'] ?? count($comments))),
            ],
        ],
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
];
if (!empty($readNext)) {
    $structuredData[] = Helper::collectionItemListSchema($readNext, $canonicalUrl . '#read-next');
}
if ($sourceUrl !== '') {
    $structuredData[0]['sameAs'] = [$sourceUrl];
}
if (!empty($post['video_url'])) {
    $structuredData[0]['video'] = [
        '@type' => 'VideoObject',
        'name' => $post['title'],
        'description' => $pageDesc,
        'embedUrl' => Helper::youtubeEmbedUrl((string)$post['video_url']) ?: (string)$post['video_url'],
    ];
}
$publishedAtIso = date('c', strtotime($post['published_at'] ?: $post['created_at']));
$modifiedAtIso = date('c', strtotime($post['updated_at'] ?: $post['published_at'] ?: $post['created_at']));
$headFragments = [];
if ($coverImage) {
    $headFragments[] = '<link rel="preload" as="image" href="' . Helper::sanitize($coverImage) . '">';
    $pageImageWidth = 1200;
    $pageImageHeight = 675;
}
$headFragments[] = '<meta name="author" content="' . Helper::sanitize($pageAuthor) . '">';
$headFragments[] = '<meta property="article:publisher" content="' . Helper::sanitize(Helper::siteUrl()) . '">';
$headFragments[] = '<meta property="article:published_time" content="' . Helper::sanitize($publishedAtIso) . '">';
$headFragments[] = '<meta property="article:modified_time" content="' . Helper::sanitize($modifiedAtIso) . '">';
$headFragments[] = '<meta property="article:section" content="' . Helper::sanitize($post['category_name'] ?: 'News') . '">';
$headFragments[] = '<meta property="article:author" content="' . Helper::sanitize($pageAuthor) . '">';
if ($keywords !== '') {
    $headFragments[] = '<meta name="news_keywords" content="' . Helper::sanitize($keywords) . '">';
}
foreach (array_slice($tags, 0, 8) as $tagMeta) {
    $headFragments[] = '<meta property="article:tag" content="' . Helper::sanitize($tagMeta['name'] ?? '') . '">';
}
$extraHead = implode("\n", $headFragments);
$bodyClass = 'single-post-page';
$mBarTitle = $post['category_name'] ?: 'News';
$mBarActions = '<button type="button" class="m-iconbtn" aria-label="Share"'
    . ' data-share-title="' . Helper::sanitize($post['title'] ?? 'FatakNews story') . '"'
    . ' data-share-url="' . Helper::sanitize($canonicalUrl) . '"><i data-lucide="share-2"></i></button>'
    . '<button type="button" class="m-iconbtn" aria-label="Save" data-bookmark="' . (int)$post['id'] . '"><i data-lucide="bookmark"></i></button>'
    . '<button type="button" class="m-iconbtn" aria-label="More"><i data-lucide="more-vertical"></i></button>';
include VIEW . 'layouts/header.php';
?>
<div class="post-page">
  <main class="post-main">
    <header class="post-header">
      <?= Helper::breadcrumbNav($breadcrumbItems) ?>
      <?php if (!empty($post['category_name'])): ?>
      <a href="/category/<?= $post['category_slug'] ?>" class="post-cat-badge" style="background:<?= $post['category_color'] ?>22;color:<?= $post['category_color'] ?>"><?= Helper::sanitize($post['category_name']) ?></a>
      <?php endif; ?>
      <h1 class="post-title"><?= Helper::sanitize($post['title']) ?></h1>
      <p class="post-excerpt"><?= Helper::sanitize($post['excerpt'] ?: Helper::excerpt($post['content'], 220)) ?></p>
      <div class="m-byline m-only">
        <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="" width="28" height="28" decoding="async">
        <span><a href="/@<?= $post['username'] ?>"><?= Helper::sanitize($post['full_name'] ?: 'FatakNews Desk') ?></a></span>
        <i>·</i> <?= Helper::timeAgo($post['published_at'] ?? $post['created_at']) ?>
        <i>·</i> <?= Helper::formatNumber((int)$post['views_count']) ?> views
      </div>
      <div class="post-meta-bar">
        <div class="post-author">
          <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name'] ?: 'Author') ?>" class="post-author-img" width="44" height="44" decoding="async">
          <div class="post-author-info">
            <strong><a href="/@<?= $post['username'] ?>"><?= Helper::sanitize($post['full_name']) ?></a></strong>
            <span>@<?= $post['username'] ?></span>
          </div>
        </div>
        <div class="post-meta-extra">
          <span><i class="fa fa-clock"></i> Published <time datetime="<?= Helper::sanitize($publishedAtIso) ?>"><?= date('d M Y, H:i', strtotime($post['published_at'] ?: $post['created_at'])) ?> IST</time></span>
          <?php if (($post['updated_at'] ?? '') && strtotime($post['updated_at']) > strtotime($post['published_at'] ?: $post['created_at']) + 60): ?>
          <span><i class="fa fa-pen"></i> Updated <time datetime="<?= Helper::sanitize($modifiedAtIso) ?>"><?= date('d M Y, H:i', strtotime($post['updated_at'])) ?> IST</time></span>
          <?php endif; ?>
          <span><i class="fa fa-eye"></i> <?= Helper::formatNumber($post['views_count']) ?></span>
          <span><i class="fa fa-book-open"></i> <?= $post['reading_time'] ?> min</span>
        </div>
      </div>
    </header>

    <?php if ($coverImage): ?>
    <img src="<?= Helper::sanitize($coverImage) ?>" alt="<?= Helper::sanitize($coverAlt) ?>" class="post-cover" width="1200" height="675" loading="eager" fetchpriority="high" decoding="async">
    <?php else: ?>
    <div class="post-cover-fallback">
      <span class="post-cover-fallback-badge">No Cover Image</span>
      <strong>No cover image was uploaded for this post.</strong>
      <p>Edit the post from the editor panel and add a cover image to display it here.</p>
    </div>
    <?php endif; ?>
    <article class="post-content"><?= $preparedContent ?></article>

    <?php if (!empty($tags)): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:28px">
      <?php foreach ($tags as $tag): ?>
      <a href="/tag/<?= $tag['slug'] ?>" class="tag-chip">#<?= Helper::sanitize($tag['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($topicHubLinks)): ?>
    <section style="margin-top:28px;padding:18px 18px 12px;border:1px solid #e8e1f3;border-radius:22px;background:#fff8f8">
      <div class="widget-title" style="margin-bottom:12px"><i class="fa fa-diagram-project"></i> Explore This Topic</div>
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach ($topicHubLinks as $topicLink): ?>
        <a href="<?= Helper::sanitize($topicLink['url']) ?>" class="tag-chip" style="display:inline-flex;border-left:3px solid <?= Helper::sanitize($topicLink['accent']) ?>"><?= Helper::sanitize($topicLink['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php
      $authorBio = trim((string)($post['bio'] ?? ''));
      $authorRole = trim((string)($post['role_name'] ?? ''));
      $authorLoc = trim((string)($post['author_location'] ?? ''));
      $authorSite = trim((string)($post['website'] ?? ''));
      $authorSiteOk = $authorSite !== '' && filter_var($authorSite, FILTER_VALIDATE_URL);
    ?>
    <section class="author-card" aria-label="About the author">
      <img src="<?= Helper::avatarUrl($post['avatar']) ?>" alt="<?= Helper::sanitize($post['full_name'] ?: 'Author') ?>" class="author-card-img" width="64" height="64" loading="lazy" decoding="async">
      <div class="author-card-body">
        <span class="author-card-kicker">Written by</span>
        <strong class="author-card-name">
          <a href="/@<?= $post['username'] ?>"><?= Helper::sanitize($post['full_name'] ?: ('@' . $post['username'])) ?></a>
          <?php if (!empty($post['is_verified'])): ?><i data-lucide="badge-check" class="verified-icon" title="Verified"></i><?php endif; ?>
        </strong>
        <span class="author-card-meta">
          <?= Helper::sanitize($authorRole ?: 'Contributor') ?><?php if ($authorLoc !== ''): ?> &middot; <?= Helper::sanitize($authorLoc) ?><?php endif; ?>
        </span>
        <?php if ($authorBio !== ''): ?>
        <p class="author-card-bio"><?= Helper::sanitize($authorBio) ?></p>
        <?php endif; ?>
        <span class="author-card-links">
          <a href="/@<?= $post['username'] ?>">More by this author</a>
          <?php if ($authorSiteOk): ?>
          <a href="<?= Helper::sanitize($authorSite) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Helper::sanitize(parse_url($authorSite, PHP_URL_HOST) ?: 'Website') ?></a>
          <?php endif; ?>
        </span>
        <span class="author-card-note">This report follows the FatakNews <a href="/corrections">editorial &amp; corrections policy</a>.</span>
      </div>
    </section>

    <div class="post-actions-bar">
      <button type="button" class="post-action-big" data-react="<?= $post['id'] ?>" data-type="like" aria-label="Like article"><i data-lucide="heart"></i><span class="react-count"><?= Helper::formatNumber((int)$post['likes_count']) ?></span><em>Like</em></button>
      <a class="post-action-big" href="#comments" aria-label="Go to comments"><i data-lucide="message-circle"></i><span><?= Helper::formatNumber((int)$post['comments_count']) ?></span><em>Comment</em></a>
      <button
        type="button"
        class="post-action-big"
        data-share-title="<?= Helper::sanitize($post['title'] ?? 'FatakNews story') ?>"
        data-share-url="<?= Helper::sanitize($canonicalUrl) ?>"
        aria-label="Share article"
      ><i data-lucide="share-2"></i><em>Share</em></button>
      <button type="button" class="post-action-big" data-bookmark="<?= $post['id'] ?>" aria-label="Save article"><i data-lucide="bookmark"></i><em>Save</em></button>
    </div>

    <section class="comments-section" id="comments">
      <h3>Comments</h3>
      <?php if ($post['allow_comments']): ?>
      <form class="comment-form" id="commentForm" data-post="<?= $post['id'] ?>">
        <textarea placeholder="Share your thoughts..."></textarea>
        <div class="comment-form-footer"><button class="btn-write" type="submit">Post comment</button></div>
      </form>
      <?php endif; ?>
      <div id="commentsList">
        <?php foreach ($comments as $comment): ?>
        <div class="comment-item">
          <img src="<?= Helper::avatarUrl($comment['avatar']) ?>" alt="<?= Helper::sanitize($comment['full_name']) ?>" class="comment-avatar" width="38" height="38" loading="lazy" decoding="async">
          <div class="comment-body">
            <div class="comment-header">
              <strong><?= Helper::sanitize($comment['full_name']) ?></strong>
              <time><?= Helper::timeAgo($comment['created_at']) ?></time>
            </div>
            <p class="comment-text"><?= nl2br(Helper::sanitize($comment['content'])) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?>
        <div class="empty-state"><i class="fa fa-comments"></i><h3>No comments yet</h3><p>Be the first to start the discussion.</p></div>
        <?php endif; ?>
      </div>
    </section>

  </main>

  <aside class="sidebar-col">
    <div class="sidebar-widget" style="background:#fffdfd;border:1px solid #e8e1f3;border-radius:28px;box-shadow:0 22px 54px rgba(53,45,88,.12),0 3px 14px rgba(53,45,88,.06);padding:24px 22px;overflow:hidden">
      <div class="widget-title"><i class="fa fa-link"></i> More Stories</div>
      <?php foreach ($related as $item): ?>
      <div class="trending-item">
        <span class="trending-num"><i class="fa fa-angle-right"></i></span>
        <div class="trending-title"><a href="/<?= $item['category_slug'] ?: 'news' ?>/<?= $item['slug'] ?>"><?= Helper::sanitize($item['title']) ?></a></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($related)): ?>
      <p style="font-size:14px;color:var(--muted)">No related stories found yet.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>

<section class="post-related-shell">
  <div class="post-related-panel">
    <div class="widget-title"><i class="fa fa-layer-group"></i> Related In <?= Helper::sanitize($post['category_name'] ?: 'This Category') ?></div>
    <?php if (!empty($related)): ?>
    <div class="related-posts-grid">
      <?php foreach ($related as $item): ?>
      <?php
        $relatedUrl = '/' . ($item['category_slug'] ?: 'news') . '/' . $item['slug'];
        $relatedExcerpt = Helper::excerpt($item['excerpt'] ?: $item['content'], 90);
        $relatedCategoryColor = $item['category_color'] ?: '#FF6B1A';
      ?>
      <article class="related-post-card" style="--related-card-color:<?= Helper::sanitize($relatedCategoryColor) ?>">
        <a href="<?= $relatedUrl ?>" class="related-post-media">
          <?php if (!empty($item['thumbnail'])): ?>
          <img src="<?= Helper::thumbnailUrl($item['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($item['image_alt'] ?? '', $item['title'] ?? '')) ?>" width="480" height="270" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="related-post-mediafallback"><i class="fa fa-newspaper"></i></span>
          <?php endif; ?>
        </a>
        <div class="related-post-body">
          <?php if (!empty($item['category_name'])): ?>
          <a href="/category/<?= Helper::sanitize($item['category_slug']) ?>" class="related-post-tag" style="color:<?= Helper::sanitize($relatedCategoryColor) ?>"><?= Helper::sanitize($item['category_name']) ?></a>
          <?php endif; ?>
          <h4><a href="<?= $relatedUrl ?>"><?= Helper::sanitize($item['title']) ?></a></h4>
          <p><?= Helper::sanitize($relatedExcerpt) ?></p>
          <div class="related-post-meta">
            <span><i class="fa fa-clock"></i> <?= Helper::timeAgo($item['published_at'] ?? $item['created_at']) ?></span>
            <span><i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($item['views_count'] ?? 0)) ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="related-posts-empty">No more stories in this category yet.</p>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($readNext)): ?>
<section class="post-related-shell" id="read-next">
  <div class="post-related-panel">
    <div class="widget-title"><i class="fa fa-arrow-right"></i> Read Next</div>
    <div class="related-posts-grid">
      <?php foreach ($readNext as $item): ?>
      <?php
        $nextUrl = '/' . ($item['category_slug'] ?: 'news') . '/' . $item['slug'];
        $nextExcerpt = Helper::excerpt($item['excerpt'] ?: $item['content'], 90);
        $nextCategoryColor = $item['category_color'] ?: '#2979FF';
      ?>
      <article class="related-post-card" style="--related-card-color:<?= Helper::sanitize($nextCategoryColor) ?>">
        <a href="<?= $nextUrl ?>" class="related-post-media">
          <?php if (!empty($item['thumbnail'])): ?>
          <img src="<?= Helper::thumbnailUrl($item['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($item['image_alt'] ?? '', $item['title'] ?? '')) ?>" width="480" height="270" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="related-post-mediafallback"><i class="fa fa-newspaper"></i></span>
          <?php endif; ?>
        </a>
        <div class="related-post-body">
          <?php if (!empty($item['category_name'])): ?>
          <a href="/category/<?= Helper::sanitize($item['category_slug']) ?>" class="related-post-tag" style="color:<?= Helper::sanitize($nextCategoryColor) ?>"><?= Helper::sanitize($item['category_name']) ?></a>
          <?php endif; ?>
          <h4><a href="<?= $nextUrl ?>"><?= Helper::sanitize($item['title']) ?></a></h4>
          <p><?= Helper::sanitize($nextExcerpt) ?></p>
          <div class="related-post-meta">
            <span><i class="fa fa-clock"></i> <?= Helper::timeAgo($item['published_at'] ?? $item['created_at']) ?></span>
            <span><i class="fa fa-eye"></i> <?= Helper::formatNumber((int)($item['views_count'] ?? 0)) ?></span>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
