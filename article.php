<?php
/**
 * article.php — публичная форма подачи научной статьи
 * Журнал «Горизонты: Диалог наук»
 *
 * Форма отправляет данные методом POST (multipart/form-data) в article_upload.php.
 * Здесь только клиентская часть: разметка, стили журнала и JS-проверки.
 * Окончательная проверка всех полей выполняется на сервере в article_upload.php.
 */

// Области исследований — те же ключи, что в js/data.js журнала (TOPICS) + «другое»
$topics = [
    'digital' => 'Цифровые технологии в образовании',
    'pe'      => 'Физическое воспитание',
    'sport'   => 'Спортивная подготовка',
    'health'  => 'Питание и здоровье',
    'psy'     => 'Психология и педагогика',
    'olymp'   => 'Олимпийское движение и право',
    'tourism' => 'Туризм и рекреация',
    'other'   => 'Другое / междисциплинарное исследование',
];

// 7 подтверждений требований к рукописи (см. SEC.08 «Авторам») + согласие с этической политикой
$checks = [
    'volume'    => ['Объём', '40 000–60 000 знаков, аннотация 150–250 слов'],
    'structure' => ['Структура', 'введение, обзор, методы, результаты, выводы'],
    'data'      => ['Данные', 'исходные данные размещены в открытом репозитории'],
    'original'  => ['Оригинальность', 'доля заимствований не более 15 %'],
    'anonymous' => ['Анонимность', 'файл рукописи не содержит сведений об авторах'],
    'refs'      => ['Ссылки', 'список литературы оформлен по ГОСТ или APA 7'],
    'template'  => ['Шаблон', 'рукопись оформлена по шаблону журнала (template.docx / template.tex)'],
];

