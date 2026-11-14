<?php
// Topic search box with a microphone button (voice.js); shown on the home page and on search.php.

function search_form($value = '') {
    static $n = 0; $n++;
    $id = 'ts-q' . $n;
    $speech = ['he' => 'he-IL', 'en' => 'en-US', 'fr' => 'fr-FR'][lang()] ?? 'he-IL'; ?>
<form class="tsearch" action="search.php" method="get" role="search">
  <label for="<?= $id ?>"><?= h(t('search_label')) ?></label>
  <span class="tsbox">
    <input id="<?= $id ?>" name="q" type="search" value="<?= h($value) ?>" required autocomplete="off" placeholder="<?= h(t('search_ph')) ?>">
    <button type="button" class="mic" hidden data-lang="<?= $speech ?>" title="<?= h(t('voice_btn')) ?>" aria-label="<?= h(t('voice_btn')) ?>"
            data-listening="<?= h(t('voice_listening')) ?>" data-nothing="<?= h(t('voice_nothing')) ?>" data-denied="<?= h(t('voice_denied')) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V22h2v-3.08A7 7 0 0 0 19 12h-2z"/></svg>
    </button>
  </span>
  <button type="submit"><?= h(t('search_btn')) ?></button>
  <span class="ts-status" aria-live="polite"></span>
</form>
<?php if ($n === 1): ?><script src="voice.js?v=<?= @filemtime(dirname(__DIR__) . '/voice.js') ?>" defer></script><?php endif;
}
