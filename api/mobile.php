<?php
/**
 * api/mobile.php — JSON REST API for the FatakNews Flutter staff app.
 * Bearer (JWT) auth, no cookies, no CSRF. Mounted at /api/mobile/*.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../app/helpers/Jwt.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') { http_response_code(204); exit; }

$path = '/' . trim(preg_replace('#^.*/api/mobile#', '', strtok($_SERVER['REQUEST_URI'], '?')), '/');
$seg  = $path === '/' ? [] : explode('/', ltrim($path, '/'));

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) { $body = $_POST; }

$db = Database::getInstance();

function out($data, int $code = 200): void { http_response_code($code); echo json_encode($data); exit; }
function fail(string $msg, int $code = 400): void { out(['success' => false, 'error' => $msg], $code); }
function inp(array $body, string $k, $def = null) { return $body[$k] ?? $_GET[$k] ?? $def; }

/* ---------------- auth ---------------- */
function currentUser(): ?array {
    static $u = null;
    if ($u !== null) return $u ?: null;
    $claims = Jwt::verify(Jwt::bearerToken());
    if (!$claims || empty($claims['uid'])) { $u = false; return null; }
    $row = (new UserModel())->findById((int) $claims['uid']);
    if (!$row || empty($row['is_active'])) { $u = false; return null; }
    $u = $row;
    return $row;
}
function requireUser(): array {
    $u = currentUser();
    if (!$u) fail('Authentication required', 401);
    return $u;
}
function requireRole(string ...$roles): array {
    $u = requireUser();
    if (!in_array($u['role_slug'] ?? '', $roles, true)) fail('You do not have access to this action', 403);
    return $u;
}
function userPerms(array $u): array {
    $p = json_decode($u['permissions'] ?? '[]', true);
    return is_array($p) ? $p : [];
}
function can(array $u, string $perm): bool {
    $p = userPerms($u);
    return in_array('all', $p, true) || in_array($perm, $p, true);
}
function isManagerPlus(array $u): bool { return in_array($u['role_slug'] ?? '', ['super_admin','admin','manager'], true); }
function isAdminPlus(array $u): bool { return in_array($u['role_slug'] ?? '', ['super_admin','admin'], true); }
function isHr(array $u): bool { return in_array($u['role_slug'] ?? '', ['super_admin','admin','hr'], true); }

$r0 = $seg[0] ?? '';
$r1 = $seg[1] ?? '';
$r2 = $seg[2] ?? '';

