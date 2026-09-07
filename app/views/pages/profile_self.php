<?php
Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'My Profile - FatakNews';
$postModel = new PostModel();
$posts = $postModel->getByUser($user['id'], max(1, (int)($_GET['page'] ?? 1)));
$bodyClass = 'profile-page';
$mHideTopBar = true;
$mUnread = (new NotificationModel())->countUnread((int)$user['id']);
include VIEW . 'layouts/header.php';
?>
<div class="m-only">
  <div class="m-profile-head">
    <?php if (Helper::avatarAssetUrl($user['avatar'] ?? null)): ?>
    <img src="<?= Helper::avatarUrl($user['avatar']) ?>" alt="<?= Helper::sanitize($user['full_name']) ?>">
    <?php else: ?>
    <span class="m-avatar-fallback"><i class="fa fa-user"></i></span>
    <?php endif; ?>
    <span class="m-profile-id">
      <b><?= Helper::sanitize($user['full_name'] ?: ('@' . $user['username'])) ?></b>
      <span><?= Helper::sanitize($user['email'] ?: ('@' . $user['username'])) ?></span>
    </span>
    <a href="/settings" class="m-iconbtn" aria-label="Settings"><i class="fa fa-gear"></i></a>
  </div>

  <div class="m-menu">
    <a href="/@<?= $user['username'] ?>" class="m-menu-item"><i class="fa fa-id-badge"></i><span class="m-menu-label">Public Profile<small>How readers see you</small></span><i class="fa fa-chevron-right"></i></a>
    <a href="/settings" class="m-menu-item"><i class="fa fa-sliders"></i><span class="m-menu-label">My Interests<small>Customise your news feed</small></span><i class="fa fa-chevron-right"></i></a>
    <a href="/bookmarks" class="m-menu-item"><i class="fa fa-bookmark"></i><span class="m-menu-label">Saved Articles<small>Your reading list</small></span><i class="fa fa-chevron-right"></i></a>
    <a href="/notifications" class="m-menu-item"><i class="fa fa-bell"></i><span class="m-menu-label">Notifications<small>Alerts &amp; updates</small></span><?php if ($mUnread > 0): ?><span class="m-dot"><?= $mUnread > 9 ? '9+' : $mUnread ?></span><?php endif; ?><i class="fa fa-chevron-right"></i></a>
    <button type="button" class="m-menu-item" data-theme-toggle><i class="fa fa-moon"></i><span class="m-menu-label">Dark Mode<small>Switch light / dark theme</small></span><span class="m-switch" style="pointer-events:none"><span class="m-slider"></span></span></button>
    <a href="/editorial-standards" class="m-menu-item"><i class="fa fa-circle-info"></i><span class="m-menu-label">About FatakNews<small>Version 1.0.0</small></span><i class="fa fa-chevron-right"></i></a>
    <a href="/contact" class="m-menu-item"><i class="fa fa-headset"></i><span class="m-menu-label">Help &amp; Support<small>Get help or contact us</small></span><i class="fa fa-chevron-right"></i></a>
  </div>
  <a href="/logout" class="m-btn-logout">Log Out</a>

  <div class="m-section-head"><h2><i class="fa fa-newspaper"></i> Your Posts</h2></div>
  <div class="m-list">
    <?php foreach (($posts['data'] ?? []) as $post):
      $pu = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
    ?>
    <a href="<?= $pu ?>" class="m-item">
      <img class="m-item-thumb" src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize($post['title'] ?? '') ?>" loading="lazy" decoding="async">
      <span class="m-item-body">
        <span class="m-kicker"><?= Helper::sanitize(ucfirst($post['status'])) ?></span>
        <h3><?= Helper::sanitize($post['title']) ?></h3>
        <span class="m-meta"><span><?= Helper::timeAgo($post['created_at']) ?></span></span>
      </span>
    </a>
    <?php endforeach; ?>
    <?php if (empty($posts['data'])): ?>
    <div class="empty-state"><i class="fa fa-user-pen"></i><h3>No posts yet</h3></div>
    <?php endif; ?>
  </div>
</div>

<div class="post-page d-only" style="grid-template-columns:1fr">
  <main class="post-main">
    <div class="profile-cover"></div>
    <div class="profile-info">
      <div class="profile-avatar-wrap">
        <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap">
          <img src="<?= Helper::avatarUrl($user['avatar']) ?>" alt="<?= Helper::sanitize($user['full_name']) ?>" class="profile-avatar" width="100" height="100" decoding="async">
          <a href="/settings" class="btn-ghost">Change Photo</a>
        </div>
        <a href="/settings" class="btn-ghost">Account settings</a>
      </div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:34px;line-height:1.1"><?= Helper::sanitize($user['full_name']) ?></h1>
      <p style="color:var(--muted);margin-top:4px">@<?= $user['username'] ?></p>
      <p style="color:var(--muted);margin-top:12px;max-width:760px"><?= Helper::sanitize($user['bio'] ?: 'No bio added yet.') ?></p>
      <div class="profile-stats" style="margin-top:22px">
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['posts_count']) ?></strong><span>Posts</span></div>
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['followers_count']) ?></strong><span>Followers</span></div>
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['following_count']) ?></strong><span>Following</span></div>
        <div class="profile-stat"><strong><?= Helper::formatNumber((int)$user['points']) ?></strong><span>Points</span></div>
      </div>
    </div>

    <div class="widget-title"><i class="fa fa-newspaper"></i> Recent Posts</div>
    <div class="news-feed">
      <?php foreach ($posts['data'] as $index => $post):
        $postUrl = '/' . ($post['category_slug'] ?: 'news') . '/' . $post['slug'];
      ?>
      <article class="news-card">
        <a href="<?= $postUrl ?>"><img src="<?= Helper::thumbnailUrl($post['thumbnail']) ?>" alt="<?= Helper::sanitize(Helper::imageAlt($post['image_alt'] ?? '', $post['title'] ?? '')) ?>" class="news-card-img" width="160" height="110" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"<?= $index === 0 ? ' fetchpriority="high"' : '' ?> decoding="async"></a>
        <div style="flex:1">
          <div class="news-card-body">
            <h3 class="news-card-title"><a href="<?= $postUrl ?>"><?= Helper::sanitize($post['title']) ?></a></h3>
            <div class="news-card-meta"><span><?= ucfirst($post['status']) ?></span><span><?= Helper::timeAgo($post['created_at']) ?></span></div>
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
      <?php if (empty($posts['data'])): ?>
      <div class="empty-state"><i class="fa fa-user-pen"></i><h3>No posts from this profile yet</h3></div>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
