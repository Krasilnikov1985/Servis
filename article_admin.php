<?php
/**
 * article_admin.php — панель редакции журнала
 */

if (isset($_GET['logout'])) {
    session_start();
    $_SESSION = [];
    session_destroy();
    header('Location: article_admin.php');
    exit;
}

session_start();
date_default_timezone_set('Europe/Moscow');

const ADMIN_PASSWORD = 'admin2025';

const ARTICLES_CSV = __DIR__ . '/articles.csv';
const GRADES_CSV   = __DIR__ . '/article_grades.csv';
const UPLOAD_DIR   = __DIR__ . '/uploads/articles/';

const ARTICLE_HEADERS = ['id', 'timestamp', 'fullname', 'email', 'organization', 'location', 'title', 'topic',
                         'abstract', 'keywords', 'references', 'cover_letter', 'filename', 'checks', 'status'];
const GRADE_HEADERS   = ['article_id', 'reviewer', 'score', 'decision', 'comment', 'graded_at', 'status'];

const DECISIONS = [
    'accept' => 'Принять',
    'minor'  => 'Минорная доработка',
    'major'  => 'Существенная доработка',
    'reject' => 'Отклонить',
];
const STATUSES = ['pending' => 'Ожидает', 'graded' => 'Оценено', 'rejected' => 'Отклонено'];
const TOPICS = [
    'digital' => 'Цифровые технологии в образовании', 'pe' => 'Физическое воспитание', 'sport' => 'Спортивная подготовка',
    'health' => 'Питание и здоровье', 'psy' => 'Психология и педагогика', 'olymp' => 'Олимпийское движение и право',
    'tourism' => 'Туризм и рекреация', 'other' => 'Другое / междисциплинарное',
];
const CHECK_NAMES = [
    'volume' => 'Объём', 'structure' => 'Структура', 'data' => 'Данные', 'original' => 'Оригинальность',
    'anonymous' => 'Анонимность', 'refs' => 'Ссылки', 'template' => 'Шаблон', 'ethics' => 'Этика',
];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------- CSV ---------- */
function parse_csv($fp, array $headers) {
    $rows = [];
    rewind($fp);
    fgetcsv($fp, 0, ',', '"', '\\');
    while (($r = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
        if ($r === [null]) continue;
        if (count($r) !== count($headers)) continue;
        $rows[] = array_combine($headers, $r);
    }
    return $rows;
}

function read_csv($file, array $headers) {
    if (!file_exists($file)) return [];
    $fp = fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $rows = parse_csv($fp, $headers);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $rows;
}

function update_csv($file, array $headers, callable $fn) {
    $fp = fopen($file, 'c+');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    $rows = $fn(parse_csv($fp, $headers));
    ftruncate($fp, 0);
    rewind($fp);
    fputcsv($fp, $headers, ',', '"', '\\');
    foreach ($rows as $r) {
        $line = [];
        foreach ($headers as $k) $line[] = $r[$k] ?? '';
        fputcsv($fp, $line, ',', '"', '\\');
    }
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function find_article($id) {
    foreach (read_csv(ARTICLES_CSV, ARTICLE_HEADERS) as $a) {
        if ($a['id'] === $id) return $a;
    }
    return null;
}

function flash($msg, $type = 'ok') { $_SESSION['flash'] = [$msg, $type]; }

function back($filter) {
    $f = array_key_exists($filter, STATUSES) ? $filter : 'all';
    header('Location: article_admin.php?filter=' . urlencode($f));
    exit;
}

/* ---------- авторизация ---------- */
$loginError = '';
if (empty($_SESSION['editor_logged'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (hash_equals(ADMIN_PASSWORD, (string)$_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['editor_logged'] = true;
            header('Location: article_admin.php');
            exit;
        }
        usleep(500000);
        $loginError = 'Неверный пароль';
    }
    ?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход в панель редакции — Горизонты: Диалог наук</title>
<link href="css/style.css" rel="stylesheet">
<style>
  body, a, button, input { cursor: auto !important; } a, button { cursor: pointer !important; }
  .login { max-width: 440px; margin: 12vh auto 0; border: 1px solid var(--line); background: var(--surface); padding: 40px 36px;
           display: flex; flex-direction: column; gap: 18px; position: relative; z-index: 1; }
  .login h1 { font-size: 1.8rem; letter-spacing: -.02em; }
  .login input { width: 100%; background: var(--bg2); color: var(--text); border: 1px solid var(--line2); padding: 14px 16px;
                 font-family: var(--f-mono); font-size: 14px; outline: none; }
  .login input:focus { border-color: var(--cyan); }
  .login .btn { justify-content: center; width: 100%; cursor: pointer; }
  .login .err { color: var(--magenta); font-family: var(--f-mono); font-size: 12px; }
  .login .back { font-family: var(--f-mono); font-size: 11px; color: var(--muted); text-align: center; }
  .login .back a:hover { color: var(--cyan); }
</style>
</head>
<body>
<div class="container">
  <form class="login" method="post" action="article_admin.php">
    <a class="logo" href="index.html"><span class="logo-mark">Г</span><span class="logo-txt"><b>ГОРИЗОНТЫ: ДИАЛОГ НАУК</b><small>редакционная система</small></span></a>
    <div class="sec-meta"><b>EDITOR</b> · доступ ограничен</div>
    <h1>Панель редакции</h1>
    <input type="password" name="password" placeholder="Пароль редакции" required autofocus autocomplete="current-password">
    <?php if ($loginError): ?><div class="err">✕ <?= h($loginError) ?></div><?php endif; ?>
    <button class="btn btn-solid" type="submit"><span>Войти</span></button>
    <div class="back"><a href="index.html">← вернуться на сайт журнала</a></div>
  </form>
</div>
</body>
</html>
    <?php
    exit;
}

/* ---------- скачивание файла ---------- */
if (isset($_GET['download'])) {
    $a = find_article((string)$_GET['download']);
    $path = $a ? UPLOAD_DIR . basename($a['filename']) : '';
    if (!$a || !is_file($path)) {
        http_response_code(404);
        exit('Файл не найден');
    }
    $types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'rtf' => 'application/rtf',
              'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              'tex' => 'text/plain; charset=UTF-8', 'txt' => 'text/plain; charset=UTF-8'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

/* ---------- действия ---------- */
$filter = $_GET['filter'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (string)($_POST['article_id'] ?? '');
    $article = $id !== '' ? find_article($id) : null;

    if (!$article) {
        flash('Статья не найдена — возможно, она уже удалена.', 'warn');
        back($filter);
    }

    if ($action === 'grade') {
        $score    = filter_var($_POST['score'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        $decision = (string)($_POST['decision'] ?? '');
        $reviewer = trim((string)($_POST['reviewer'] ?? ''));
        $comment  = trim(str_replace("\r\n", "\n", (string)($_POST['comment'] ?? '')));

        if ($score === false || !isset(DECISIONS[$decision]) || $reviewer === '' || mb_strlen($reviewer) > 120 || mb_strlen($comment) > 4000) {
            flash('Оценка не сохранена: укажите оценку 0–100, решение и рецензента.', 'warn');
            back($filter);
        }

        $status = $decision === 'reject' ? 'rejected' : 'graded';
        $grade = [
            'article_id' => $id, 'reviewer' => $reviewer, 'score' => (string)$score, 'decision' => $decision,
            'comment' => $comment, 'graded_at' => date('Y-m-d H:i:s'), 'status' => $status,
        ];

        update_csv(GRADES_CSV, GRADE_HEADERS, function ($rows) use ($id, $grade) {
            $found = false;
            foreach ($rows as $i => $r) {
                if ($r['article_id'] === $id) {
                    if ($found) { unset($rows[$i]); continue; }
                    $rows[$i] = $grade;
                    $found = true;
                }
            }
            if (!$found) $rows[] = $grade;
            return array_values($rows);
        });

        update_csv(ARTICLES_CSV, ARTICLE_HEADERS, function ($rows) use ($id, $status) {
            foreach ($rows as $i => $r) {
                if ($r['id'] === $id) $rows[$i]['status'] = $status;
            }
            return $rows;
        });

        flash('Оценка сохранена: «' . $article['title'] . '» — ' . $score . '/100, ' . DECISIONS[$decision] . '.');
        back($filter);
    }

    if ($action === 'delete') {
        $path = UPLOAD_DIR . basename($article['filename']);
        $fileGone = !is_file($path) || unlink($path);

        update_csv(ARTICLES_CSV, ARTICLE_HEADERS, function ($rows) use ($id) {
            return array_values(array_filter($rows, function ($r) use ($id) { return $r['id'] !== $id; }));
        });

        if (file_exists(GRADES_CSV)) {
            update_csv(GRADES_CSV, GRADE_HEADERS, function ($rows) use ($id) {
                return array_values(array_filter($rows, function ($r) use ($id) { return $r['article_id'] !== $id; }));
            });
        }

        flash('Статья «' . $article['title'] . '» удалена' . ($fileGone ? ' вместе с файлом.' : ', но файл удалить не удалось.'),
              $fileGone ? 'ok' : 'warn');
        back($filter);
    }

    flash('Неизвестное действие.', 'warn');
    back($filter);
}

/* ---------- данные для отображения ---------- */
$articles = read_csv(ARTICLES_CSV, ARTICLE_HEADERS);
$grades = [];
foreach (read_csv(GRADES_CSV, GRADE_HEADERS) as $g) $grades[$g['article_id']] = $g;

$stats = ['all' => count($articles), 'pending' => 0, 'graded' => 0, 'rejected' => 0];
foreach ($articles as $a) {
    $s = isset(STATUSES[$a['status']]) ? $a['status'] : 'pending';
    $stats[$s]++;
}

if (!array_key_exists($filter, STATUSES)) $filter = 'all';
$list = array_filter($articles, function ($a) use ($filter) { return $filter === 'all' || $a['status'] === $filter; });
usort($list, function ($a, $b) { return strcmp($b['timestamp'] . $b['id'], $a['timestamp'] . $a['id']); });

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function fsize($path) {
    if (!is_file($path)) return 'файл отсутствует';
    $b = filesize($path);
    return $b >= 1048576 ? round($b / 1048576, 2) . ' МБ' : max(1, round($b / 1024)) . ' КБ';
}
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель редакции — Горизонты: Диалог наук</title>
<link href="css/style.css" rel="stylesheet">
<style>
  body, a, button, input, label, textarea, select, summary { cursor: auto !important; }
  a, button, select, summary { cursor: pointer !important; }

  .svc-top { border-bottom: 1px solid var(--line); position: relative; z-index: 5; background: var(--bg); }
  .svc-top .container { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-top: 16px; padding-bottom: 16px; flex-wrap: wrap; }
  .svc-top nav { display: flex; gap: 8px; flex-wrap: wrap; }
  .svc-main { padding: 48px 0 90px; position: relative; z-index: 1; }

  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; background: var(--line); border: 1px solid var(--line); margin-bottom: 26px; }
  @media (max-width: 760px) { .stats { grid-template-columns: repeat(2, 1fr); } }
  .stat { background: var(--bg2); padding: 20px 22px; }
  .stat .w-label { font-family: var(--f-mono); font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
  .stat .w-num { font-family: var(--f-disp); font-weight: 300; font-size: clamp(28px, 4vw, 44px); line-height: 1.2; margin-top: 6px; }
  .stat.p .w-num { color: var(--amber); } .stat.g .w-num { color: #41FFB0; } .stat.r .w-num { color: var(--magenta); }

  .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; }
  .filters .chip.on { background: var(--cyan); border-color: var(--cyan); color: #0A1128; }

  .flash { border: 1px solid var(--line2); border-left: 3px solid #41FFB0; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; background: var(--surface); }
  .flash.warn { border-left-color: var(--amber); }

  .card { border: 1px solid var(--line); background: var(--surface); display: grid; grid-template-columns: 1fr 340px; margin-bottom: 18px; }
  @media (max-width: 1000px) { .card { grid-template-columns: 1fr; } }
  .card-main { padding: 22px 24px; display: flex; flex-direction: column; gap: 12px; min-width: 0; }
  .card-side { border-left: 1px solid var(--line); padding: 22px 24px; background: var(--bg2); display: flex; flex-direction: column; gap: 10px; }
  @media (max-width: 1000px) { .card-side { border-left: 0; border-top: 1px solid var(--line); } }

  .card-top { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-family: var(--f-mono); font-size: 11px; color: var(--muted); letter-spacing: .06em; }
  .card h3 { font-size: 1.25rem; line-height: 1.35; letter-spacing: -.01em; }
  .badge { font-family: var(--f-mono); font-size: 10px; letter-spacing: .1em; text-transform: uppercase; padding: 4px 10px; border: 1px solid; }
  .badge.pending { color: var(--amber); } .badge.graded { color: #41FFB0; } .badge.rejected { color: var(--magenta); }
  .kv { display: grid; grid-template-columns: 150px 1fr; gap: 4px 14px; font-size: 13.5px; }
  .kv dt { font-family: var(--f-mono); font-size: 10.5px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; padding-top: 3px; }
  .kv dd { word-break: break-word; }
  @media (max-width: 600px) { .kv { grid-template-columns: 1fr; } }
  .abs { font-size: 14px; color: var(--muted); line-height: 1.7; white-space: pre-line; }
  .tags { display: flex; flex-wrap: wrap; gap: 6px; }
  .tag { font-family: var(--f-mono); font-size: 10.5px; padding: 3px 9px; border: 1px solid var(--line2); color: var(--muted); }
  .tag.ok { color: #41FFB0; border-color: color-mix(in srgb, #41FFB0 40%, transparent); }
  details { border: 1px solid var(--line); }
  details summary { padding: 10px 14px; font-family: var(--f-mono); font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
  details summary:hover { color: var(--cyan); }
  details div { padding: 0 14px 14px; font-size: 13.5px; white-space: pre-line; color: var(--muted); }
  .file-link { display: inline-flex; gap: 8px; align-items: center; color: var(--cyan); font-family: var(--f-mono); font-size: 12px; word-break: break-all; }
  .file-link:hover { text-decoration: underline; }

  .grade-now { border: 1px dashed var(--line2); padding: 12px 14px; font-size: 13px; }
  .grade-now b { font-family: var(--f-disp); font-weight: 300; font-size: 26px; color: var(--amber); }
  .grade-now .c { color: var(--muted); white-space: pre-line; margin-top: 6px; }
  .card-side label { font-family: var(--f-mono); font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
  .card-side input, .card-side select, .card-side textarea {
      width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--line2); padding: 10px 12px; outline: none;
      font-family: var(--f-body); font-size: 13.5px; margin-top: 5px; }
  .card-side input:focus, .card-side select:focus, .card-side textarea:focus { border-color: var(--cyan); }
  .card-side select option { background: var(--bg2); }
  .card-side .btn { justify-content: center; width: 100%; cursor: pointer; padding: 12px 18px; }
  .btn-danger { color: var(--magenta); border-color: color-mix(in srgb, var(--magenta) 50%, transparent); background: none; }
  .btn-danger:hover { background: color-mix(in srgb, var(--magenta) 12%, transparent); transform: translateY(-2px); }
  .empty { border: 1px dashed var(--line2); padding: 50px 20px; text-align: center; color: var(--muted); font-family: var(--f-mono); font-size: 13px; }
</style>
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 2h9l4 4v16H6z"/><path d="M14 2v5h5"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12H4M11 5l-7 7 7 7"/></symbol>
</svg>

<header class="svc-top">
  <div class="container">
    <a class="logo" href="index.html">
      <span class="logo-mark">Г</span>
      <span class="logo-txt"><b>ГОРИЗОНТЫ: ДИАЛОГ НАУК</b><small>панель редакции</small></span>
    </a>
    <nav>
      <a class="chip" href="index.html"><svg><use href="#i-back"/></svg>Сайт журнала</a>
      <a class="chip" href="article.php">Форма подачи</a>
      <a class="chip" href="?logout">Выйти</a>
    </nav>
  </div>
</header>

<main class="svc-main">
  <div class="container">
    <div class="sec-head">
      <div class="sec-meta"><b>EDITOR</b><br>рецензирование рукописей<br>двойное слепое</div>
      <h1 class="sec-title">Панель редакции</h1>
    </div>

    <div class="stats">
      <div class="stat"><div class="w-label">Всего статей</div><div class="w-num"><?= $stats['all'] ?></div></div>
      <div class="stat p"><div class="w-label">Ожидают</div><div class="w-num"><?= $stats['pending'] ?></div></div>
      <div class="stat g"><div class="w-label">Оценено</div><div class="w-num"><?= $stats['graded'] ?></div></div>
      <div class="stat r"><div class="w-label">Отклонено</div><div class="w-num"><?= $stats['rejected'] ?></div></div>
    </div>

    <div class="filters">
      <?php foreach (['all' => 'Все'] + STATUSES as $key => $name): ?>
        <a class="chip <?= $filter === $key ? 'on' : '' ?>" href="?filter=<?= $key ?>"><?= h($name) ?> · <?= $stats[$key] ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($flash): ?>
      <div class="flash <?= $flash[1] === 'warn' ? 'warn' : '' ?>"><?= h($flash[0]) ?></div>
    <?php endif; ?>

    <?php if (!$list): ?>
      <div class="empty">Статей с таким статусом нет</div>
    <?php endif; ?>

    <?php foreach ($list as $a):
        $id = $a['id'];
        $g = $grades[$id] ?? null;
        $st = isset(STATUSES[$a['status']]) ? $a['status'] : 'pending';
        $checks = array_filter(explode('|', $a['checks']));
        $action = 'article_admin.php?filter=' . urlencode($filter);
    ?>
      <article class="card" id="<?= h($id) ?>">
        <div class="card-main">
          <div class="card-top">
            <span class="badge <?= $st ?>"><?= h(STATUSES[$st]) ?></span>
            <span><?= h($a['timestamp']) ?></span>
            <span>·</span>
            <span><?= h($id) ?></span>
          </div>
          <h3><?= h($a['title']) ?></h3>
          <dl class="kv">
            <dt>Автор</dt><dd><?= h($a['fullname']) ?></dd>
            <dt>E-mail</dt><dd><a href="mailto:<?= h($a['email']) ?>" style="color:var(--cyan)"><?= h($a['email']) ?></a></dd>
            <dt>Организация</dt><dd><?= h($a['organization']) ?></dd>
            <dt>Страна / город</dt><dd><?= h($a['location']) ?></dd>
            <dt>Область</dt><dd><?= h(TOPICS[$a['topic']] ?? $a['topic']) ?></dd>
            <dt>Ключевые слова</dt>
            <dd class="tags"><?php foreach (array_filter(array_map('trim', explode(',', $a['keywords']))) as $k): ?><span class="tag"><?= h($k) ?></span><?php endforeach; ?></dd>
            <dt>Подтверждения</dt>
            <dd class="tags"><?php foreach ($checks as $c): ?><span class="tag ok">✓ <?= h(CHECK_NAMES[$c] ?? $c) ?></span><?php endforeach; ?></dd>
            <dt>Файл</dt>
            <dd><a class="file-link" href="?download=<?= urlencode($id) ?>" target="_blank" rel="noopener"><svg style="width:14px;height:14px"><use href="#i-file"/></svg><?= h($a['filename']) ?></a>
                <span class="mono" style="font-size:11px;color:var(--faint)"> · <?= h(fsize(UPLOAD_DIR . basename($a['filename']))) ?></span></dd>
          </dl>
          <div>
            <div class="sec-meta" style="margin-bottom:4px">Аннотация</div>
            <div class="abs"><?= h($a['abstract']) ?></div>
          </div>
          <details>
            <summary>Список литературы</summary>
            <div><?= $a['references'] !== '' ? h($a['references']) : '— не указан —' ?></div>
          </details>
          <details>
            <summary>Сопроводительное письмо</summary>
            <div><?= $a['cover_letter'] !== '' ? h($a['cover_letter']) : '— не приложено —' ?></div>
          </details>
        </div>

        <div class="card-side">
          <?php if ($g): ?>
            <div class="grade-now">
              <div><b><?= h($g['score']) ?></b> / 100 · <?= h(DECISIONS[$g['decision']] ?? $g['decision']) ?></div>
              <div class="mono" style="font-size:11px;color:var(--faint)"><?= h($g['reviewer']) ?> · <?= h($g['graded_at']) ?></div>
              <?php if ($g['comment'] !== ''): ?><div class="c"><?= h($g['comment']) ?></div><?php endif; ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= h($action) ?>">
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="article_id" value="<?= h($id) ?>">
            <div style="display:flex;flex-direction:column;gap:10px">
              <label>Оценка (0–100)
                <input type="number" name="score" min="0" max="100" step="1" required value="<?= h($g['score'] ?? '') ?>">
              </label>
              <label>Решение
                <select name="decision" required>
                  <option value="">— выберите —</option>
                  <?php foreach (DECISIONS as $k => $n): ?>
                    <option value="<?= $k ?>" <?= ($g['decision'] ?? '') === $k ? 'selected' : '' ?>><?= $k ?> — <?= h($n) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Рецензент
                <input type="text" name="reviewer" required maxlength="120" value="<?= h($g['reviewer'] ?? '') ?>" placeholder="ФИО рецензента">
              </label>
              <label>Комментарий
                <textarea name="comment" rows="3" maxlength="4000" placeholder="Замечания для автора"><?= h($g['comment'] ?? '') ?></textarea>
              </label>
              <button class="btn btn-solid" type="submit"><span><?= $g ? 'Обновить оценку' : 'Сохранить оценку' ?></span></button>
            </div>
          </form>

          <form method="post" action="<?= h($action) ?>" class="del-form">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="article_id" value="<?= h($id) ?>">
            <button class="btn btn-danger" type="submit"><span>Удалить статью</span></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</main>

<script>
  document.querySelectorAll('.del-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm('Удалить статью безвозвратно? Будут удалены файл рукописи и все оценки.')) e.preventDefault();
    });
  });
</script>
</body>
</html>