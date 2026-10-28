<?php
/**
 * article_upload.php — обработчик формы подачи статьи
 */

const ARTICLES_CSV = __DIR__ . '/articles.csv';
const GRADES_CSV   = __DIR__ . '/article_grades.csv';
const UPLOAD_DIR   = __DIR__ . '/uploads/articles/';
const MAX_BYTES    = 10 * 1024 * 1024;

const ARTICLE_HEADERS = ['id', 'timestamp', 'fullname', 'email', 'organization', 'location', 'title', 'topic',
                         'abstract', 'keywords', 'references', 'cover_letter', 'filename', 'checks', 'status'];
const GRADE_HEADERS   = ['article_id', 'reviewer', 'score', 'decision', 'comment', 'graded_at', 'status'];

const ALLOWED = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/cdfv2', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'tex'  => ['text/plain', 'text/x-tex', 'application/x-tex', 'text/x-latex'],
    'txt'  => ['text/plain'],
    'rtf'  => ['text/rtf', 'application/rtf', 'text/plain'],
];

const TOPICS = ['digital', 'pe', 'sport', 'health', 'psy', 'olymp', 'tourism', 'other'];
const CHECKS = ['volume', 'structure', 'data', 'original', 'anonymous', 'refs', 'template'];

date_default_timezone_set('Europe/Moscow');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function len($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }

function field($name) {
    $v = $_POST[$name] ?? '';
    if (!is_string($v)) return '';
    $v = str_replace("\r\n", "\n", $v);
    $v = preg_replace('/[^\P{C}\n\t]/u', '', $v);
    return trim((string)$v);
}

function safe_name($s) {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    $s = trim($s, '_');
    return substr($s !== '' ? $s : 'author', 0, 40);
}

function fail(array $errors, $code = 400) {
    http_response_code($code);
    page('Заявка не принята', '<div class="sec-meta"><b>ОШИБКА</b> · проверьте данные</div>'
        . '<h1 class="sec-title" style="font-size:clamp(1.8rem,3.4vw,2.6rem)">Заявка не принята</h1>'
        . '<ul class="err">' . implode('', array_map(function ($e) { return '<li>' . h($e) . '</li>'; }, $errors)) . '</ul>'
        . '<p class="note">Введённые данные сохранятся в форме, если вернуться кнопкой «Назад».</p>'
        . '<div class="actions"><a class="btn btn-solid" href="javascript:history.back()"><span>Назад к форме</span></a>'
        . '<a class="btn btn-line" href="article.php"><span>Открыть форму заново</span></a></div>');
    exit;
}

function page($title, $content) {
    echo '<!DOCTYPE html><html lang="ru" data-theme="dark"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . h($title) . ' — Горизонты: Диалог наук</title>'
       . '<link href="css/style.css" rel="stylesheet"><style>'
       . 'body,a,button{cursor:auto!important}a,button{cursor:pointer!important}'
       . '.box{max-width:760px;margin:80px auto;border:1px solid var(--line);background:var(--surface);padding:44px 40px;display:flex;flex-direction:column;gap:18px;position:relative;z-index:1}'
       . '.err{list-style:none;display:flex;flex-direction:column;gap:8px}.err li{border-left:2px solid var(--magenta);padding:8px 14px;background:var(--surface2);font-size:14px}'
       . '.note{color:var(--muted);font-size:14px}.actions{display:flex;gap:12px;flex-wrap:wrap}'
       . '.idbox{font-family:var(--f-mono);font-size:clamp(14px,2.2vw,20px);color:var(--amber);border:1px dashed var(--amber);padding:16px 20px;word-break:break-all}'
       . '.kv{display:grid;grid-template-columns:160px 1fr;gap:6px 16px;font-size:14px}.kv dt{font-family:var(--f-mono);font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;padding-top:3px}'
       . '@media(max-width:600px){.box{margin:30px 16px;padding:28px 20px}.kv{grid-template-columns:1fr}}'
       . '</style></head><body><div class="container"><div class="box">' . $content . '</div></div></body></html>';
}

