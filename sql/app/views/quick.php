<?php View::$title = t('nav_quick'); ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('quick_call')); ?></h3>
    <p class="hint"><?php echo h(t('quick_hint')); ?></p>
    <form method="post" action="<?php echo View::url('/quick'); ?>" id="quick-form"><?php echo Auth::csrfField(); ?><input type="hidden" name="form_token" value="<?php echo h($formToken); ?>">
      <?php if (Request::get('dup')): ?><label class="check"><input type="checkbox" name="force" value="1"> <?php echo h(t('quick_force')); ?></label><?php endif; ?>
      <label><?php echo h(t('phone')); ?> *<input type="text" name="phone" dir="ltr" required placeholder="0912xxxxxxx" id="quick-phone" autocomplete="off" value="<?php echo h(Util::normalizePhone(Request::get('phone', ''), Settings::get('country_code', '98'))); ?>"><div id="quick-suggest" class="suggest" hidden></div></label>
      <label><?php echo h(t('name')); ?><input type="text" name="name"></label>
      <label><?php echo h(t('audio')); ?> *<select name="audio_id" required><?php foreach ($audio as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo h($a['name']); ?> (<?php echo Util::formatDuration($a['duration_sec']); ?>)</option><?php endforeach; ?></select></label>
      <label><?php echo h(t('transfer_on_1')); ?><input type="text" name="transfer" dir="ltr" placeholder="<?php echo h(t('extension_or_queue')); ?>"></label>
      <button class="btn btn-primary" type="submit" id="quick-submit" <?php echo $audio ? '' : 'disabled'; ?>>📞 <?php echo h(t('call_now')); ?></button>
      <?php if (!$audio): ?><p class="alert alert-warn"><?php echo h(t('no_audio_files')); ?> <a href="<?php echo View::url('/audio'); ?>"><?php echo h(t('nav_audio')); ?></a></p><?php endif; ?>
    </form>
  </div>
  <div class="card">
    <h3><?php echo h(t('recent_quick_calls')); ?></h3>
    <table class="table compact">
      <thead><tr><th><?php echo h(t('phone')); ?></th><th><?php echo h(t('status')); ?></th><th><?php echo h(t('dtmf')); ?></th><th><?php echo h(t('duration')); ?></th><th><?php echo h(t('time')); ?></th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="5" class="muted center"><?php echo h(t('nothing_here')); ?></td></tr><?php endif; ?>
      <?php foreach ($recent as $r): ?><tr><td dir="ltr"><a href="<?php echo View::url('/campaigns/' . $r['id']); ?>"><?php echo h($r['phone']); ?></a></td><td><span class="badge badge-<?php echo h($r['cstatus']); ?>"><?php echo h(t('st_' . $r['cstatus'])); ?></span></td><td><?php echo h($r['dtmf']); ?></td><td><?php echo Util::formatDuration($r['duration_sec']); ?></td><td dir="ltr"><small><?php echo h(Util::fdate($r['created_at'])); ?></small></td></tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>window.AC_PAGE='quick';(function(){var f=document.getElementById('quick-form');if(f){f.addEventListener('submit',function(){var b=document.getElementById('quick-submit');if(b){b.disabled=true;b.textContent='…';}});}})();</script>
