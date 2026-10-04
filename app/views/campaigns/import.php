<?php View::$title = t('import_contacts') . ': ' . $c['name']; ?>
<div class="card slim breadcrumb"><a href="<?php echo View::url('/campaigns/' . $c['id']); ?>">← <?php echo h($c['name']); ?></a> · <?php echo h(t('contacts')); ?>: <?php echo (int)$stats['total']; ?> (<?php echo h(t('st_pending')); ?>: <?php echo (int)$stats['pending']; ?>)</div>

<?php if ($step === 1): ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('upload_file')); ?></h3>
    <form method="post" enctype="multipart/form-data" action="<?php echo View::url('/campaigns/' . $c['id'] . '/import'); ?>"><?php echo Auth::csrfField(); ?>
      <label><?php echo h(t('file_csv_xlsx')); ?><input type="file" name="file" accept=".csv,.txt,.xlsx" required></label>
      <button class="btn btn-primary" type="submit"><?php echo h(t('upload_and_preview')); ?></button>
    </form>
    <div class="hint">
      <p><?php echo h(t('import_hint_1')); ?></p>
      <p><?php echo h(t('import_hint_2')); ?></p>
      <p><?php echo h(t('import_hint_3')); ?></p>
      <pre dir="ltr">phone,name,audio
09121234567,Ali Ahmadi,promo1
09351234567,,promo2
02188776655,Company X,</pre>
    </div>
  </div>
  <div class="card">
    <h3><?php echo h(t('add_numbers_manually')); ?></h3>
    <form method="post" action="<?php echo View::url('/campaigns/' . $c['id'] . '/contacts/add'); ?>"><?php echo Auth::csrfField(); ?>
      <textarea name="numbers" rows="10" dir="ltr" placeholder="09121234567 Ali&#10;09351234567"></textarea>
      <button class="btn" type="submit"><?php echo h(t('add')); ?></button>
    </form>
    <div class="btn-row"><a class="btn btn-ghost" href="<?php echo View::url('/campaigns/' . $c['id']); ?>"><?php echo h(t('skip_for_now')); ?></a></div>
  </div>
</div>
<div class="card">
  <h3><?php echo h(t('pb_from_phonebook')); ?></h3>
  <?php if (!$pbGroups && !$pbUngrouped): ?><p class="muted"><?php echo h(t('pb_empty')); ?> <a href="<?php echo View::url('/phonebook'); ?>"><?php echo h(t('nav_phonebook')); ?></a></p>
  <?php else: ?>
  <form method="post" action="<?php echo View::url('/campaigns/' . $c['id'] . '/import/phonebook'); ?>"><?php echo Auth::csrfField(); ?>
    <div class="check-row">
      <label class="inline"><input type="checkbox" name="all" value="1"> <b><?php echo h(t('all')); ?></b></label>
      <?php if ($pbUngrouped): ?><label class="inline"><input type="checkbox" name="groups[]" value="0"> <?php echo h(t('pb_ungrouped')); ?> (<?php echo $pbUngrouped; ?>)</label><?php endif; ?>
      <?php foreach ($pbGroups as $g): ?><label class="inline"><input type="checkbox" name="groups[]" value="<?php echo $g['id']; ?>"> <?php echo h($g['name']); ?> (<?php echo (int)$g['n']; ?>)</label><?php endforeach; ?>
    </div>
    <label class="check"><input type="checkbox" name="skip_dnc" value="1" checked> <?php echo h(t('skip_dnc')); ?></label>
    <button class="btn btn-primary" type="submit"><?php echo h(t('pb_add_to_campaign')); ?></button>
  </form>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <h3><?php echo h(t('map_columns')); ?> — <?php echo h($fileName); ?> (<?php echo (int)$rowCount; ?> <?php echo h(t('rows')); ?>)</h3>
  <form method="post" action="<?php echo View::url('/campaigns/' . $c['id'] . '/import/confirm'); ?>"><?php echo Auth::csrfField(); ?>
    <input type="hidden" name="token" value="<?php echo h($token); ?>">
    <div class="row3">
      <label><?php echo h(t('phone_column')); ?> *<select name="col_phone"><?php for ($i = 0; $i < $cols; $i++): ?><option value="<?php echo $i; ?>" <?php echo $guess['phone'] === $i ? 'selected' : ''; ?>><?php echo h(t('column')); ?> <?php echo $i + 1; ?><?php echo $guess['has_header'] && isset($preview[0][$i]) ? ' — ' . h($preview[0][$i]) : ''; ?></option><?php endfor; ?></select></label>
      <label><?php echo h(t('name_column')); ?><select name="col_name"><option value="-1">—</option><?php for ($i = 0; $i < $cols; $i++): ?><option value="<?php echo $i; ?>" <?php echo $guess['name'] === $i ? 'selected' : ''; ?>><?php echo h(t('column')); ?> <?php echo $i + 1; ?><?php echo $guess['has_header'] && isset($preview[0][$i]) ? ' — ' . h($preview[0][$i]) : ''; ?></option><?php endfor; ?></select></label>
      <label><?php echo h(t('audio_column')); ?><select name="col_audio"><option value="-1">—</option><?php for ($i = 0; $i < $cols; $i++): ?><option value="<?php echo $i; ?>" <?php echo $guess['audio'] === $i ? 'selected' : ''; ?>><?php echo h(t('column')); ?> <?php echo $i + 1; ?><?php echo $guess['has_header'] && isset($preview[0][$i]) ? ' — ' . h($preview[0][$i]) : ''; ?></option><?php endfor; ?></select></label>
    </div>
    <div class="check-row">
      <label class="inline"><input type="checkbox" name="has_header" value="1" <?php echo $guess['has_header'] ? 'checked' : ''; ?>> <?php echo h(t('first_row_header')); ?></label>
      <label class="inline"><input type="checkbox" name="skip_dnc" value="1" checked> <?php echo h(t('skip_dnc')); ?></label>
      <label class="inline"><input type="checkbox" name="dedupe" value="1" checked> <?php echo h(t('dedupe')); ?></label>
    </div>
    <h4><?php echo h(t('preview')); ?></h4>
    <div class="scroll"><table class="table compact" dir="ltr"><tbody>
      <?php foreach ($preview as $ri => $row): ?><tr class="<?php echo $ri === 0 && $guess['has_header'] ? 'muted' : ''; ?>"><?php for ($i = 0; $i < $cols; $i++): ?><td><?php echo h(isset($row[$i]) ? $row[$i] : ''); ?></td><?php endfor; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?php echo h(t('import_now')); ?></button>
      <a class="btn btn-ghost" href="<?php echo View::url('/campaigns/' . $c['id'] . '/import'); ?>"><?php echo h(t('cancel')); ?></a>
    </div>
  </form>
</div>
<?php endif; ?>
