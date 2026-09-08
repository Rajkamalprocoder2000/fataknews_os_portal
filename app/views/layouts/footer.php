</div><!-- /page-wrapper -->

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
  <div class="footer-container">
    <div class="footer-top">
      <!-- Brand -->
      <div class="footer-brand">
        <div class="footer-logo">
          <div class="logo-icon"><i class="fa fa-bolt"></i></div>
          <span>Fatak<strong>News</strong></span>
        </div>
        <p>India's fastest, youth-first digital news platform. Breaking news, community stories, and more - Fatafat!</p>
        <?= Helper::socialLinksHtml() ?>
      </div>

      <!-- Categories -->
      <div class="footer-col">
        <h4>Topics</h4>
        <?php
          $footerCats = Helper::cacheRemember('layout_footer_topics_v1', 300, static function (): array {
              return (new CategoryModel())->getTopLevel();
          });
          foreach (array_slice($footerCats, 0, 6) as $cat):
        ?>
        <a href="/category/<?= $cat['slug'] ?>"><?= $cat['name'] ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Company -->
      <div class="footer-col">
        <h4>Company</h4>
        <a href="/about">About Us</a>
        <a href="/team">Editorial Team</a>
        <a href="/editorial-standards">Editorial Standards</a>
        <a href="/careers">Careers</a>
        <a href="/advertise">Advertise</a>
        <a href="/contact">Contact</a>
        <a href="/press">Press Room</a>
      </div>

      <!-- Legal -->
      <div class="footer-col">
        <h4>Legal</h4>
        <a href="/privacy">Privacy Policy</a>
        <a href="/terms">Terms of Service</a>
        <a href="/disclaimer">Disclaimer</a>
        <a href="/corrections">Corrections</a>
        <a href="/sitemap.xml">Sitemap</a>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> FatakNews.in - All rights reserved.</span>
      <span>Made with <i class="fa fa-heart text-red"></i> in India</span>
    </div>
  </div>
</footer>

<!-- ===== MOBILE BOTTOM NAV ===== -->
<?php
$mNavPath = rtrim(Helper::requestPath(), '/') ?: '/';
$mNavItems = [
  ['/', 'home', 'Home', ['/', '/feed']],
  ['/shorts', 'play-circle', 'Shorts', ['/shorts']],
  ['/trending', 'compass', 'Explore', ['/explore', '/trending', '/search', '/categories']],
  ['/community', 'users', 'Community', ['/community']],
  [Auth::check() ? '/profile' : '/login', 'user', 'Profile', ['/profile', '/settings', '/login', '/notifications', '/bookmarks']],
];
?>
<nav class="m-bottomnav m-only" aria-label="Primary">
  <?php foreach ($mNavItems as [$href, $icon, $label, $match]): ?>
  <a href="<?= $href ?>" class="<?= in_array($mNavPath, $match, true) ? 'active' : '' ?>">
    <i data-lucide="<?= $icon ?>"></i><span><?= $label ?></span>
  </a>
  <?php endforeach; ?>
</nav>

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer"></div>

<script>
(function () {
  // render Lucide outline icons (mobile UI)
  function drawIcons(){ if (window.lucide && typeof lucide.createIcons === 'function') lucide.createIcons(); }
  if (document.readyState !== 'loading') drawIcons();
  document.addEventListener('DOMContentLoaded', drawIcons);
  window.addEventListener('load', drawIcons);
  window.fnDrawIcons = drawIcons;

  // splash: hide shortly after first load, remember for the session
  var s = document.getElementById('mSplash');
  if (s && !s.classList.contains('is-hidden')) {
    setTimeout(function () {
      s.classList.add('is-hidden');
      try { sessionStorage.setItem('fn-splash-seen', '1'); } catch (e) {}
    }, 1100);
  }
  // theme toggle: any element with [data-theme-toggle]
  window.fnSetTheme = function (mode) {
    var el = document.documentElement;
    if (mode === 'dark') el.setAttribute('data-theme', 'dark');
    else el.removeAttribute('data-theme');
    try { localStorage.setItem('fn-theme', mode); } catch (e) {}
    document.querySelectorAll('[data-theme-toggle]').forEach(function (t) {
      if (t.type === 'checkbox') t.checked = (mode === 'dark');
    });
  };
  var np = document.getElementById('mNotifPref');
  if (np) {
    try { np.checked = localStorage.getItem('fn-notif-pref') !== '0'; } catch (e) {}
    np.addEventListener('change', function () {
      try { localStorage.setItem('fn-notif-pref', this.checked ? '1' : '0'); } catch (e) {}
    });
  }
  var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  document.querySelectorAll('[data-theme-toggle]').forEach(function (t) {
    if (t.type === 'checkbox') t.checked = (current === 'dark');
    t.addEventListener('change', function () { window.fnSetTheme(this.checked ? 'dark' : 'light'); });
    t.addEventListener('click', function (e) {
      if (this.type !== 'checkbox') { e.preventDefault(); window.fnSetTheme(current === 'dark' ? 'light' : 'dark'); }
    });
  });
})();
</script>

<!-- ===== SCRIPTS ===== -->
<script>
window.APP = Object.assign({}, window.APP || {}, {
  url: '<?= Helper::appUrl() ?>',
  csrfToken: '<?= Csrf::token() ?>',
  userId: <?= Auth::id() ?? 'null' ?>,
  isLoggedIn: <?= Auth::check() ? 'true' : 'false' ?>,
  avatarFallback: '<?= Helper::avatarUrl(null) ?>'
});
</script>
<?php if (empty($appJsPreloaded)): ?>
<?php $appJsVersion = @filemtime(BASE_PATH . '/public/assets/js/app.js') ?: time(); ?>
<script src="/public/assets/js/app.js?v=<?= $appJsVersion ?>"></script>
<?php endif; ?>
<?= $extraScripts ?? '' ?>
</body>
</html>
