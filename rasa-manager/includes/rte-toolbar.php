<?php
/**
 * نوار ابزار ویرایشگر متنی غنی (RTE) — مشترک بین «ویرایشگر وبلاگ» و «ویرایشگر پروژه»
 * تا هر دو ابزار یکسان (بولد، لیست، لینک، تصویر، ویدیو، آهنگ، رنگ و ...) داشته باشند
 * و همیشه هماهنگ بمانند.
 *
 * $idBase مقدار پیشوند شناسه‌هاست: برای وبلاگ "rte" و برای پروژه "pRte".
 * خروجی این تابع فقط خودِ نوار ابزار (div.rte-toolbar) است؛ ظرف بیرونی rte
 * و ناحیه‌ی contenteditable در فایل صدا زننده قرار دارند.
 */
function resa_rte_toolbar_html(string $idBase): string
{
    $id = fn(string $suffix) => htmlspecialchars($idBase . $suffix, ENT_QUOTES, 'UTF-8');

    ob_start();
    ?>
    <div class="rte-toolbar" id="<?= $id('Toolbar') ?>">

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" data-cmd="undo" title="واگرد (Undo)">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h9a5 5 0 0 1 0 10h-2"/><path d="M7 5 3 10l4 5"/></svg></span>
          <span class="rte-tool-label">واگرد</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="redo" title="ازنو (Redo)">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10h-9a5 5 0 0 0 0 10h2"/><path d="m17 5 4 5-4 5"/></svg></span>
          <span class="rte-tool-label">ازنو</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" data-cmd="bold" title="بولد">
          <span class="rte-tool-icon rte-tool-icon--glyph"><b>B</b></span><span class="rte-tool-label">بولد</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="italic" title="ایتالیک">
          <span class="rte-tool-icon rte-tool-icon--glyph"><i>I</i></span><span class="rte-tool-label">ایتالیک</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="underline" title="زیرخط">
          <span class="rte-tool-icon rte-tool-icon--glyph"><u>U</u></span><span class="rte-tool-label">زیرخط</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="strikeThrough" title="خط‌خورده">
          <span class="rte-tool-icon rte-tool-icon--glyph"><s>S</s></span><span class="rte-tool-label">خط‌خورده</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" data-block="H2" title="تیتر بزرگ">
          <span class="rte-tool-icon rte-tool-icon--glyph">H2</span><span class="rte-tool-label">تیتر بزرگ</span>
        </button>
        <button type="button" class="rte-tool" data-block="H3" title="تیتر متوسط">
          <span class="rte-tool-icon rte-tool-icon--glyph">H3</span><span class="rte-tool-label">تیتر متوسط</span>
        </button>
        <button type="button" class="rte-tool" data-block="P" title="پاراگراف عادی">
          <span class="rte-tool-icon rte-tool-icon--glyph">P</span><span class="rte-tool-label">پاراگراف</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="blockquote" title="نقل‌قول">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7.17 6C4.87 6 3 7.94 3 10.33c0 2.13 1.53 3.9 3.5 4.24-.34 1.35-1.2 2.4-2.5 3.13l.9 1.3c2.63-1.36 4.1-3.5 4.1-6.5V10.3C9 7.94 9.47 6 7.17 6zm10 0c-2.3 0-4.17 1.94-4.17 4.33 0 2.13 1.53 3.9 3.5 4.24-.34 1.35-1.2 2.4-2.5 3.13l.9 1.3c2.63-1.36 4.1-3.5 4.1-6.5V10.3C19 7.94 19.47 6 17.17 6z"/></svg></span>
          <span class="rte-tool-label">نقل‌قول</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" data-cmd="insertUnorderedList" title="لیست نقطه‌ای">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="4.5" cy="6" r="1.15" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.15" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.15" fill="currentColor" stroke="none"/><path d="M9 6h11M9 12h11M9 18h11"/></svg></span>
          <span class="rte-tool-label">لیست نقطه‌ای</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="insertOrderedList" title="لیست شماره‌دار">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11M9 12h11M9 18h11"/><text x="1.6" y="8" font-size="6.5" fill="currentColor" stroke="none" font-family="sans-serif">1</text><text x="1.6" y="14" font-size="6.5" fill="currentColor" stroke="none" font-family="sans-serif">2</text><text x="1.6" y="20" font-size="6.5" fill="currentColor" stroke="none" font-family="sans-serif">3</text></svg></span>
          <span class="rte-tool-label">لیست شماره‌دار</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" data-cmd="justifyRight" title="راست‌چین">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M10 12h10M7 18h13"/></svg></span>
          <span class="rte-tool-label">راست‌چین</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="justifyCenter" title="وسط‌چین">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M8 12h8M6 18h12"/></svg></span>
          <span class="rte-tool-label">وسط‌چین</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="justifyLeft" title="چپ‌چین">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h13"/></svg></span>
          <span class="rte-tool-label">چپ‌چین</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <label class="rte-tool rte-tool--color" title="رنگ متن">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a9 9 0 1 1 9-9c0 1.5-1 3-3 3h-1.7c-1 0-1.6 1.2-1 2 .5.6.5 1.6-.2 2.2-.5.5-1.5.8-2.1.8z"/><circle cx="7.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="12" cy="7.3" r="1.1" fill="currentColor" stroke="none"/><circle cx="16.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/></svg></span>
          <span class="rte-tool-label">رنگ متن</span>
          <input type="color" id="<?= $id('TextColor') ?>" value="#0c8d8d">
        </label>
        <label class="rte-tool rte-tool--color" title="رنگ هایلایت">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m9 11-6 6v3h3l6-6"/><path d="m14.5 4.5 5 5L12 17l-5-5z"/></svg></span>
          <span class="rte-tool-label">هایلایت</span>
          <input type="color" id="<?= $id('HiliteColor') ?>" value="#b9feff">
        </label>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" id="<?= $id('LinkBtn') ?>" title="افزودن لینک">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.5-1.5"/></svg></span>
          <span class="rte-tool-label">لینک</span>
        </button>
        <button type="button" class="rte-tool" id="<?= $id('ImageBtn') ?>" title="افزودن تصویر">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="8.5" cy="9.5" r="1.4"/><path d="M21 16.5 16 11a2 2 0 0 0-2.8 0L4 20"/></svg></span>
          <span class="rte-tool-label">تصویر</span>
        </button>
        <button type="button" class="rte-tool" id="<?= $id('VideoBtn') ?>" title="افزودن ویدیو (آپارات، یوتیوب یا آپلود فایل)">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="13" height="12" rx="2.2"/><path d="M21 9.2 16 12l5 2.8z"/></svg></span>
          <span class="rte-tool-label">ویدیو</span>
        </button>
        <button type="button" class="rte-tool" id="<?= $id('AudioBtn') ?>" title="افزودن آهنگ / صدا">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="2.6"/><circle cx="17" cy="16" r="2.6"/></svg></span>
          <span class="rte-tool-label">آهنگ / صدا</span>
        </button>
        <button type="button" class="rte-tool" data-cmd="removeFormat" title="پاک‌کردن قالب‌بندی">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20H8l-5-5a2 2 0 0 1 0-2.8l9-9a2 2 0 0 1 2.8 0l5.5 5.5a2 2 0 0 1 0 2.8L13 18"/><path d="m8.5 12.5 6.5 6.5"/></svg></span>
          <span class="rte-tool-label">پاک‌کردن</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" id="<?= $id('TableBtn') ?>" title="افزودن جدول">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="1.5"/><path d="M3 10h18M3 16h18M9 4v16M15 4v16"/></svg></span>
          <span class="rte-tool-label">جدول</span>
        </button>
        <button type="button" class="rte-tool" id="<?= $id('EmojiBtn') ?>" title="افزودن ایموجی">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 10.5h.01M15.5 10.5h.01M8 15c1 1.3 2.4 2 4 2s3-.7 4-2"/></svg></span>
          <span class="rte-tool-label">ایموجی</span>
        </button>
        <button type="button" class="rte-tool" id="<?= $id('HrBtn') ?>" title="افزودن خط جداکننده">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 12h16"/><circle cx="7" cy="12" r="1.1" fill="currentColor" stroke="none"/><circle cx="17" cy="12" r="1.1" fill="currentColor" stroke="none"/></svg></span>
          <span class="rte-tool-label">خط جدا‌کننده</span>
        </button>
        <button type="button" class="rte-tool" id="<?= $id('CodeBtn') ?>" title="قطعه کد">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m9 9-2.5 3L9 15M15 9l2.5 3L15 15"/></svg></span>
          <span class="rte-tool-label">قطعه کد</span>
        </button>
      </div>

      <div class="rte-tool-group">
        <button type="button" class="rte-tool" id="<?= $id('HtmlToggleBtn') ?>" title="ویرایش کد HTML خام">
          <span class="rte-tool-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 6-6 6 6 6M16 6l6 6-6 6"/></svg></span>
          <span class="rte-tool-label">کد HTML</span>
        </button>
      </div>

      <div class="rte-emoji-panel" id="<?= $id('EmojiPanel') ?>">
        <div class="rte-emoji-grid">
          <?php foreach ([
              "😀","😃","😄","😁","😆","😅","😂","🤣","😊","😇","🙂","😉",
              "😍","😘","😜","🤔","😎","🙄","😢","😭","😡","🥳","👍","👎",
              "👏","🙏","💪","🤝","🔥","✨","🎉","🎊","❤️","🧡","💛","💚",
              "💙","💜","🖤","🤍","💯","⭐","✅","❌","⚠️","💡","📌","📷",
              "🎬","🎵","🎧","💬","📢","🕒","📅","🚀","🌟","👌","🙌","🌹",
          ] as $emoji): ?>
            <button type="button" class="rte-emoji-item" data-emoji="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>"><?= $emoji ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <input type="file" id="<?= $id('ImageFile') ?>" accept="image/*" style="display:none;">
      <input type="file" id="<?= $id('VideoFile') ?>" accept="video/mp4,video/webm,video/ogg,video/quicktime" style="display:none;">
      <input type="file" id="<?= $id('AudioFile') ?>" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4" style="display:none;">
    </div>
    <?php
    return ob_get_clean();
}
?>
