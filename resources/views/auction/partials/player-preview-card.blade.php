{{-- Signature player card — tap to edit registration details --}}
<button type="button" class="au-reg-player-card" id="open-reg-editor" aria-haspopup="dialog" aria-controls="reg-editor-modal">
  <div class="au-reg-card-frame" aria-hidden="true"></div>
  <div class="au-reg-card-inner">
    <div class="au-reg-card-photo" id="preview-photo">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
    </div>
    <div class="au-reg-card-meta">
      <span class="au-reg-card-kicker">Player card</span>
      <strong class="au-reg-card-name" id="preview-name">Your Name</strong>
      <span class="au-reg-card-role" id="preview-role">Playing role</span>
      <div class="au-reg-card-stats" id="player-preview-stats">
        <span><em>Matches</em><b id="preview-matches">—</b></span>
        <span><em>Runs</em><b id="preview-runs">—</b></span>
        <span><em>HS</em><b id="preview-hs">—</b></span>
        <span><em>Wkts</em><b id="preview-wickets">—</b></span>
      </div>
      <span class="au-reg-card-cta">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        Tap card to edit details
      </span>
    </div>
  </div>
</button>
