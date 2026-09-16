<?php
declare(strict_types=1);

require_once __DIR__ . '/visitor-symbols.php';


function fbo_visitor_identity(): string
{
    $candidates = array_unique(array_merge([(string) ($_SESSION['fbo_last_blog_word'] ?? '')], array_map(
        static fn($key) => substr($key, strlen('fbo_admin_auth_')),
        array_filter(array_keys($_SESSION), static fn($key) => str_starts_with($key, 'fbo_admin_auth_'))
    )));
    foreach ($candidates as $word) {
        if (preg_match('/\A[a-z0-9_-]{1,24}\z/', $word)
            && !empty($_SESSION['fbo_admin_auth_' . $word])
            && is_file(dirname(__DIR__) . '/multi-tenant/blogs/' . $word . '/backend/.auth.json')) return $word;
    }
    return '';
}

$visitorIdentity = fbo_visitor_identity();
$visitorLoginUrl = '/';
foreach ([$_COOKIE[MT_REMEMBERED_BLOG_COOKIE] ?? '', $_SESSION['fbo_last_blog_word'] ?? ''] as $rememberedWord) {
    if (is_string($rememberedWord) && preg_match('/\A[a-z0-9_-]{1,24}\z/', $rememberedWord)
        && is_file(dirname(__DIR__) . '/multi-tenant/blogs/' . $rememberedWord . '/backend/.auth.json')) {
        $visitorLoginUrl = '/blog/' . rawurlencode($rememberedWord) . '?compose=1';
        break;
    }
}
$interactionKey = hash('sha256', blog_root());
$_SESSION['fbo_interaction_csrf'] ??= bin2hex(random_bytes(32));
$interactionToken = $_SESSION['fbo_interaction_csrf'];
$interactionError = '';
$interactionPath = backend_dir_path() . '/visitor-interactions.php';
$visitorEntered = $adminAuthed || !empty($_SESSION['fbo_entered'][$interactionKey]);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['visitor_action'])) {
    try {
        if (!is_string($_POST['interaction_token'] ?? null) || !hash_equals($interactionToken, $_POST['interaction_token'])) {
            http_response_code(403);
            throw new RuntimeException('Please reload the page and try again.');
        }
        $action = $_POST['visitor_action'];
        if ($action === 'symbols' && !$visitorEntered) {
            $choices = $_POST['symbols'] ?? [];
            if (!is_array($choices) || count($choices) !== 3) throw new RuntimeException('Choose exactly three symbols.');
            foreach ($choices as $choice) {
                if (!is_string($choice) || !ctype_digit($choice) || !array_key_exists((int) $choice, FBO_SYMBOLS)) throw new RuntimeException('Choose three symbols from the selection.');
            }
            $choices = array_map('intval', $choices);
            fbo_interactions_append($interactionPath, ['type' => 'symbols', 'symbols' => $choices, 'author' => $visitorIdentity, 'time' => time()]);
            $_SESSION['fbo_entered'][$interactionKey] = true;
        } elseif ($action === 'note' || $action === 'comment') {
            if ($visitorIdentity === '' || !$visitorEntered || $adminAuthed) {
                http_response_code(403);
                throw new RuntimeException('Log in to your blog and leave three symbols first.');
            }
            $body = $_POST['note'] ?? $_POST['comment'] ?? '';
            if (!is_string($body) || trim($body) === '' || mb_strlen(trim($body)) > 2000) throw new RuntimeException('Write a note of 1–2000 characters.');
            $notePostId = $_POST['note_post_id'] ?? '';
            $notePost = null;
            foreach (load_posts() as $candidate) {
                if (is_string($notePostId) && $notePostId !== '' && (string) ($candidate['id'] ?? '') === $notePostId) { $notePost = $candidate; break; }
            }
            if ($notePost === null) throw new RuntimeException('This post is no longer available. Open a post to leave a note.');
            if (time() - (int) ($_SESSION['fbo_comment_time'][$interactionKey] ?? 0) < 30) throw new RuntimeException('Please wait a moment before leaving another note.');
            $notePostLabel = ucfirst((string) ($notePost['type'] ?? 'Post')) . ' · ' . date('d.m.Y H:i', (int) ($notePost['timestamp'] ?? 0));
            if (!empty($notePost['text'])) $notePostLabel .= ' · ' . mb_substr((string) $notePost['text'], 0, 80);
            fbo_interactions_append($interactionPath, ['type' => 'note', 'body' => trim($body), 'author' => $visitorIdentity, 'time' => time(), 'post_id' => $notePostId, 'post_label' => $notePostLabel]);
            $_SESSION['fbo_comment_time'][$interactionKey] = time();
            $_SESSION['fbo_comment_sent'][$interactionKey] = true;
        } else { throw new RuntimeException('This action is not available.'); }
        // Keep the requested post, page and view after entering.
        $query = http_build_query($_GET);
        header('Location: ' . blog_self_url() . ($query !== '' ? '?' . $query : ''), true, 303);
        exit;
    } catch (RuntimeException $error) { $interactionError = $error->getMessage(); }
}
$interactionRows = fbo_interactions_read($interactionPath);
if (!$interactionRows) {
    fbo_interactions_append($interactionPath, ['type' => 'initial_symbols', 'symbols' => array_rand(FBO_SYMBOLS, 3), 'time' => time()]);
    $interactionRows = fbo_interactions_read($interactionPath);
}
$symbolContributions = array_values(array_filter($interactionRows, static fn($row) => ($row['type'] ?? '') === 'symbols'));
$symbolStates = array_values(array_filter($interactionRows, static fn($row) => in_array($row['type'] ?? '', ['symbols', 'initial_symbols'], true)));
$currentSymbols = $symbolStates ? $symbolStates[count($symbolStates) - 1]['symbols'] : array_rand(FBO_SYMBOLS, 3);
$symbolHistory = $adminAuthed ? array_reverse(array_slice($symbolContributions, -10)) : [];
$privateComments = $adminAuthed ? array_reverse(array_values(array_filter($interactionRows, static fn($row) => in_array($row['type'] ?? '', ['comment', 'note'], true)))) : [];
unset($interactionRows);
$commentSent = !empty($_SESSION['fbo_comment_sent'][$interactionKey]);
unset($_SESSION['fbo_comment_sent'][$interactionKey]);
$visitorGate = !$visitorEntered;
