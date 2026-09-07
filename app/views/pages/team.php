<?php
$team = Helper::cacheRemember('public_editorial_team_v1', 300, static function (): array {
    return (new UserModel())->getEditorialTeam();
});

$roleTitles = [
    'super_admin' => 'Editor-in-Chief',
    'admin'       => 'Editor',
    'manager'     => 'News Editor',
    'editor'      => 'Sub-Editor',
    'reporter'    => 'Reporter',
];

$pageTitle = 'Editorial Team & Masthead | FatakNews';
$pageDesc = 'Meet the FatakNews editorial team - the editors and reporters responsible for the newsroom\'s reporting, fact-checking, and corrections.';
$canonicalUrl = Helper::siteUrl('team');
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Editorial Team', 'url' => $canonicalUrl],
];

$memberSchema = [];
foreach ($team as $member) {
    $person = [
        '@type' => 'Person',
        'name' => $member['full_name'] ?: ('@' . $member['username']),
        'url' => Helper::siteUrl('@' . $member['username']),
        'jobTitle' => $roleTitles[$member['role_slug']] ?? 'Journalist',
        'worksFor' => [
            '@type' => 'NewsMediaOrganization',
            'name' => 'FatakNews.in',
            'url' => Helper::siteUrl(),
        ],
    ];
    $avatar = Helper::avatarAssetUrl($member['avatar'] ?? null);
    if ($avatar !== null) {
        $person['image'] = $avatar;
    }
    if (trim((string)($member['bio'] ?? '')) !== '') {
        $person['description'] = trim((string)$member['bio']);
    }
    if (!empty($member['website']) && filter_var($member['website'], FILTER_VALIDATE_URL)) {
        $person['sameAs'] = [$member['website']];
    }
    $memberSchema[] = $person;
}

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'AboutPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
        'mainEntity' => array_merge(Helper::organizationSchema(), [
            'employee' => $memberSchema,
        ]),
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
];

$bodyClass = 'team-page';
include VIEW . 'layouts/header.php';
?>
<div class="home-grid">
  <main class="feed-col">
    <section class="sidebar-widget" style="margin-bottom:24px">
      <?= Helper::breadcrumbNav($breadcrumbItems) ?>
      <div class="widget-title"><i class="fa fa-users"></i> Masthead</div>
      <h1 style="font-family:'Baloo 2',cursive;font-size:34px;line-height:1.15;margin-top:6px">Editorial Team</h1>
      <p style="color:var(--muted);margin-top:8px;max-width:680px">
        These are the editors and reporters responsible for FatakNews reporting. Our
        sourcing, fact-checking, corrections, and use of AI tools are described in our
        <a href="/editorial-standards">editorial standards</a>.
      </p>
    </section>

    <?php if (empty($team)): ?>
    <div class="empty-state"><i class="fa fa-users"></i><h3>Team profiles are being updated</h3></div>
    <?php else: ?>
    <div class="team-grid">
      <?php foreach ($team as $member):
        $memberUrl = '/@' . $member['username'];
        $memberTitle = $roleTitles[$member['role_slug']] ?? 'Journalist';
        $memberSiteOk = !empty($member['website']) && filter_var($member['website'], FILTER_VALIDATE_URL);
      ?>
      <article class="team-card">
        <a href="<?= $memberUrl ?>" class="team-card-avatar">
          <img src="<?= Helper::avatarUrl($member['avatar']) ?>" alt="<?= Helper::sanitize($member['full_name'] ?: $member['username']) ?>" width="72" height="72" loading="lazy" decoding="async">
        </a>
        <div class="team-card-body">
          <h2 class="team-card-name">
            <a href="<?= $memberUrl ?>"><?= Helper::sanitize($member['full_name'] ?: ('@' . $member['username'])) ?></a>
            <?php if (!empty($member['is_verified'])): ?><i class="fa fa-check-circle verified-icon" title="Verified"></i><?php endif; ?>
          </h2>
          <span class="team-card-role"><?= Helper::sanitize($memberTitle) ?><?php if (!empty($member['location'])): ?> &middot; <?= Helper::sanitize($member['location']) ?><?php endif; ?></span>
          <?php if (trim((string)($member['bio'] ?? '')) !== ''): ?>
          <p class="team-card-bio"><?= Helper::sanitize($member['bio']) ?></p>
          <?php endif; ?>
          <span class="team-card-links">
            <a href="<?= $memberUrl ?>"><?= (int)$member['posts_count'] ?> stor<?= (int)$member['posts_count'] === 1 ? 'y' : 'ies' ?></a>
            <?php if ($memberSiteOk): ?>
            <a href="<?= Helper::sanitize($member['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= Helper::sanitize(parse_url($member['website'], PHP_URL_HOST) ?: 'Website') ?></a>
            <?php endif; ?>
          </span>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>

  <aside class="sidebar-col">
    <div class="sidebar-widget">
      <div class="widget-title"><i class="fa fa-scale-balanced"></i> How We Work</div>
      <p style="font-size:14px;color:var(--muted);line-height:1.6;margin-bottom:10px">Every published story is reviewed by an editor before it goes live.</p>
      <a href="/editorial-standards" class="tag-chip">Editorial standards</a>
      <a href="/corrections" class="tag-chip">Corrections</a>
      <a href="/contact" class="tag-chip">Contact the desk</a>
    </div>
  </aside>
</div>
<?php include VIEW . 'layouts/footer.php'; ?>
