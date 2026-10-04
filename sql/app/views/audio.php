<?php View::$title = t('nav_audio'); $op = Auth::can('operator'); ?>
<div class="row2">
  <div class="card">
    <div class="card-head"><h3><?php echo h(t('audio_files')); ?></h3></div>
    <table class="table">
      <thead><tr><th><?php echo h(t('name')); ?></th><th><?php echo h(t('duration')); ?></th><th><?php echo h(t('size')); ?></th><th><?php echo h(t('used_in')); ?></th><th><?php echo h(t('play')); ?></th><?php if ($op): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted center"><?php echo h(t('no_audio_files')); ?></td></tr><?php endif; ?>
      <?php foreach ($rows as $a): ?>
        <tr>
          <td><strong><?php echo h($a['name']); ?></strong><br><small class="muted"><?php echo h($a['original_name']); ?> · <?php echo h($a['uploader']); ?></small></td>
          <td><?php echo Util::formatDuration($a['duration_sec']); ?></td>
          <td><?php echo Util::humanSize($a['size_bytes']); ?></td>
          <td><?php echo (int)$a['used']; ?></td>
          <td><audio controls preload="none" src="<?php echo View::url('/audio/' . $a['id'] . '/play'); ?>"></audio></td>
          <?php if ($op): ?><td class="actions">
            <details class="menu"><summary class="btn btn-sm">⋯</summary><div class="menu-body">
              <form method="post" action="<?php echo View::url('/audio/' . $a['id'] . '/rename'); ?>"><?php echo Auth::csrfField(); ?><input type="text" name="name" value="<?php echo h($a['name']); ?>"><button class="btn btn-sm btn-block" type="submit"><?php echo h(t('rename')); ?></button></form>
              <form method="post" action="<?php echo View::url('/audio/' . $a['id'] . '/delete'); ?>" onclick="return confirm('<?php echo h(t('confirm_delete')); ?>')"><?php echo Auth::csrfField(); ?><button class="btn btn-sm btn-danger btn-block" type="submit"><?php echo h(t('delete')); ?></button></form>
            </div></details>
          </td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($op): ?>
  <div class="card">
    <h3><?php echo h(t('upload_audio')); ?></h3>
    <form method="post" enctype="multipart/form-data" action="<?php echo View::url('/audio/upload'); ?>"><?php echo Auth::csrfField(); ?>
      <label><?php echo h(t('file')); ?><input type="file" name="file" accept=".wav,.mp3,.gsm,.ogg,.m4a,.aac,.flac,.wma" required></label>
      <label><?php echo h(t('logical_name')); ?><input type="text" name="name" placeholder="promo1"><small class="hint"><?php echo h(t('logical_name_hint')); ?></small></label>
      <button class="btn btn-primary" type="submit"><?php echo h(t('upload')); ?></button>
    </form>
    <div class="hint">
      <p><?php echo h(t('audio_convert_hint')); ?></p>
      <?php if (!$sox): ?><p class="alert alert-warn"><?php echo h(t('no_sox_warning')); ?></p><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