function ensure_csv($file, array $headers) {
    if (!file_exists($file)) {
        $fp = fopen($file, 'c');
        if ($fp && flock($fp, LOCK_EX)) {
            if (filesize($file) === 0) fputcsv($fp, $headers, ',', '"', '\\');
            flock($fp, LOCK_UN);
        }
        if ($fp) fclose($fp);
    }
}

/* ---------- 1. Метод ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: article.php');
    exit;
}
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    fail(['Размер запроса превышает допустимый: файл рукописи должен быть не больше 10 МБ.'], 413);
}

/* ---------- 2. Валидация ---------- */
$data = [
    'fullname'     => field('fullname'),
    'email'        => field('email'),
    'organization' => field('organization'),
    'location'     => field('location'),
    'title'        => field('title'),
    'topic'        => field('topic'),
    'abstract'     => field('abstract'),
    'keywords'     => field('keywords'),
    'references'   => field('references'),
    'cover_letter' => field('cover_letter'),
];

$labels = [
    'fullname' => 'ФИО', 'email' => 'E-mail', 'organization' => 'Организация', 'location' => 'Страна / город',
    'title' => 'Название статьи', 'topic' => 'Область исследований', 'abstract' => 'Аннотация', 'keywords' => 'Ключевые слова',
];
$limits = ['fullname' => 150, 'email' => 120, 'organization' => 200, 'location' => 120, 'title' => 300,
           'abstract' => 4000, 'keywords' => 300, 'references' => 8000, 'cover_letter' => 4000];

$errors = [];
foreach ($labels as $key => $label) {
    if ($data[$key] === '') $errors[] = "Не заполнено обязательное поле «{$label}».";
}
foreach ($limits as $key => $max) {
    if (len($data[$key]) > $max) $errors[] = "Поле «" . ($labels[$key] ?? $key) . "» длиннее {$max} символов.";
}
if ($data['fullname'] !== '' && !preg_match("/^[\p{L}'\-]+(\s+[\p{L}'.\-]+)+$/u", $data['fullname'])) {
    $errors[] = 'ФИО должно содержать не менее двух слов и только буквы, дефис или апостроф.';
}
if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Некорректный адрес e-mail.';
}
if ($data['topic'] !== '' && !in_array($data['topic'], TOPICS, true)) {
    $errors[] = 'Выбрана несуществующая область исследований.';
}
if ($data['title'] !== '' && len($data['title']) < 10) {
    $errors[] = 'Название статьи слишком короткое (минимум 10 символов).';
}
if ($data['abstract'] !== '' && len($data['abstract']) < 100) {
    $errors[] = 'Аннотация слишком короткая (минимум 100 символов).';
}
if ($data['keywords'] !== '') {
    $kw = array_values(array_filter(array_map('trim', explode(',', $data['keywords'])), 'strlen'));
    if (count($kw) < 3 || count($kw) > 10) {
        $errors[] = 'Укажите от 3 до 10 ключевых слов через запятую.';
    }
    $data['keywords'] = implode(', ', $kw);
}

$checks = $_POST['checks'] ?? [];
$checks = is_array($checks) ? array_values(array_intersect(CHECKS, $checks)) : [];
$missing = array_diff(CHECKS, $checks);
if ($missing) {
    $errors[] = 'Не подтверждены требования к рукописи: ' . count($missing) . ' из ' . count(CHECKS) . ' пунктов не отмечены.';
}
if (($_POST['ethics_agree'] ?? '') !== '1') {
    $errors[] = 'Необходимо согласие с этической политикой журнала.';
}