try {

/* ======================= AUTH ======================= */
if ($r0 === 'auth' && $r1 === 'login' && $method === 'POST') {
    $login = trim((string) inp($body, 'email', inp($body, 'username', '')));
    $pass  = (string) inp($body, 'password', '');
    if ($login === '' || $pass === '') fail('Email and password are required', 422);

    $um = new UserModel();
    $user = strpos($login, '@') !== false ? $um->findByEmail($login) : $um->findByUsername($login);
    if (!$user || !password_verify($pass, $user['password_hash'])) fail('Invalid credentials', 401);
    if (empty($user['is_active'])) fail('Account is suspended', 403);
    if (($user['role_slug'] ?? 'user') === 'user') fail('This app is for FatakNews staff only', 403);

    recordMobileLogin($um, (int) $user['id']);
    $full = $um->findById((int) $user['id']);
    $token = Jwt::issue(['uid' => (int) $user['id'], 'role' => $full['role_slug']]);
    out(['success' => true, 'token' => $token, 'expires_in' => (int) JWT_EXPIRE, 'user' => publicUser($full)]);
}

if ($r0 === 'auth' && $r1 === 'google' && $method === 'POST') {
    $idToken = trim((string) inp($body, 'id_token', ''));
    if ($idToken === '') fail('Google ID token is required', 422);
    if (trim((string) GOOGLE_CLIENT_ID) === '') fail('Google sign-in is not configured', 503);

    $profile = googleIdTokenProfile($idToken);
    $googleId = trim((string) ($profile['sub'] ?? ''));
    $email = trim((string) ($profile['email'] ?? ''));
    if ($googleId === '' || $email === '' || empty($profile['email_verified'])) {
        fail('Google did not return a verified email address', 401);
    }

    $um = new UserModel();
    $user = $um->findByGoogleIdentity($googleId);
    if (!$user) {
        $user = $um->findByEmail($email);
        if ($user) {
            $um->linkGoogleIdentity((int) $user['id'], $googleId, true);
            $user = $um->findById((int) $user['id']);
        }
    }
    if (!$user || empty($user['is_active'])) fail('Your account is not available for login', 403);
    if (($user['role_slug'] ?? 'user') === 'user') fail('This app is for FatakNews staff only', 403);

    recordMobileLogin($um, (int) $user['id']);
    $full = $um->findById((int) $user['id']);
    $token = Jwt::issue(['uid' => (int) $full['id'], 'role' => $full['role_slug']]);
    out(['success' => true, 'token' => $token, 'expires_in' => (int) JWT_EXPIRE, 'user' => publicUser($full)]);
}

function googleIdTokenProfile(string $idToken): array {
    if (!function_exists('curl_init')) fail('Google sign-in is unavailable on this server', 503);

    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $profile = is_string($raw) ? json_decode($raw, true) : null;
    if ($status !== 200 || !is_array($profile)) fail('Google ID token could not be verified', 401);
    if (!in_array($profile['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
        fail('Google ID token issuer is invalid', 401);
    }
    if (!hash_equals((string) GOOGLE_CLIENT_ID, (string) ($profile['aud'] ?? ''))) {
        fail('Google ID token was issued for another client', 401);
    }
    if ((int) ($profile['exp'] ?? 0) < time()) fail('Google ID token has expired', 401);

    return $profile;
}

if ($r0 === 'auth' && $r1 === 'refresh' && $method === 'POST') {
    $u = requireUser();
    out(['success' => true, 'token' => Jwt::issue(['uid' => (int) $u['id'], 'role' => $u['role_slug']]), 'expires_in' => (int) JWT_EXPIRE]);
}

function publicUser(array $u): array {
    return [
        'id' => (int) $u['id'],
        'username' => $u['username'],
        'full_name' => $u['full_name'],
        'email' => $u['email'],
        'phone' => $u['phone'] ?? null,
        'avatar' => Helper::avatarUrl($u['avatar'] ?? null),
        'bio' => $u['bio'] ?? '',
        'location' => $u['location'] ?? '',
        'website' => $u['website'] ?? '',
        'role' => $u['role_slug'] ?? 'user',
        'role_name' => $u['role_name'] ?? '',
        'permissions' => userPerms($u),
        'is_verified' => (bool) ($u['is_verified'] ?? false),
        'posts_count' => (int) ($u['posts_count'] ?? 0),
    ];
}

// Login must still succeed when optional audit columns have not been deployed.
function recordMobileLogin(UserModel $users, int $userId): void {
    try {
        $users->updateLastLogin($userId);
    } catch (Throwable $error) {
        error_log('Mobile API could not update last-login audit data: ' . $error->getMessage());
    }
}

/* ======================= ME ======================= */
if ($r0 === 'me' && $method === 'GET') {
    out(['success' => true, 'user' => publicUser(requireUser())]);
}
if ($r0 === 'me' && in_array($method, ['PUT','PATCH','POST'], true)) {
    $u = requireUser();
    $fields = [];
    foreach (['full_name','phone','bio','location','website'] as $f) {
        if (array_key_exists($f, $body)) $fields[$f] = trim((string) $body[$f]);
    }
    if (!empty($body['password'])) {
        if (strlen((string) $body['password']) < 8) fail('Password must be at least 8 characters', 422);
        $fields['password_hash'] = password_hash((string) $body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
    if (!empty($body['avatar'])) $fields['avatar'] = basename((string) $body['avatar']);
    if ($fields) $db->update('users', $fields, 'id=?', [(int) $u['id']]);
    out(['success' => true, 'user' => publicUser((new UserModel())->findById((int) $u['id']))]);
}

/* ======================= DASHBOARD ======================= */
if ($r0 === 'dashboard' && $method === 'GET') {
    $u = requireUser();
    $role = $u['role_slug'];
    $stats = [];

    if (isManagerPlus($u) || in_array($role, ['editor','reporter'], true)) {
        $mineOnly = in_array($role, ['reporter'], true);
        $scope = $mineOnly ? ' AND user_id=' . (int) $u['id'] : '';
        $stats['my_posts']      = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE user_id=?", [(int) $u['id']])['c'] ?? 0);
        $stats['published']     = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='published'$scope")['c'] ?? 0);
        $stats['pending']       = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='pending'$scope")['c'] ?? 0);
        $stats['drafts']        = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='draft' AND user_id=?", [(int) $u['id']])['c'] ?? 0);
        $stats['total_views']   = (int) ($db->fetchOne("SELECT COALESCE(SUM(views_count),0) c FROM posts WHERE 1=1$scope")['c'] ?? 0);
    }
    if (isAdminPlus($u)) {
        $stats['total_users']   = (int) ($db->fetchOne("SELECT COUNT(*) c FROM users")['c'] ?? 0);
        $stats['today_posts']   = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE DATE(created_at)=CURDATE()")['c'] ?? 0);
        $stats['breaking']      = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE is_breaking=1 AND status='published'")['c'] ?? 0);
        $stats['comments']      = (int) ($db->fetchOne("SELECT COUNT(*) c FROM comments")['c'] ?? 0);
    }
    if (isHr($u)) {
        try {
            $stats['hr'] = (new HrModel())->getDashboardStats();
        } catch (Throwable $e) { $stats['hr'] = []; }
    }
    $stats['unread_notifications'] = (new NotificationModel())->countUnread((int) $u['id']);

    // 7-day views trend for charts
    $trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $trend[] = [
            'date' => $d,
            'posts' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE DATE(created_at)=?", [$d])['c'] ?? 0),
        ];
    }
    out(['success' => true, 'role' => $role, 'stats' => $stats, 'trend' => $trend]);
}

/* ======================= POSTS ======================= */
if ($r0 === 'posts') {
    $pm = new PostModel();

    // list
    if ($method === 'GET' && $r1 === '') {
        $u = requireUser();
        $page = max(1, (int) inp($body, 'page', 1));
        $status = trim((string) inp($body, 'status', ''));
        $mine = (string) inp($body, 'mine', '') === '1' || in_array($u['role_slug'], ['reporter'], true);
        $q = trim((string) inp($body, 'q', ''));
        $cat = (int) inp($body, 'category_id', 0);
        $loc = trim((string) inp($body, 'location', ''));

        $where = "WHERE 1=1";
        $params = [];
        if ($mine) { $where .= " AND p.user_id=?"; $params[] = (int) $u['id']; }
        if ($status !== '' && in_array($status, ['draft','pending','published','rejected'], true)) { $where .= " AND p.status=?"; $params[] = $status; }
        if ($cat > 0) { $where .= " AND p.category_id=?"; $params[] = $cat; }
        if ($loc !== '') { $where .= " AND COALESCE(p.location,'')=?"; $params[] = $loc; }
        if ($q !== '') { $where .= " AND (p.title LIKE ? OR p.excerpt LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }

        $res = $db->paginate(
            "SELECT p.id,p.title,p.slug,p.excerpt,p.status,p.type,p.location,p.thumbnail,p.video_url,
                    p.views_count,p.likes_count,p.comments_count,p.is_featured,p.is_breaking,
                    p.created_at,p.published_at,p.updated_at,
                    u.full_name AS author, c.name AS category_name
             FROM posts p JOIN users u ON p.user_id=u.id LEFT JOIN categories c ON p.category_id=c.id
             $where ORDER BY p.created_at DESC",
            $params, $page, 20
        );
        foreach ($res['data'] as &$p) { $p['thumbnail_url'] = Helper::thumbnailUrl($p['thumbnail']); }
        out(['success' => true] + $res);
    }

    if ($method === 'GET' && $r1 === 'pending') {
        requireRole('super_admin','admin','manager','editor');
        out(['success' => true] + $pm->getPending(max(1, (int) inp($body, 'page', 1))));
    }

    // single
    if ($method === 'GET' && ctype_digit($r1)) {
        requireUser();
        $post = $pm->findById((int) $r1);
        if (!$post) fail('Post not found', 404);
        $post['thumbnail_url'] = Helper::thumbnailUrl($post['thumbnail'] ?? null);
        $post['tags'] = array_column($db->fetchAll("SELECT t.name FROM post_tags pt JOIN tags t ON pt.tag_id=t.id WHERE pt.post_id=?", [(int) $r1]), 'name');
        out(['success' => true, 'post' => $post]);
    }

    // create / update
    if (($method === 'POST' && $r1 === '') || (in_array($method, ['PUT','PATCH'], true) && ctype_digit($r1))) {
        $u = requireRole('super_admin','admin','manager','editor','reporter');
        $editId = $method === 'POST' ? (int) inp($body, 'id', 0) : (int) $r1;
        $existing = $editId ? $pm->findById($editId) : null;
        if ($editId && !$existing) fail('Post not found', 404);
        if ($existing && !isManagerPlus($u) && (int) $existing['user_id'] !== (int) $u['id']) fail('You can only edit your own posts', 403);

        $title = trim((string) inp($body, 'title', ''));
        $content = trim((string) inp($body, 'content', ''));
        if ($title === '' || $content === '') fail('Title and content are required', 422);

        $status = (string) inp($body, 'status', 'draft');
        if ($status === 'published' && !isManagerPlus($u)) $status = 'pending';

        $allowedLoc = ['home','category','both','explore','shorts'];
        $loc = (string) inp($body, 'location', 'both');
        if (!in_array($loc, $allowedLoc, true)) $loc = 'both';

        $type = (string) inp($body, 'type', 'news');
        if (!in_array($type, ['news','article','community_post','thought','breaking'], true)) $type = 'news';

        $data = [
            'user_id'        => $existing['user_id'] ?? (int) $u['id'],
            'title'          => $title,
            'slug'           => trim((string) inp($body, 'slug', '')),
            'content'        => $content,
            'excerpt'        => trim((string) inp($body, 'excerpt', '')),
            'category_id'    => ((int) inp($body, 'category_id', 0)) ?: null,
            'type'           => $type,
            'location'       => $loc,
            'video_url'      => trim((string) inp($body, 'video_url', '')),
            'status'         => $status,
            'is_featured'    => (int) !empty($body['is_featured']),
            'is_breaking'    => (isManagerPlus($u) && !empty($body['is_breaking'])) ? 1 : 0,
            'allow_comments' => (int) ($body['allow_comments'] ?? 1),
            'source_name'    => trim((string) inp($body, 'source_name', '')),
            'source_url'     => trim((string) inp($body, 'source_url', '')),
            'image_alt'      => trim((string) inp($body, 'image_alt', '')),
            'seo_title'      => trim((string) inp($body, 'seo_title', '')),
            'seo_description' => trim((string) inp($body, 'seo_description', '')),
            'thumbnail'      => trim((string) inp($body, 'thumbnail', '')) ?: ($existing['thumbnail'] ?? null),
        ];
        $isCommunity = in_array($type, ['community_post','thought'], true);
        if (!$isCommunity && !$data['category_id']) fail('Please select a category', 422);
        if ($loc === 'shorts' && !$data['video_url'] && empty($existing['video_url'])) fail('A Short needs a video link', 422);

        try {
            if ($editId) { $pm->update($editId, $data); $pid = $editId; }
            else { $pid = $pm->create($data); }
            $tags = array_filter(array_map('trim', explode(',', (string) inp($body, 'tags', ''))));
            if ($tags || $editId) {
                $db->delete('post_tags', 'post_id=?', [$pid]);
                foreach ($tags as $tn) {
                    $slug = Helper::slug($tn);
                    if ($slug === '') continue;
                    $tr = $db->fetchOne("SELECT id FROM tags WHERE slug=?", [$slug]);
                    $tid = $tr ? $tr['id'] : $db->insert('tags', ['name' => $tn, 'slug' => $slug]);
                    $db->insert('post_tags', ['post_id' => $pid, 'tag_id' => $tid]);
                }
            }
        } catch (Throwable $e) {
            fail($e->getMessage(), 422);
        }
        out(['success' => true, 'id' => $pid, 'status' => $status]);
    }

    // moderation / toggles
    if ($method === 'POST' && ctype_digit($r1) && $r2 !== '') {
        $u = requireRole('super_admin','admin','manager','editor');
        $pid = (int) $r1;
        $post = $pm->findById($pid);
        if (!$post) fail('Post not found', 404);
        $nm = new NotificationModel();

        switch ($r2) {
            case 'approve':
                $pm->approve($pid, (int) $u['id']);
                $nm->send((int) $post['user_id'], 'post_approved', 'Your post "' . mb_substr($post['title'], 0, 60) . '" was approved', (int) $u['id'], '/' . $post['slug']);
                out(['success' => true]);
            case 'reject':
                $reason = trim((string) inp($body, 'reason', 'Does not meet editorial standards'));
                $pm->reject($pid, $reason);
                $nm->send((int) $post['user_id'], 'post_rejected', 'Your post was rejected: ' . $reason, (int) $u['id']);
                out(['success' => true]);
            case 'toggle-featured':
                $pm->update($pid, ['is_featured' => $post['is_featured'] ? 0 : 1]);
                out(['success' => true, 'is_featured' => !$post['is_featured']]);
            case 'toggle-breaking':
                requireRole('super_admin','admin','manager');
                $pm->update($pid, ['is_breaking' => $post['is_breaking'] ? 0 : 1]);
                out(['success' => true, 'is_breaking' => !$post['is_breaking']]);
            case 'engagement':
                requireRole('super_admin','admin');
                $pm->update($pid, [
                    'likes_count' => max(0, (int) inp($body, 'likes_count', $post['likes_count'])),
                    'views_count' => max(0, (int) inp($body, 'views_count', $post['views_count'])),
                ]);
                out(['success' => true]);
            default:
                fail('Unknown action', 400);
        }
    }

    if ($method === 'DELETE' && ctype_digit($r1)) {
        $u = requireRole('super_admin','admin','manager','editor');
        $post = $pm->findById((int) $r1);
        if (!$post) fail('Post not found', 404);
        if (!isManagerPlus($u) && (int) $post['user_id'] !== (int) $u['id']) fail('You can only delete your own posts', 403);
        $pm->delete((int) $r1);
        out(['success' => true]);
    }
}

/* ======================= CATEGORIES ======================= */
if ($r0 === 'categories') {
    if ($method === 'GET') {
        requireUser();
        out(['success' => true, 'categories' => $db->fetchAll("SELECT * FROM categories ORDER BY parent_id IS NOT NULL, sort_order, name")]);
    }
    $u = requireRole('super_admin','admin','manager','editor');
    if ($method === 'POST') {
        $name = trim((string) inp($body, 'name', ''));
        if ($name === '') fail('Name is required', 422);
        $id = $db->insert('categories', [
            'name' => $name,
            'slug' => Helper::uniqueSlug('categories', Helper::slug($name)),
            'parent_id' => ((int) inp($body, 'parent_id', 0)) ?: null,
            'color' => trim((string) inp($body, 'color', '#2979FF')),
            'icon' => trim((string) inp($body, 'icon', 'fa-newspaper')),
            'description' => trim((string) inp($body, 'description', '')),
            'sort_order' => (int) inp($body, 'sort_order', 0),
            'is_active' => (int) ($body['is_active'] ?? 1),
        ]);
        out(['success' => true, 'id' => $id]);
    }
    if (in_array($method, ['PUT','PATCH'], true) && ctype_digit($r1)) {
        $f = [];
        foreach (['name','color','icon','description'] as $k) if (array_key_exists($k, $body)) $f[$k] = trim((string) $body[$k]);
        if (array_key_exists('parent_id', $body)) $f['parent_id'] = ((int) $body['parent_id']) ?: null;
        if (array_key_exists('sort_order', $body)) $f['sort_order'] = (int) $body['sort_order'];
        if (array_key_exists('is_active', $body)) $f['is_active'] = (int) (bool) $body['is_active'];
        if ($f) $db->update('categories', $f, 'id=?', [(int) $r1]);
        out(['success' => true]);
    }
    if ($method === 'DELETE' && ctype_digit($r1)) {
        requireRole('super_admin','admin');
        $db->update('posts', ['category_id' => null], 'category_id=?', [(int) $r1]);
        $db->delete('categories', 'id=? OR parent_id=?', [(int) $r1, (int) $r1]);
        out(['success' => true]);
    }
}

/* ======================= USERS ======================= */
if ($r0 === 'users' || $r0 === 'roles') {
    if ($r0 === 'roles') { requireRole('super_admin','admin'); out(['success' => true, 'roles' => $db->fetchAll("SELECT id,name,slug FROM roles ORDER BY id")]); }
    $actor = requireRole('super_admin','admin');

    if ($method === 'GET' && $r1 === '') {
        $page = max(1, (int) inp($body, 'page', 1));
        $q = trim((string) inp($body, 'q', ''));
        $where = "WHERE 1=1"; $params = [];
        if ($q !== '') { $where .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
        $res = $db->paginate("SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON u.role_id=r.id $where ORDER BY u.created_at DESC", $params, $page, 25);
        $res['data'] = array_map('publicUser', $res['data']);
        out(['success' => true] + $res);
    }
    if ($method === 'GET' && ctype_digit($r1)) {
        $row = $db->fetchOne("SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=?", [(int) $r1]);
        if (!$row) fail('User not found', 404);
        out(['success' => true, 'user' => publicUser($row)]);
    }
    if ($method === 'POST' && $r1 === '') {
        foreach (['full_name','username','email','password','role_id'] as $req) if (trim((string) inp($body, $req, '')) === '') fail("$req is required", 422);
        if (strlen((string) $body['password']) < 8) fail('Password must be at least 8 characters', 422);
        $id = $db->insert('users', [
            'role_id' => (int) $body['role_id'],
            'full_name' => trim((string) $body['full_name']),
            'username' => trim((string) $body['username']),
            'email' => trim((string) $body['email']),
            'password_hash' => password_hash((string) $body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
            'phone' => trim((string) inp($body, 'phone', '')),
            'is_active' => 1, 'email_verified' => 1,
        ]);
        out(['success' => true, 'id' => $id]);
    }
    if (in_array($method, ['PUT','PATCH'], true) && ctype_digit($r1)) {
        $f = [];
        foreach (['full_name','username','email','phone','bio','location','website','badge_level'] as $k) if (array_key_exists($k, $body)) $f[$k] = trim((string) $body[$k]);
        if (!empty($body['role_id'])) $f['role_id'] = (int) $body['role_id'];
        if (!empty($body['password'])) $f['password_hash'] = password_hash((string) $body['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        foreach (['is_active','is_verified','email_verified'] as $k) if (array_key_exists($k, $body)) $f[$k] = (int) (bool) $body[$k];
        if ($f) $db->update('users', $f, 'id=?', [(int) $r1]);
        out(['success' => true]);
    }
    if ($method === 'POST' && ctype_digit($r1) && in_array($r2, ['toggle-active','toggle-verified'], true)) {
        if ((int) $r1 === (int) $actor['id']) fail('You cannot change your own status', 409);
        $col = $r2 === 'toggle-active' ? 'is_active' : 'is_verified';
        $cur = $db->fetchOne("SELECT $col FROM users WHERE id=?", [(int) $r1]);
        if (!$cur) fail('User not found', 404);
        $db->update('users', [$col => $cur[$col] ? 0 : 1], 'id=?', [(int) $r1]);
        out(['success' => true, $col => !$cur[$col]]);
    }
    if ($method === 'DELETE' && ctype_digit($r1)) {
        if ((int) $r1 === (int) $actor['id']) fail('You cannot delete your own account', 409);
        $cnt = (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE user_id=?", [(int) $r1])['c'] ?? 0);
        if ($cnt > 0) fail('This user has posts and cannot be deleted', 409);
        $db->delete('users', 'id=?', [(int) $r1]);
        out(['success' => true]);
    }
}

/* ======================= COMMENTS ======================= */
if ($r0 === 'comments') {
    $u = requireRole('super_admin','admin','manager','editor');
    if ($method === 'GET') {
        $page = max(1, (int) inp($body, 'page', 1));
        $res = $db->paginate(
            "SELECT cm.*, u.full_name, u.username, p.title AS post_title, p.slug AS post_slug
             FROM comments cm JOIN users u ON cm.user_id=u.id JOIN posts p ON cm.post_id=p.id
             ORDER BY cm.created_at DESC", [], $page, 30
        );
        out(['success' => true] + $res);
    }
    if ($method === 'DELETE' && ctype_digit($r1)) {
        $db->delete('comments', 'id=? OR parent_id=?', [(int) $r1, (int) $r1]);
        out(['success' => true]);
    }
}

/* ======================= NOTIFICATIONS ======================= */
if ($r0 === 'notifications') {
    $u = requireUser();
    $nm = new NotificationModel();
    if ($method === 'GET') {
        $rows = $db->fetchAll("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 60", [(int) $u['id']]);
        out(['success' => true, 'notifications' => $rows, 'unread' => $nm->countUnread((int) $u['id'])]);
    }
    if ($method === 'POST' && ($r1 === 'read-all' || $r1 === 'read')) {
        if ($r1 === 'read-all' || empty($body['id'])) $nm->markAllRead((int) $u['id']);
        else $db->update('notifications', ['is_read' => 1], 'id=? AND user_id=?', [(int) $body['id'], (int) $u['id']]);
        out(['success' => true]);
    }
}

/* ======================= ANALYTICS ======================= */
if ($r0 === 'analytics' && $method === 'GET') {
    requireRole('super_admin','admin','manager');
    $days = min(90, max(7, (int) inp($body, 'days', 30)));
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $series[] = [
            'date' => $d,
            'posts' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE DATE(created_at)=?", [$d])['c'] ?? 0),
            'comments' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM comments WHERE DATE(created_at)=?", [$d])['c'] ?? 0),
            'users' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM users WHERE DATE(created_at)=?", [$d])['c'] ?? 0),
        ];
    }
    out([
        'success' => true,
        'series' => $series,
        'top_posts' => $db->fetchAll("SELECT id,title,slug,views_count,likes_count,comments_count FROM posts WHERE status='published' ORDER BY views_count DESC LIMIT 10"),
        'by_category' => $db->fetchAll("SELECT c.name, COUNT(p.id) posts, COALESCE(SUM(p.views_count),0) views FROM categories c LEFT JOIN posts p ON p.category_id=c.id AND p.status='published' GROUP BY c.id ORDER BY views DESC LIMIT 12"),
        'totals' => [
            'posts' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM posts WHERE status='published'")['c'] ?? 0),
            'views' => (int) ($db->fetchOne("SELECT COALESCE(SUM(views_count),0) c FROM posts")['c'] ?? 0),
            'users' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM users")['c'] ?? 0),
            'comments' => (int) ($db->fetchOne("SELECT COUNT(*) c FROM comments")['c'] ?? 0),
        ],
    ]);
}

/* ======================= ADS ======================= */
if ($r0 === 'ads') {
    requireRole('super_admin','admin');
    if ($method === 'GET') out(['success' => true, 'ads' => $db->fetchAll("SELECT * FROM ads ORDER BY id DESC")]);
    if ($method === 'POST') {
        $id = $db->insert('ads', [
            'name' => trim((string) inp($body, 'name', 'Ad')),
            'placement' => trim((string) inp($body, 'placement', 'sidebar')),
            'image_url' => trim((string) inp($body, 'image_url', '')),
            'target_url' => trim((string) inp($body, 'target_url', '')),
            'html_code' => (string) inp($body, 'html_code', ''),
            'is_active' => (int) ($body['is_active'] ?? 1),
        ]);
        out(['success' => true, 'id' => $id]);
    }
    if (in_array($method, ['PUT','PATCH'], true) && ctype_digit($r1)) {
        $f = [];
        foreach (['name','placement','image_url','target_url','html_code'] as $k) if (array_key_exists($k, $body)) $f[$k] = (string) $body[$k];
        if (array_key_exists('is_active', $body)) $f['is_active'] = (int) (bool) $body['is_active'];
        if ($f) $db->update('ads', $f, 'id=?', [(int) $r1]);
        out(['success' => true]);
    }
    if ($method === 'DELETE' && ctype_digit($r1)) { $db->delete('ads', 'id=?', [(int) $r1]); out(['success' => true]); }
}

/* ======================= SETTINGS ======================= */
if ($r0 === 'settings') {
    requireRole('super_admin','admin');
    if ($method === 'GET') {
        $rows = $db->fetchAll("SELECT `key`,`value` FROM settings");
        out(['success' => true, 'settings' => array_column($rows, 'value', 'key')]);
    }
    if (in_array($method, ['PUT','PATCH','POST'], true)) {
        foreach (($body['settings'] ?? $body) as $k => $v) {
            if (!is_string($k)) continue;
            $db->query("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)", [$k, (string) $v]);
        }
        out(['success' => true]);
    }
}

/* ======================= STORIES ======================= */
if ($r0 === 'stories') {
    $u = requireUser();
    if ($method === 'GET') {
        out(['success' => true, 'groups' => (new StoryModel())->getActiveGroups((int) $u['id'], 30)]);
    }
    if ($method === 'POST') {
        $id = $db->insert('stories', [
            'user_id' => (int) $u['id'],
            'caption' => trim((string) inp($body, 'caption', '')),
            'background_color' => trim((string) inp($body, 'background_color', '#2D2244')),
            'image' => trim((string) inp($body, 'image', '')),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);
        out(['success' => true, 'id' => $id]);
    }
    if ($method === 'DELETE' && ctype_digit($r1)) {
        $db->delete('stories', 'id=? AND user_id=?', [(int) $r1, (int) $u['id']]);
        out(['success' => true]);
    }
}

/* ======================= HR ======================= */
if ($r0 === 'hr') {
    $u = requireUser();
    $hr = new HrModel();
    $isHrAdmin = isHr($u) || isManagerPlus($u);

    if ($r1 === 'employees' && $method === 'GET') {
        if (!$isHrAdmin) fail('HR access required', 403);
        out(['success' => true] + $hr->getEmployees(max(1, (int) inp($body, 'page', 1)), ((int) inp($body, 'department_id', 0)) ?: null));
    }
    if ($r1 === 'departments') {
        if ($method === 'GET') { out(['success' => true, 'departments' => $db->fetchAll("SELECT * FROM departments ORDER BY name")]); }
        if (!$isHrAdmin) fail('HR access required', 403);
        if ($method === 'POST') { $id = $db->insert('departments', ['name' => trim((string) inp($body, 'name', '')), 'description' => trim((string) inp($body, 'description', ''))]); out(['success' => true, 'id' => $id]); }
        if (in_array($method, ['PUT','PATCH'], true) && ctype_digit($r2)) { $db->update('departments', ['name' => trim((string) inp($body, 'name', '')), 'description' => trim((string) inp($body, 'description', ''))], 'id=?', [(int) $r2]); out(['success' => true]); }
        if ($method === 'DELETE' && ctype_digit($r2)) { $db->delete('departments', 'id=?', [(int) $r2]); out(['success' => true]); }
    }
    if ($r1 === 'attendance') {
        if ($method === 'GET') {
            $target = $isHrAdmin ? ((int) inp($body, 'user_id', 0)) : (int) $u['id'];
            $month = trim((string) inp($body, 'month', date('Y-m')));
            $rows = $db->fetchAll(
                "SELECT * FROM attendance WHERE " . ($target ? "user_id=? AND " : "") . "DATE_FORMAT(date,'%Y-%m')=? ORDER BY date DESC",
                $target ? [$target, $month] : [$month]
            );
            out(['success' => true, 'attendance' => $rows]);
        }
        if ($method === 'POST') {
            $target = $isHrAdmin ? ((int) inp($body, 'user_id', $u['id'])) : (int) $u['id'];
            $hr->markAttendance($target, (string) inp($body, 'status', 'present'), inp($body, 'check_in'));
            out(['success' => true]);
        }
    }
    if ($r1 === 'leaves') {
        if ($method === 'GET') {
            if (($body['pending'] ?? '') === '1' && $isHrAdmin) out(['success' => true, 'leaves' => $hr->getPendingLeaves()]);
            $target = $isHrAdmin && !empty($body['user_id']) ? (int) $body['user_id'] : (int) $u['id'];
            out(['success' => true, 'leaves' => $hr->getLeaves($target, inp($body, 'status'))]);
        }
        if ($method === 'POST' && $r2 === '') {
            $id = $hr->applyLeave([
                'user_id' => (int) $u['id'],
                'leave_type' => trim((string) inp($body, 'leave_type', 'casual')),
                'start_date' => (string) inp($body, 'start_date', date('Y-m-d')),
                'end_date' => (string) inp($body, 'end_date', date('Y-m-d')),
                'reason' => trim((string) inp($body, 'reason', '')),
                'status' => 'pending',
            ]);
            out(['success' => true, 'id' => $id]);
        }
        if ($method === 'POST' && ctype_digit($r2) && in_array($r0 . $r1, ['hrleaves'], true)) {
            if (!$isHrAdmin) fail('HR access required', 403);
            $act = $seg[3] ?? '';
            if ($act === 'approve') { $hr->approveLeave((int) $r2, (int) $u['id']); out(['success' => true]); }
            if ($act === 'reject') { $db->update('leaves', ['status' => 'rejected'], 'id=?', [(int) $r2]); out(['success' => true]); }
        }
    }
    if ($r1 === 'payroll') {
        if (!$isHrAdmin) fail('HR access required', 403);
        if ($method === 'GET') {
            $month = trim((string) inp($body, 'month', date('Y-m')));
            out(['success' => true, 'payroll' => $db->fetchAll("SELECT pr.*, u.full_name FROM payroll pr JOIN users u ON pr.user_id=u.id WHERE pr.month=? ORDER BY u.full_name", [$month])]);
        }
        if ($method === 'POST') {
            $id = $db->insert('payroll', [
                'user_id' => (int) inp($body, 'user_id', 0),
                'month' => (string) inp($body, 'month', date('Y-m')),
                'basic' => (float) inp($body, 'basic', 0),
                'allowances' => (float) inp($body, 'allowances', 0),
                'deductions' => (float) inp($body, 'deductions', 0),
                'net_pay' => (float) inp($body, 'basic', 0) + (float) inp($body, 'allowances', 0) - (float) inp($body, 'deductions', 0),
                'status' => 'generated',
            ]);
            out(['success' => true, 'id' => $id]);
        }
    }
}

/* ======================= UPLOAD ======================= */
if ($r0 === 'upload' && $method === 'POST') {
    requireRole('super_admin','admin','manager','editor','reporter','hr');
    $dir = in_array((string) ($_POST['dir'] ?? 'content'), ['thumbnails','avatars','content','community','stories','shorts'], true) ? $_POST['dir'] : 'content';
    $file = $_FILES['file'] ?? $_FILES['image'] ?? $_FILES['video'] ?? null;
    if (!$file) fail('No file uploaded', 422);

    $mime = mime_content_type($file['tmp_name'] ?? '') ?: '';
    if (str_starts_with($mime, 'video/')) {
        $name = Upload::video($file, 'shorts');
        if (!$name) fail('Video upload failed (MP4/WebM, <=100MB)', 422);
        out(['success' => true, 'url' => Helper::publicUrl('uploads/shorts/' . $name), 'filename' => $name, 'type' => 'video']);
    }
    $name = Upload::image($file, $dir);
    if (!$name) fail('Image upload failed (JPG/PNG/WebP, <=5MB)', 422);
    out(['success' => true, 'url' => Helper::publicUrl('uploads/' . $dir . '/' . $name), 'filename' => $name, 'type' => 'image']);
}

/* ======================= AI ======================= */
if ($r0 === 'ai' && $r1 === 'generate' && $method === 'POST') {
    requireRole('super_admin','admin','manager','editor','reporter');
    $topic = trim((string) inp($body, 'topic', ''));
    if ($topic === '') fail('Topic is required', 422);
    try {
        $result = XaiWriter::generateArticle($topic, [
            'tone' => (string) inp($body, 'tone', 'neutral'),
            'length' => (string) inp($body, 'length', 'medium'),
            'category' => (string) inp($body, 'category', ''),
        ]);
        out(['success' => true, 'draft' => $result]);
    } catch (Throwable $e) {
        fail($e->getMessage(), 502);
    }
}

fail('Endpoint not found: ' . $method . ' ' . $path, 404);

} catch (Throwable $e) {
    $errorId = bin2hex(random_bytes(6));
    error_log(sprintf(
        'Mobile API [%s] %s %s: %s',
        $errorId,
        $method,
        $path,
        $e->getMessage(),
    ));
    fail(DEBUG ? $e->getMessage() : "Server error. Reference: $errorId", 500);
}
