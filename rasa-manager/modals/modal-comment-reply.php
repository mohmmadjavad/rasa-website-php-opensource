<div class="modal-backdrop" id="commentReplyModal">
  <div class="modal-box">
    <button class="modal-close-btn" id="commentReplyCloseBtn" type="button" aria-label="بستن">&times;</button>
    <h3>پاسخ به نظر</h3>
    <p class="modal-meta" id="commentReplyTarget"></p>
    <p class="modal-message-text" id="commentReplyOriginalText"></p>
    <form id="commentReplyForm">
      <textarea id="commentReplyText" rows="4" maxlength="2000" placeholder="پاسخ خود را بنویسید..." style="width:100%; margin-top:1rem; padding:.7rem .9rem; border-radius:14px; border:1.5px solid var(--border-soft); font-family:inherit; font-size:.9rem; resize:vertical; outline:none;" required></textarea>
      <button type="submit" class="btn-primary" style="margin-top:.9rem; width:100%;">ارسال پاسخ</button>
    </form>
    <div class="form-msg" id="commentReplyMsg"></div>
  </div>
</div>

