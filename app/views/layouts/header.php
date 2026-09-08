<!DOCTYPE html>
<html lang="en-IN">
<head>
<script>(function(){try{var t=localStorage.getItem('fn-theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
<?php
$metaTitle = trim((string)($pageTitle ?? 'FatakNews: Breaking News, Latest Headlines & Live Updates'));
$metaDescription = Helper::metaDescription($pageDesc ?? null);
$canonicalUrl = $canonicalUrl ?? Helper::siteUrl(ltrim(Helper::requestPath(), '/'));
$metaRobots = $metaRobots ?? Helper::robotsDirective(true);
$pageAuthor = trim((string)($pageAuthor ?? 'FatakNews Desk'));
$pageImage = $pageImage ?? Helper::defaultShareImage();
$ogType = $ogType ?? 'website';
$structuredData = $structuredData ?? null;
$prevUrl = $prevUrl ?? null;
$nextUrl = $nextUrl ?? null;
$appCssPath = BASE_PATH . '/public/assets/css/app.css';
$appMinCssPath = BASE_PATH . '/public/assets/css/app.min.css';
$appCssFile = 'app.css';
if (!is_file($appCssPath) && is_file($appMinCssPath)) {
    $appCssFile = 'app.min.css';
}
$appCssVersion = @filemtime(BASE_PATH . '/public/assets/css/' . $appCssFile) ?: time();
$alternateHreflangs = $alternateHreflangs ?? [
    'en-IN' => $canonicalUrl,
    'x-default' => $canonicalUrl,
];
$navCategoryTree = $navCategoryTree ?? Helper::cacheRemember('layout_nav_category_tree_v1', 300, static function (): array {
    return (new CategoryModel())->getTree();
});
$tickerBreaking = $tickerBreaking ?? Helper::cacheRemember('layout_breaking_ticker_v1', 60, static function (): array {
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT p.title, p.slug, c.slug AS category_slug
         FROM posts p
         LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.is_breaking=1 AND p.status='published'
         ORDER BY p.published_at DESC
         LIMIT 10"
    );
});
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= Helper::sanitize($metaTitle) ?></title>
<meta name="description" content="<?= Helper::sanitize($metaDescription) ?>">
<meta name="robots" content="<?= Helper::sanitize($metaRobots) ?>">
<meta name="googlebot" content="<?= Helper::sanitize($metaRobots) ?>">
<meta name="author" content="<?= Helper::sanitize($pageAuthor) ?>">
<meta name="referrer" content="strict-origin-when-cross-origin">
<link rel="canonical" href="<?= Helper::sanitize($canonicalUrl) ?>">
<link rel="alternate" type="application/rss+xml" title="FatakNews.in RSS" href="<?= Helper::sanitize(Helper::siteUrl('rss.xml')) ?>">
<?= Helper::analyticsHeadHtml() ?>
<?php foreach ($alternateHreflangs as $lang => $url): ?>
<link rel="alternate" hreflang="<?= Helper::sanitize($lang) ?>" href="<?= Helper::sanitize($url) ?>">
<?php endforeach; ?>
<?php if ($prevUrl): ?>
<link rel="prev" href="<?= Helper::sanitize($prevUrl) ?>">
<?php endif; ?>
<?php if ($nextUrl): ?>
<link rel="next" href="<?= Helper::sanitize($nextUrl) ?>">
<?php endif; ?>
<meta property="og:site_name" content="FatakNews.in">
<meta property="og:locale" content="en_IN">
<meta property="og:type" content="<?= Helper::sanitize($ogType) ?>">
<meta property="og:title" content="<?= Helper::sanitize($metaTitle) ?>">
<meta property="og:description" content="<?= Helper::sanitize($metaDescription) ?>">
<meta property="og:url" content="<?= Helper::sanitize($canonicalUrl) ?>">
<meta property="og:image" content="<?= Helper::sanitize($pageImage) ?>">
<meta property="og:image:width" content="<?= (int)($pageImageWidth ?? 1200) ?>">
<meta property="og:image:height" content="<?= (int)($pageImageHeight ?? 630) ?>">
<meta property="og:image:alt" content="<?= Helper::sanitize($metaTitle) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= Helper::sanitize($metaTitle) ?>">
<meta name="twitter:description" content="<?= Helper::sanitize($metaDescription) ?>">
<meta name="twitter:image" content="<?= Helper::sanitize($pageImage) ?>">
<meta name="twitter:image:alt" content="<?= Helper::sanitize($metaTitle) ?>">
<meta name="application-name" content="FatakNews">
<meta name="apple-mobile-web-app-title" content="FatakNews">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
<meta name="msapplication-TileColor" content="#E41E26">
<link rel="icon" href="<?= Helper::sanitize(Helper::siteUrl('favicon.ico')) ?>" sizes="any">
<link rel="shortcut icon" href="<?= Helper::sanitize(Helper::siteUrl('favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="48x48" href="<?= Helper::sanitize(Helper::siteUrl('favicon-48x48.png')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= Helper::sanitize(Helper::siteUrl('favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= Helper::sanitize(Helper::siteUrl('favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= Helper::sanitize(Helper::siteUrl('apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= Helper::sanitize(Helper::siteUrl('site.webmanifest')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="dns-prefetch" href="//fonts.googleapis.com">
<link rel="dns-prefetch" href="//fonts.gstatic.com">
<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" crossorigin="anonymous" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous"></noscript>
<?php $lucideVer = @filemtime(BASE_PATH . '/public/assets/js/lucide.min.js') ?: '1'; ?>
<script defer src="/public/assets/js/lucide.min.js?v=<?= $lucideVer ?>"></script>
<link rel="stylesheet" href="/public/assets/css/<?= $appCssFile ?>?v=<?= $appCssVersion ?>">
<?php $appMobileCssVersion = @filemtime(BASE_PATH . '/public/assets/css/app.mobile.css') ?: time(); ?>
<link rel="stylesheet" href="/public/assets/css/app.mobile.css?v=<?= $appMobileCssVersion ?>">
<?= $extraHead ?? '' ?>
<?php if ($structuredData): ?>
<script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">
<?= Helper::analyticsBodyOpenHtml() ?>
<?php $uri = Helper::requestPath(); ?>

<?php
// ===== MOBILE SPLASH (first load per session) =====
$mLogo = '/public/assets/images/fataknew_logo.png';
?>
<div class="m-splash" id="mSplash" aria-hidden="true">
  <img src="<?= $mLogo ?>" alt="">
  <b>Fatak<span>News</span></b>
  <small>News That Matters</small>
  <span class="m-splash-bar"><i></i></span>
</div>
<script>(function(){try{if(sessionStorage.getItem('fn-splash-seen')){var s=document.getElementById('mSplash');if(s)s.classList.add('is-hidden');}}catch(e){}})();</script>

<?php
// ===== MOBILE TOP BAR =====
$mBarPath = rtrim($uri, '/') ?: '/';
$mHomeBarPaths = ['/', '/feed', '/trending', '/explore', '/community'];
$mHideTopBar = $mHideTopBar ?? false;
$mBarTitle = $mBarTitle ?? trim(preg_replace('/\s*[|\-–].*$/', '', (string)($pageTitle ?? 'FatakNews')));
$mBarBack = $mBarBack ?? 'javascript:history.length>1?history.back():location.assign("/")';
if (!$mHideTopBar):
?>
<?php if (in_array($mBarPath, $mHomeBarPaths, true)): ?>
<header class="m-topbar m-only">
  <a href="/" class="m-topbar-logo">
    <img src="<?= $mLogo ?>" alt="FatakNews">
    <span><b>Fatak<span>News</span></b><small>News That Matters</small></span>
  </a>
  <a href="/search" class="m-iconbtn" aria-label="Search"><i data-lucide="search"></i></a>
  <a href="<?= Auth::check() ? '/notifications' : '/login' ?>" class="m-iconbtn" aria-label="Notifications">
    <i data-lucide="bell"></i>
    <?php if (Auth::check()): $mNotif = (new NotificationModel())->countUnread((int)Auth::id()); if ($mNotif > 0): ?>
    <span class="m-dot"><?= $mNotif > 9 ? '9+' : $mNotif ?></span>
    <?php endif; endif; ?>
  </a>
</header>
<?php else: ?>
<header class="m-topbar m-only">
  <a href="<?= Helper::sanitize($mBarBack) ?>" class="m-iconbtn" aria-label="Back"><i data-lucide="arrow-left"></i></a>
  <span class="m-topbar-title"><?= Helper::sanitize($mBarTitle) ?></span>
  <?= $mBarActions ?? '<a href="/search" class="m-iconbtn" aria-label="Search"><i data-lucide="search"></i></a>' ?>
</header>
<?php endif; ?>
<?php endif; ?>

<!-- ===== BREAKING NEWS TICKER ===== -->
<div class="ticker-wrap">
  <span class="ticker-label"><i class="fa fa-bolt"></i> BREAKING</span>
  <div class="ticker-inner">
    <div class="ticker-track" id="tickerTrack">
      <?php foreach ($tickerBreaking as $b): ?>
        <span><a href="/<?= $b['category_slug'] ?>/<?= $b['slug'] ?>"><?= Helper::sanitize($b['title']) ?></a></span>
      <?php endforeach; ?>
      <?php if (empty($tickerBreaking)): ?>
        <span>Welcome to FatakNews.in - India's fastest news platform!</span>
        <span>Stay tuned for live breaking news coverage</span>
        <span>Download our app for instant alerts</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== MAIN NAVBAR ===== -->
<nav class="navbar" id="navbar">
  <div class="nav-container">

    <!-- Logo -->
    <a href="/" class="nav-logo">
      <span class="logo-icon">
        <img src="/public/assets/images/fataknew_logo.webp" alt="FatakNews logo" width="60" height="60" decoding="async">
      </span>
      <span class="nav-brandcopy">
        <span class="logo-text">Fatak<strong>News</strong></span>
        <span class="nav-brand-slogan">News That Matter</span>
      </span>
    </a>

    <!-- Search Bar -->
    <div class="nav-search">
      <form action="/search" method="GET">
        <i class="fa fa-search"></i>
        <input type="text" name="q" placeholder="Search news, topics, people..." autocomplete="off" id="navSearch">
        <div class="search-dropdown" id="searchDropdown"></div>
      </form>
    </div>

    <!-- Nav Links (Desktop) -->
    <div class="nav-links">
      <a href="/" class="nav-link <?= $uri === '/' ? 'active' : '' ?>">
        <i class="fa fa-home"></i><span>Home</span>
      </a>
      <a href="/trending" class="nav-link <?= $uri === '/trending' ? 'active' : '' ?>">
        <i class="fa fa-fire"></i><span>Trending</span>
      </a>
      <a href="/explore" class="nav-link <?= $uri === '/explore' ? 'active' : '' ?>">
        <i class="fa fa-compass"></i><span>Explore</span>
      </a>
      <a href="/community" class="nav-link <?= $uri === '/community' ? 'active' : '' ?>">
        <i class="fa fa-users"></i><span>Community</span>
      </a>

      <!-- Categories Mega Dropdown -->
      <div class="nav-link nav-dropdown-trigger">
        <i class="fa fa-th-large"></i><span>Topics</span>
        <i class="fa fa-chevron-down fa-xs"></i>
        <div class="mega-dropdown">
          <?php foreach ($navCategoryTree as $cat): ?>
          <?php $children = $cat['children'] ?? []; ?>
          <div class="mega-col">
            <a href="/category/<?= $cat['slug'] ?>" class="mega-head" style="color:<?= $cat['color'] ?>">
              <i class="fa <?= $cat['icon'] ?>"></i> <?= $cat['name'] ?>
            </a>
            <?php foreach ($children as $child): ?>
            <a href="/category/<?= $child['slug'] ?>" class="mega-item"><?= $child['name'] ?></a>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Right Actions -->
    <div class="nav-actions">
      <?php if (Auth::check()): ?>
        <?php $authUser = Auth::user(); ?>

        <!-- Write Button -->
        <a href="<?= Auth::isEmployee() ? '/employee/create' : '/community/create' ?>" class="btn-write">
          <i class="fa fa-pen"></i> Write
        </a>

        <a href="/search" class="nav-mobile-search" aria-label="Search">
          <i class="fa fa-search"></i>
        </a>

        <!-- Notifications -->
        <div class="nav-notif" id="notifToggle">
          <i class="fa fa-bell"></i>
          <?php
            $notifCount = (new NotificationModel())->countUnread(Auth::id());
            if ($notifCount > 0): ?>
          <span class="notif-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
          <?php endif; ?>
          <div class="notif-panel" id="notifPanel">
            <div class="notif-header">
              <span>Notifications</span>
              <button id="markAllRead">Mark all read</button>
            </div>
            <div class="notif-list" id="notifList">
              <div class="notif-loading"><i class="fa fa-spinner fa-spin"></i></div>
            </div>
          </div>
        </div>

        <!-- User Menu -->
        <div class="nav-user" id="userMenuToggle">
          <img src="<?= Helper::avatarUrl($authUser['avatar']) ?>" alt="<?= Helper::sanitize($authUser['username']) ?>" class="nav-avatar" width="36" height="36" decoding="async">
          <div class="user-menu" id="userMenu">
            <div class="user-menu-header">
              <img src="<?= Helper::avatarUrl($authUser['avatar']) ?>" alt="<?= Helper::sanitize($authUser['full_name']) ?>" width="52" height="52" decoding="async">
              <div>
                <strong><?= Helper::sanitize($authUser['full_name']) ?></strong>
                <span>@<?= $authUser['username'] ?></span>
              </div>
            </div>
            <div class="user-menu-links">
              <a href="/@<?= $authUser['username'] ?>"><i class="fa fa-user"></i> Profile</a>
              <a href="/bookmarks"><i class="fa fa-bookmark"></i> Bookmarks</a>
              <a href="/settings"><i class="fa fa-cog"></i> Settings</a>
              <?php if (Auth::isAdmin()): ?>
              <a href="/admin"><i class="fa fa-shield"></i> Admin Panel</a>
              <?php endif; ?>
              <?php if (Auth::isManager()): ?>
              <a href="/manager"><i class="fa fa-tasks"></i> Manager Panel</a>
              <?php endif; ?>
              <?php if (Auth::isEmployee()): ?>
              <a href="/employee"><i class="fa fa-briefcase"></i> Employee Panel</a>
              <?php endif; ?>
              <?php if (Auth::isHR()): ?>
              <a href="/hr"><i class="fa fa-id-card"></i> HR Panel</a>
              <?php endif; ?>
              <hr>
              <a href="/logout" class="text-red"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
          </div>
        </div>

      <?php else: ?>
        <a href="/search" class="nav-mobile-search" aria-label="Search">
          <i class="fa fa-search"></i>
        </a>
        <a href="/login" class="btn-ghost">Login</a>
        <a href="/register" class="btn-primary">Sign Up</a>
      <?php endif; ?>

      <!-- Mobile Menu Toggle -->
      <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <!-- Mobile Nav -->
  <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
  <div class="mobile-nav" id="mobileNav">
    <?php if (Auth::check() && Auth::isAdmin()): ?>
    <div class="mobile-nav-adminmenu">
      <div class="mobile-nav-adminmenuhead">
        <img src="<?= Helper::avatarUrl($authUser['avatar'] ?? null) ?>" alt="<?= Helper::sanitize($authUser['full_name'] ?? 'Admin') ?>" class="mobile-nav-adminavatar" width="68" height="68" decoding="async">
        <div class="mobile-nav-adminmeta">
          <strong><?= Helper::sanitize($authUser['full_name'] ?? 'Admin') ?></strong>
          <span>@<?= Helper::sanitize($authUser['username'] ?? 'admin') ?></span>
        </div>
      </div>
      <div class="mobile-nav-adminlinks">
        <a href="/profile" class="mobile-nav-link"><i class="fa fa-user"></i><span>Profile</span></a>
        <a href="/bookmarks" class="mobile-nav-link"><i class="fa fa-bookmark"></i><span>Bookmarks</span></a>
        <a href="/settings" class="mobile-nav-link"><i class="fa fa-cog"></i><span>Settings</span></a>
        <a href="/admin" class="mobile-nav-link"><i class="fa fa-shield-halved"></i><span>Admin Panel</span></a>
        <a href="/manager" class="mobile-nav-link"><i class="fa fa-list-check"></i><span>Manager Panel</span></a>
        <a href="/employee" class="mobile-nav-link"><i class="fa fa-briefcase"></i><span>Employee Panel</span></a>
        <a href="/hr" class="mobile-nav-link"><i class="fa fa-id-card"></i><span>HR Panel</span></a>
      </div>
      <div class="mobile-nav-adminfooter">
        <a href="/logout" class="mobile-nav-link mobile-nav-adminlogout"><i class="fa fa-right-from-bracket"></i><span>Logout</span></a>
        <?= Helper::socialLinksHtml('mobile-nav-social') ?>
      </div>
    </div>
    <?php else: ?>
    <a href="/" class="mobile-nav-link"><i class="fa fa-home"></i> Home</a>
    <a href="/trending" class="mobile-nav-link"><i class="fa fa-fire"></i> Trending</a>
    <a href="/explore" class="mobile-nav-link"><i class="fa fa-compass"></i> Explore</a>
    <a href="/community" class="mobile-nav-link"><i class="fa fa-users"></i> Community</a>
    <a href="/more" class="mobile-nav-link"><i class="fa fa-grid-2"></i> More</a>
    <a href="/search" class="mobile-nav-link"><i class="fa fa-search"></i> Search</a>
    <?php if (Auth::check()): ?>
    <a href="/community/create" class="mobile-nav-link"><i class="fa fa-pen"></i> Write</a>
    <a href="/bookmarks" class="mobile-nav-link"><i class="fa fa-bookmark"></i> Bookmarks</a>
    <a href="/logout" class="mobile-nav-link text-red"><i class="fa fa-sign-out-alt"></i> Logout</a>
    <?php else: ?>
    <a href="/login" class="mobile-nav-link"><i class="fa fa-sign-in-alt"></i> Login</a>
    <a href="/register" class="mobile-nav-link"><i class="fa fa-user-plus"></i> Sign Up</a>
    <?php endif; ?>
    <?= Helper::socialLinksHtml('mobile-nav-social') ?>
    <?php endif; ?>
  </div>
</nav>

<!-- ===== PAGE WRAPPER ===== -->
<div class="page-wrapper">
