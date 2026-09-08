<?php
Auth::requireLogin();
$pageTitle = 'Create Community Post - FatakNews';
$pageDesc = 'Create a community post on FatakNews and share your perspective with the audience.';
$canonicalUrl = Helper::siteUrl('community/create');
$breadcrumbItems = [
    ['name' => 'Home', 'url' => Helper::siteUrl()],
    ['name' => 'Community', 'url' => Helper::siteUrl('community')],
    ['name' => 'Create Post', 'url' => $canonicalUrl],
];
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $pageTitle,
        'description' => $pageDesc,
        'url' => $canonicalUrl,
    ],
    Helper::breadcrumbSchema($breadcrumbItems),
];
$bodyClass = 'community-page community-create-page';
$cuUser = Auth::user();
$mBarTitle = 'Create Post';
include VIEW . 'layouts/header.php';
?>
<form action="/api/posts" method="post" enctype="multipart/form-data" id="communityCreateForm" class="m-compose">
  <?= Csrf::field() ?>
  <input type="hidden" name="type" value="community_post">
  <input type="hidden" name="allow_comments" value="1">

  <div class="m-compose-user">
    <img src="<?= Helper::avatarUrl($cuUser['avatar'] ?? null) ?>" alt="">
    <div>
      <strong><?= Helper::sanitize($cuUser['full_name'] ?: ('@' . $cuUser['username'])) ?></strong>
      <span>Posting to Community</span>
    </div>
  </div>

  <input type="text" class="m-compose-title" name="title" placeholder="Title" required>
  <textarea class="m-compose-body" name="content" placeholder="Write your post here..." required></textarea>

  <div class="m-compose-panel" data-panel="photo" hidden>
    <input type="file" name="thumbnail" accept="image/*" id="cuThumb">
    <div class="m-compose-preview" id="cuThumbPreview" hidden></div>
  </div>
  <div class="m-compose-panel" data-panel="video" hidden>
    <input type="url" name="video_url" placeholder="YouTube / Facebook / Instagram / X link">
    <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime">
    <label class="m-compose-check"><input type="checkbox" name="location" value="shorts"> Post as a Short (vertical video)</label>
  </div>
  <div class="m-compose-panel" data-panel="tags" hidden>
    <input type="text" name="tags" placeholder="Tags — comma separated (politics, campus, local)">
  </div>

  <div class="m-compose-add">
    <span>Add to your post</span>
    <div class="m-compose-addbtns">
      <button type="button" data-toggle-panel="photo" aria-label="Add photo"><i data-lucide="image"></i></button>
      <button type="button" data-toggle-panel="video" aria-label="Add video"><i data-lucide="video"></i></button>
      <button type="button" data-toggle-panel="tags" aria-label="Add tags"><i data-lucide="hash"></i></button>
    </div>
  </div>

  <div class="m-compose-actions">
    <button class="m-compose-post" type="submit" name="status" value="published" id="communitySubmitPublish">Post</button>
    <button class="m-compose-draft" type="submit" name="status" value="draft" id="communitySubmitDraft">Save draft</button>
  </div>
</form>
<?php
$extraScripts = <<<HTML
<script>
(function initCommunityCreateForm() {
  const form = document.getElementById('communityCreateForm');
  if (!form) return;

  async function submitCommunityForm(submitter, retried = false) {
    const originalLabel = submitter?.dataset.originalLabel || submitter?.innerHTML || '';
    const formData = new FormData(form);

    if (submitter?.name && submitter?.value) {
      formData.set(submitter.name, submitter.value);
    }

    if (submitter) {
      if (!submitter.dataset.originalLabel) {
        submitter.dataset.originalLabel = originalLabel;
      }
      submitter.disabled = true;
      submitter.innerHTML = submitter.value === 'draft' ? 'Saving...' : 'Submitting...';
    }

    try {
      const response = await fetch(resolveAppUrl('/api/posts'), {
        method: 'POST',
        headers: {
          'X-CSRF-Token': APP.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });

      const data = await response.json();

      if (data?.csrf_token) {
        window.syncAppCsrfToken?.(data.csrf_token);
      }

      if (response.status === 403 && data?.error === 'Invalid CSRF token' && !retried) {
        const refreshed = await window.refreshAppCsrfToken?.();
        if (refreshed) {
          return submitCommunityForm(submitter, true);
        }
      }

      if (!response.ok || !data.success) {
        throw new Error(data.error || 'Submission failed');
      }

      Toast.show(data.message || 'Submitted successfully', 'success');
      window.setTimeout(() => {
        window.location.href = submitter?.value === 'draft'
          ? resolveAppUrl('/community/create')
          : resolveAppUrl('/community');
      }, 500);
    } catch (error) {
      Toast.show(error.message || 'Submission failed', 'error');
    } finally {
      if (submitter) {
        submitter.disabled = false;
        submitter.innerHTML = originalLabel;
      }
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitter = event.submitter;
    await submitCommunityForm(submitter);
  });

  form.querySelectorAll('[data-toggle-panel]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const panel = form.querySelector('.m-compose-panel[data-panel="' + btn.dataset.togglePanel + '"]');
      if (!panel) return;
      panel.hidden = !panel.hidden;
      btn.classList.toggle('is-on', !panel.hidden);
      if (!panel.hidden) { const f = panel.querySelector('input,textarea'); if (f) f.focus(); }
    });
  });

  const thumb = document.getElementById('cuThumb');
  const preview = document.getElementById('cuThumbPreview');
  if (thumb) thumb.addEventListener('change', () => {
    const file = thumb.files && thumb.files[0];
    if (!file) { preview.hidden = true; preview.innerHTML = ''; return; }
    preview.hidden = false;
    preview.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="">';
  });

  const ta = form.querySelector('.m-compose-body');
  if (ta) {
    const grow = () => { ta.style.height = 'auto'; ta.style.height = Math.max(120, ta.scrollHeight) + 'px'; };
    ta.addEventListener('input', grow); grow();
  }
})();
</script>
HTML;
?>
<?php include VIEW . 'layouts/mobile_bottom_nav.php'; ?>
<?php include VIEW . 'layouts/footer.php'; ?>