/* ---------- 3. Файл ---------- */
$file = $_FILES['manuscript'] ?? null;
$ext = '';
if (!$file || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = 'Не прикреплён файл рукописи.';
} elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    $errors[] = 'Файл превышает 10 МБ.';
} elseif ($file['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Ошибка загрузки файла (код ' . (int)$file['error'] . '). Попробуйте ещё раз.';
} elseif (!is_uploaded_file($file['tmp_name'])) {
    $errors[] = 'Файл получен некорректно.';
} else {
    $size = filesize($file['tmp_name']);
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($size === 0) {
        $errors[] = 'Файл пустой.';
    } elseif ($size > MAX_BYTES) {
        $errors[] = 'Файл превышает 10 МБ (' . round($size / 1048576, 2) . ' МБ).';
    }
    if (!isset(ALLOWED[$ext])) {
        $errors[] = 'Неподдерживаемый формат файла «.' . $ext . '». Допустимы: PDF, DOC, DOCX, TEX, TXT, RTF.';
    } elseif (class_exists('finfo')) {
        $mime = strtolower((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '');
        if (!in_array($mime, ALLOWED[$ext], true)) {
            $errors[] = 'Содержимое файла не соответствует расширению .' . $ext . ' (определён тип ' . $mime . ').';
        }
    }
}

if ($errors) fail($errors);

/* ---------- 4. Сохранение файла ---------- */
if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
    fail(['Не удалось создать папку uploads/articles/. Проверьте права на запись.'], 500);
}
if (!file_exists(UPLOAD_DIR . '.htaccess')) {
    file_put_contents(UPLOAD_DIR . '.htaccess',
        "# Рукописи доступны только через article_admin.php\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n");
}

do {
    $newName = date('Ymd_His') . '_' . safe_name($data['fullname']) . '_' . random_int(1000, 9999) . '.' . $ext;
} while (file_exists(UPLOAD_DIR . $newName));

if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
    fail(['Не удалось сохранить файл. Проверьте права на папку uploads/articles/.'], 500);
}
@chmod(UPLOAD_DIR . $newName, 0644);

/* ---------- 5. Запись в articles.csv ---------- */
ensure_csv(GRADES_CSV, GRADE_HEADERS);

$id  = uniqid('art_', true);
$now = date('Y-m-d H:i:s');
$row = [
    $id, $now, $data['fullname'], $data['email'], $data['organization'], $data['location'], $data['title'],
    $data['topic'], $data['abstract'], $data['keywords'], $data['references'], $data['cover_letter'],
    $newName, implode('|', array_merge($checks, ['ethics'])), 'pending',
];

$ok = false;
$fp = fopen(ARTICLES_CSV, 'a');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        clearstatcache(true, ARTICLES_CSV);
        if (filesize(ARTICLES_CSV) === 0) {
            fputcsv($fp, ARTICLE_HEADERS, ',', '"', '\\');
        } else {
            // гарантия, что предыдущая строка завершена переводом строки
            $tail = file_get_contents(ARTICLES_CSV);
            if ($tail !== '' && substr($tail, -1) !== "\n") {
                fwrite($fp, "\n");
            }
        }
        $ok = fputcsv($fp, $row, ',', '"', '\\') !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

if (!$ok) {
    @unlink(UPLOAD_DIR . $newName);
    fail(['Не удалось записать заявку в articles.csv. Попробуйте позже.'], 500);
}

/* ---------- 6. Страница успеха ---------- */
page('Статья принята', '<div class="sec-meta"><b>ГОТОВО</b> · заявка зарегистрирована</div>'
    . '<h1 class="sec-title" style="font-size:clamp(1.8rem,3.4vw,2.6rem)">Статья отправлена</h1>'
    . '<p class="note">Спасибо, ' . h($data['fullname']) . '! Рукопись передана в редакцию и ожидает рецензирования. '
    . 'Сохраните номер заявки — он понадобится при переписке с редакцией.</p>'
    . '<div class="idbox">ID: ' . h($id) . '</div>'
    . '<dl class="kv"><dt>Название</dt><dd>' . h($data['title']) . '</dd>'
    . '<dt>E-mail</dt><dd>' . h($data['email']) . '</dd>'
    . '<dt>Файл</dt><dd class="mono" style="font-size:12px">' . h($newName) . '</dd>'
    . '<dt>Дата подачи</dt><dd>' . h($now) . '</dd>'
    . '<dt>Статус</dt><dd>ожидает рецензии</dd></dl>'
    . '<div class="actions"><a class="btn btn-solid" href="index.html"><span>Вернуться в журнал</span></a>'
    . '<a class="btn btn-line" href="article.php"><span>Подать ещё одну статью</span></a></div>');