$maxBytes = 10 * 1024 * 1024; // 10 МБ
?>
<!DOCTYPE html>
<html lang="ru" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0A1128">
<title>Подача статьи — Горизонты: Диалог наук</title>
<!-- Подключаем стили журнала: шрифты, цвета, кнопки .btn / .btn-solid / .btn-line -->
<link href="css/style.css" rel="stylesheet">
<style>
  /* Курсор журнала рисуется скриптом script.js, здесь его нет — возвращаем системный */
  body, a, button, input, label, textarea, select { cursor: auto !important; }
  a, button, label, select, input[type=checkbox], input[type=file] { cursor: pointer !important; }

  .svc-top { border-bottom: 1px solid var(--line); position: relative; z-index: 5; background: var(--bg); }
  .svc-top .container { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-top: 16px; padding-bottom: 16px; flex-wrap: wrap; }
  .svc-top nav { display: flex; gap: 8px; flex-wrap: wrap; }

  .svc-main { padding: 56px 0 90px; position: relative; z-index: 1; }
  .svc-grid { display: grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start; }
  @media (max-width: 1000px) { .svc-grid { grid-template-columns: 1fr; } }

  .fs { border: 1px solid var(--line); background: var(--surface); margin-bottom: 18px; }
  .fs-head { padding: 14px 20px; border-bottom: 1px solid var(--line); font-family: var(--f-mono); font-size: 11px;
             letter-spacing: .16em; text-transform: uppercase; color: var(--muted); display: flex; justify-content: space-between; }
  .fs-head b { color: var(--amber); font-weight: 500; }
  .fs-body { padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .fs-body .wide { grid-column: 1 / -1; }
  @media (max-width: 700px) { .fs-body { grid-template-columns: 1fr; } }

  .f label.lbl { display: block; font-family: var(--f-mono); font-size: 11px; letter-spacing: .08em; text-transform: uppercase;
                 color: var(--muted); margin-bottom: 8px; }
  .f label.lbl .req { color: var(--amber); }
  .f input[type=text], .f input[type=email], .f select, .f textarea {
      width: 100%; background: var(--bg2); color: var(--text); border: 1px solid var(--line2); outline: none;
      padding: 12px 14px; font-family: var(--f-body); font-size: 14px; transition: border-color .2s; border-radius: 0; }
  .f textarea { resize: vertical; min-height: 110px; line-height: 1.6; }
  .f input:focus, .f select:focus, .f textarea:focus { border-color: var(--cyan); }
  .f input:user-invalid, .f textarea:user-invalid, .f select:user-invalid { border-color: var(--magenta); }
  .f .hint { font-family: var(--f-mono); font-size: 10.5px; color: var(--faint); margin-top: 6px; letter-spacing: .04em; }
  .f .hint.warn { color: var(--amber); }
  .f select option { background: var(--bg2); color: var(--text); }

  /* Зона выбора файла — повторяет стиль блока #drop журнала */
  .dropzone { border: 1px dashed var(--line2); padding: 30px 20px; text-align: center; display: flex; flex-direction: column;
              align-items: center; gap: 10px; transition: .25s; position: relative; }
  .dropzone.over { border-color: var(--amber); background: color-mix(in srgb, var(--amber) 7%, transparent); }
  .dropzone svg { width: 26px; height: 26px; color: var(--muted); }
  .dropzone .d1 { font-family: var(--f-mono); font-size: 12px; letter-spacing: .08em; }
  .dropzone .d1 b { color: var(--amber); font-weight: 500; }
  .dropzone .d2 { font-family: var(--f-mono); font-size: 10px; color: var(--faint); letter-spacing: .06em; }
  .dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; }
  .file-info { font-family: var(--f-mono); font-size: 12px; color: var(--cyan); word-break: break-all; }
  .file-info.bad { color: var(--magenta); }

  .chk { display: grid; grid-template-columns: 22px 1fr; gap: 12px; padding: 12px 20px; border-bottom: 1px solid var(--line);
         font-size: 13.5px; color: var(--muted); align-items: start; }
  .chk:last-child { border-bottom: 0; }
  .chk input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--amber); }
  .chk b { color: var(--text); font-weight: 600; }
  .chk.ethics { background: color-mix(in srgb, var(--amber) 6%, transparent); }

  .side { position: sticky; top: 20px; }
  .side .guide-block p { padding: 14px 20px; font-size: 13px; color: var(--muted); border-bottom: 1px solid var(--line); }
  .side .guide-block p:last-child { border-bottom: 0; }
  .side .guide-block a { color: var(--cyan); }

  .actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 8px; }
  .btn { cursor: pointer; background: none; color: var(--text); }
  .btn-solid { color: #0A1128; background: var(--amber); }
  #sendBar { height: 3px; background: var(--line); position: relative; overflow: hidden; display: none; margin-top: 14px; }
  #sendBar i { position: absolute; inset: 0 auto 0 0; width: 35%; background: var(--grad-data); animation: sendmove 1.1s linear infinite; }
  @keyframes sendmove { from { left: -35%; } to { left: 100%; } }
  #sendStat { font-family: var(--f-mono); font-size: 11px; color: var(--muted); display: none; margin-top: 8px; }
  .sending #sendBar, .sending #sendStat { display: block; }
  .sending .fs { opacity: .55; pointer-events: none; }
</style>
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 12h16M13 5l7 7-7 7"/></symbol>
  <symbol id="i-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 15V3M7 8l5-5 5 5M4 20h16"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12H4M11 5l-7 7 7 7"/></symbol>
</svg>

<header class="svc-top">
  <div class="container">
    <a class="logo" href="index.html">
      <span class="logo-mark">Г</span>
      <span class="logo-txt"><b>ГОРИЗОНТЫ: ДИАЛОГ НАУК</b><small>ISSN 2686-8842 · подача рукописи</small></span>
    </a>
    <nav>
      <a class="chip" href="index.html#authors">Требования к рукописи</a>
      <a class="chip" href="index.html#submit"><svg><use href="#i-back"/></svg>Вернуться в журнал</a>
    </nav>
  </div>
</header>

<main class="svc-main">
  <div class="container">
    <div class="sec-head">
      <div class="sec-meta"><b>SEC.10</b> / 02<br>подача рукописи<br>анкета автора</div>
      <h1 class="sec-title">Отправить статью</h1>
    </div>

    <div class="svc-grid">
      <form id="articleForm" class="f" action="article_upload.php" method="post" enctype="multipart/form-data" novalidate>
        <!-- MAX_FILE_SIZE — подсказка PHP, основная проверка размера всё равно на сервере -->
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxBytes ?>">

        <!-- 01. Автор -->
        <div class="fs">
          <div class="fs-head"><span>01 · Сведения об авторе</span><b>4 поля</b></div>
          <div class="fs-body">
            <div class="wide">
              <label class="lbl" for="fullname">ФИО автора <span class="req">*</span></label>
              <input type="text" id="fullname" name="fullname" required maxlength="150"
                     pattern="[A-Za-zА-ЯЁа-яё'\-]+(\s+[A-Za-zА-ЯЁа-яё'.\-]+)+"
                     placeholder="Иванов Иван Иванович" autocomplete="name">
              <div class="hint">Не менее двух слов, только буквы, дефис и апостроф</div>
            </div>
            <div>
              <label class="lbl" for="email">E-mail <span class="req">*</span></label>
              <input type="email" id="email" name="email" required maxlength="120" placeholder="name@university.ru" autocomplete="email">
            </div>
            <div>
              <label class="lbl" for="organization">Организация <span class="req">*</span></label>
              <input type="text" id="organization" name="organization" required maxlength="200" placeholder="Финансовый университет при Правительстве РФ" autocomplete="organization">
            </div>
            <div class="wide">
              <label class="lbl" for="location">Страна / город <span class="req">*</span></label>
              <input type="text" id="location" name="location" required maxlength="120" placeholder="Россия, Москва">
            </div>
          </div>
        </div>

        <!-- 02. Рукопись -->
        <div class="fs">
          <div class="fs-head"><span>02 · Сведения о рукописи</span><b>6 полей</b></div>
          <div class="fs-body">
            <div class="wide">
              <label class="lbl" for="title">Название статьи <span class="req">*</span></label>
              <input type="text" id="title" name="title" required minlength="10" maxlength="300" placeholder="Полное название статьи на русском языке">
            </div>
            <div class="wide">
              <label class="lbl" for="topic">Область исследований <span class="req">*</span></label>
              <select id="topic" name="topic" required>
                <option value="">— выберите область —</option>
                <?php foreach ($topics as $key => $name): ?>
                  <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="wide">
              <label class="lbl" for="abstract">Аннотация <span class="req">*</span></label>
              <textarea id="abstract" name="abstract" required minlength="100" maxlength="4000" rows="6"
                        placeholder="Актуальность, цель, методы, результаты, выводы (рекомендуется 150–250 слов)"></textarea>
              <div class="hint" id="absCount">0 слов · рекомендуется 150–250</div>
            </div>
            <div class="wide">
              <label class="lbl" for="keywords">Ключевые слова <span class="req">*</span></label>
              <input type="text" id="keywords" name="keywords" required maxlength="300"
                     pattern="[^,]+(,[^,]+){2,9}"
                     placeholder="веб-сервис, рецензирование, PHP, CSV, научный журнал">
              <div class="hint">От 3 до 10 слов или словосочетаний через запятую</div>
            </div>
            <div class="wide">
              <label class="lbl" for="references">Список литературы</label>
              <textarea id="references" name="references" maxlength="8000" rows="5"
                        placeholder="1. Автор А. А. Название // Журнал. — 2024. — № 1. — С. 1–10. (необязательно)"></textarea>
            </div>
            <div class="wide">
              <label class="lbl" for="cover_letter">Сопроводительное письмо</label>
              <textarea id="cover_letter" name="cover_letter" maxlength="4000" rows="4"
                        placeholder="Кратко: почему статья подходит журналу (необязательно)"></textarea>
            </div>
          </div>
        </div>

        <!-- 03. Файл -->
        <div class="fs">
          <div class="fs-head"><span>03 · Файл рукописи</span><b>≤ 10 МБ</b></div>
          <div class="fs-body">
            <div class="wide">
              <label class="lbl" for="manuscript">Файл рукописи <span class="req">*</span></label>
              <div class="dropzone" id="dropzone">
                <svg><use href="#i-up"/></svg>
                <div class="d1"><b>Перетащите файл</b> или нажмите для выбора</div>
                <div class="d2">PDF · DOC · DOCX · TEX · TXT · RTF · ≤ 10 МБ</div>
                <div class="file-info" id="fileInfo"></div>
                <input type="file" id="manuscript" name="manuscript" required accept=".pdf,.doc,.docx,.tex,.txt,.rtf">
              </div>
            </div>
          </div>
        </div>

        <!-- 04. Подтверждения -->
        <div class="fs">
          <div class="fs-head"><span>04 · Подтверждение требований</span><b><span id="chkCount">0</span> / <?= count($checks) + 1 ?></b></div>
          <?php foreach ($checks as $key => [$name, $text]): ?>
            <label class="chk">
              <input type="checkbox" name="checks[]" value="<?= htmlspecialchars($key) ?>" required>
              <span><b><?= htmlspecialchars($name) ?></b> — <?= htmlspecialchars($text) ?></span>
            </label>
          <?php endforeach; ?>
          <label class="chk ethics">
            <input type="checkbox" name="ethics_agree" value="1" required>
            <span><b>Этическая политика</b> — я ознакомлен(а) с этической политикой журнала (COPE, ICMJE), подтверждаю отсутствие конфликта интересов и согласен(на) на обработку персональных данных</span>
          </label>
        </div>

        <div class="actions">
          <button type="submit" class="btn btn-solid" id="sendBtn"><span>Отправить рукопись</span><svg><use href="#i-arrow"/></svg></button>
          <a class="btn btn-line" href="index.html#submit"><span>Отмена</span></a>
        </div>
        <div id="sendBar"><i></i></div>
        <div id="sendStat">Отправка рукописи… не закрывайте страницу</div>
      </form>

      <aside class="side">
        <div class="guide-block">
          <div class="gb-head"><span>Как это работает</span><b>4 шага</b></div>
          <div class="gi"><span class="n">01</span><span><b>Анкета</b> — заполните поля, отмеченные звёздочкой.</span></div>
          <div class="gi"><span class="n">02</span><span><b>Файл</b> — анонимизированная рукопись до 10 МБ.</span></div>
          <div class="gi"><span class="n">03</span><span><b>ID заявки</b> — выдаётся сразу после отправки.</span></div>
          <div class="gi"><span class="n">04</span><span><b>Рецензия</b> — редакция выставит оценку и решение.</span></div>
        </div>
        <div class="guide-block">
          <div class="gb-head"><span>Шаблоны</span><b>DOC / TEX</b></div>
          <p>Скачайте шаблон оформления: <a href="files/template.docx" download>template.docx</a> или <a href="files/template.tex" download>template.tex</a>.</p>
          <p>Публикация бесплатна (APC 0 ₽), лицензия CC BY 4.0.</p>
        </div>
      </aside>
    </div>
  </div>
</main>

<script>
(function () {
  'use strict';
  var MAX = <?= $maxBytes ?>;
  var EXT = ['pdf', 'doc', 'docx', 'tex', 'txt', 'rtf'];

  var form = document.getElementById('articleForm');
  var file = document.getElementById('manuscript');
  var info = document.getElementById('fileInfo');
  var zone = document.getElementById('dropzone');
  var abs  = document.getElementById('abstract');
  var absCount = document.getElementById('absCount');
  var boxes = form.querySelectorAll('input[type=checkbox]');
  var chkCount = document.getElementById('chkCount');

  // Проверка файла на клиенте (тип и размер). Сервер проверит повторно.
  function checkFile() {
    var f = file.files[0];
    file.setCustomValidity('');
    info.className = 'file-info';
    if (!f) { info.textContent = ''; return; }
    var ext = f.name.split('.').pop().toLowerCase();
    var mb = (f.size / 1048576).toFixed(2) + ' МБ';
    if (EXT.indexOf(ext) === -1) {
      file.setCustomValidity('Недопустимый формат файла');
      info.className = 'file-info bad';
      info.textContent = f.name + ' — недопустимый формат (разрешены: ' + EXT.join(', ') + ')';
    } else if (f.size > MAX) {
      file.setCustomValidity('Файл больше 10 МБ');
      info.className = 'file-info bad';
      info.textContent = f.name + ' — ' + mb + ', превышен лимит 10 МБ';
    } else {
      info.textContent = f.name + ' · ' + mb;
    }
  }
  file.addEventListener('change', checkFile);
  ['dragenter', 'dragover'].forEach(function (e) { zone.addEventListener(e, function () { zone.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (e) { zone.addEventListener(e, function () { zone.classList.remove('over'); }); });

  // Счётчик слов аннотации
  function countWords() {
    var n = abs.value.trim() ? abs.value.trim().split(/\s+/).length : 0;
    absCount.textContent = n + ' слов · рекомендуется 150–250';
    absCount.className = 'hint' + (n && (n < 150 || n > 250) ? ' warn' : '');
  }
  abs.addEventListener('input', countWords);

  // Счётчик отмеченных подтверждений
  function countChecks() {
    var n = 0;
    boxes.forEach(function (b) { if (b.checked) n++; });
    chkCount.textContent = n;
  }
  boxes.forEach(function (b) { b.addEventListener('change', countChecks); });

  // Отправка: проверка формы и индикатор отправки
  form.addEventListener('submit', function (e) {
    checkFile();
    if (!form.checkValidity()) {
      e.preventDefault();
      form.reportValidity();
      return;
    }
    form.classList.add('sending');
    var btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.querySelector('span').textContent = 'Отправка…';
  });

  // Если пользователь вернулся кнопкой «Назад» — снимаем индикатор
  window.addEventListener('pageshow', function () {
    form.classList.remove('sending');
    var btn = document.getElementById('sendBtn');
    btn.disabled = false;
    btn.querySelector('span').textContent = 'Отправить рукопись';
    checkFile(); countWords(); countChecks();
  });
})();
</script>
</body>
</html>
