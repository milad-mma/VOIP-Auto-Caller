<?php
View::$title = $isNew ? t('new_campaign') : t('edit_campaign') . ': ' . $c['name'];
$s = Settings::all();
$useGlobalWindow = $c['work_start'] === null && $c['work_end'] === null && $c['work_days'] === null;
$days = $c['work_days'] !== null ? array_map('intval', explode(',', $c['work_days'])) : array(0, 1, 2, 3, 4, 5, 6);
$retryOn = array_map('trim', explode(',', (string)$c['retry_on']));
$dayNames = array(t('day_sun'), t('day_mon'), t('day_tue'), t('day_wed'), t('day_thu'), t('day_fri'), t('day_sat'));
$weekOrder = I18n::isRtl() ? array(6, 0, 1, 2, 3, 4, 5) : array(1, 2, 3, 4, 5, 6, 0);
$fmtDt = function ($v) { return $v ? date('Y-m-d\TH:i', strtotime($v)) : ''; };
?>
<form method="post" action="<?php echo View::url($isNew ? '/campaigns' : '/campaigns/' . $c['id']); ?>" class="form" id="campaign-form">
<?php echo Auth::csrfField(); ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('basic_info')); ?></h3>
    <label><?php echo h(t('name')); ?> *<input type="text" name="name" value="<?php echo h($c['name']); ?>" required maxlength="128"></label>
    <label><?php echo h(t('description')); ?><textarea name="description" rows="2"><?php echo h($c['description']); ?></textarea></label>
    <label><?php echo h(t('default_audio')); ?>
      <select name="audio_id">
        <option value="0">— <?php echo h(t('none_per_contact')); ?> —</option>
        <?php foreach ($audio as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo (int)$c['audio_id'] === (int)$a['id'] ? 'selected' : ''; ?>><?php echo h($a['name']); ?> (<?php echo Util::formatDuration($a['duration_sec']); ?>)</option><?php endforeach; ?>
      </select>
    </label>
    <p class="hint"><?php echo h(t('audio_hint')); ?> <a href="<?php echo View::url('/audio'); ?>" target="_blank"><?php echo h(t('nav_audio')); ?></a></p>
    <div class="row3">
      <label><?php echo h(t('priority')); ?><input type="number" name="priority" min="1" max="10" value="<?php echo (int)$c['priority']; ?>"></label>
      <label><?php echo h(t('max_repeats')); ?><input type="number" name="max_repeats" min="0" max="5" value="<?php echo (int)$c['max_repeats']; ?>"><small class="hint"><?php echo h(t('max_repeats_hint')); ?></small></label>
      <label><?php echo h(t('ivr_timeout')); ?><input type="number" name="ivr_timeout" min="1" max="30" value="<?php echo (int)$ivr['timeout_sec']; ?>"></label>
    </div>
  </div>

  <div class="card">
    <h3><?php echo h(t('dialing')); ?></h3>
    <div class="row3">
      <label><?php echo h(t('concurrent')); ?><input type="number" name="concurrent" min="1" max="200" value="<?php echo (int)$c['concurrent']; ?>"><small class="hint"><?php echo h(t('concurrent_hint', $s['global_max_concurrent'])); ?></small></label>
      <label><?php echo h(t('gap_ms')); ?><input type="number" name="gap_ms" min="0" step="100" value="<?php echo (int)$c['gap_ms']; ?>"></label>
      <label><?php echo h(t('ring_timeout')); ?><input type="number" name="ring_timeout" min="5" max="120" value="<?php echo (int)$c['ring_timeout']; ?>"></label>
    </div>
    <div class="row3">
      <label><?php echo h(t('max_retries')); ?><input type="number" name="max_retries" min="0" max="10" value="<?php echo (int)$c['max_retries']; ?>"></label>
      <label><?php echo h(t('retry_delay_min')); ?><input type="number" name="retry_delay_min" min="1" max="1440" value="<?php echo (int)$c['retry_delay_min']; ?>"></label>
      <label><?php echo h(t('amd')); ?><select name="amd"><option value="0" <?php echo !$c['amd'] ? 'selected' : ''; ?>><?php echo h(t('off')); ?></option><option value="1" <?php echo $c['amd'] ? 'selected' : ''; ?>><?php echo h(t('on')); ?></option></select></label>
    </div>
    <div class="check-row"><span><?php echo h(t('retry_on')); ?>:</span>
      <?php foreach (CallStatus::retryable() as $st): ?>
        <label class="inline"><input type="checkbox" name="retry_on[]" value="<?php echo $st; ?>" <?php echo in_array($st, $retryOn, true) ? 'checked' : ''; ?>> <?php echo h(t('st_' . $st)); ?></label>
      <?php endforeach; ?>
    </div>
    <details <?php echo ($c['callerid_name'] !== null || $c['callerid_number'] !== null || $c['trunk_name'] !== null || $c['dial_prefix'] !== null) ? 'open' : ''; ?>>
      <summary><?php echo h(t('advanced_overrides')); ?></summary>
      <div class="row3">
        <label><?php echo h(t('callerid_name')); ?><input type="text" name="callerid_name" value="<?php echo h($c['callerid_name']); ?>" placeholder="<?php echo h($s['callerid_name']); ?>"></label>
        <label><?php echo h(t('callerid_number')); ?><input type="text" name="callerid_number" dir="ltr" value="<?php echo h($c['callerid_number']); ?>" placeholder="<?php echo h($s['callerid_number']); ?>"></label>
        <label><?php echo h(t('channel_tech')); ?>
          <select name="channel_tech"><option value="">— <?php echo h(t('global')); ?> (<?php echo h($s['channel_tech']); ?>) —</option>
          <?php foreach (array('local', 'sip', 'pjsip', 'custom') as $ct): ?><option value="<?php echo $ct; ?>" <?php echo $c['channel_tech'] === $ct ? 'selected' : ''; ?>><?php echo strtoupper($ct); ?></option><?php endforeach; ?></select>
        </label>
      </div>
      <div class="row3">
        <label><?php echo h(t('trunk_name')); ?><input type="text" name="trunk_name" dir="ltr" value="<?php echo h($c['trunk_name']); ?>" placeholder="<?php echo h($s['trunk_name']); ?>"></label>
        <label class="check"><input type="checkbox" name="use_global_prefix" value="1" <?php echo $c['dial_prefix'] === null ? 'checked' : ''; ?> onchange="document.getElementById('dial_prefix').disabled=this.checked"> <?php echo h(t('use_global_prefix')); ?> (<?php echo h($s['dial_prefix'] === '' ? t('none') : $s['dial_prefix']); ?>)</label>
        <label><?php echo h(t('dial_prefix')); ?><input type="text" id="dial_prefix" name="dial_prefix" dir="ltr" value="<?php echo h($c['dial_prefix']); ?>" <?php echo $c['dial_prefix'] === null ? 'disabled' : ''; ?>></label>
      </div>
    </details>
  </div>
</div>

<div class="row2">
  <div class="card">
    <h3><?php echo h(t('schedule')); ?></h3>
    <div class="row2">
      <label><?php echo h(t('start_at')); ?><input type="datetime-local" name="start_at" dir="ltr" value="<?php echo $fmtDt($c['start_at']); ?>"><small class="hint"><?php echo h(t('start_at_hint')); ?></small></label>
      <label><?php echo h(t('end_at')); ?><input type="datetime-local" name="end_at" dir="ltr" value="<?php echo $fmtDt($c['end_at']); ?>"><small class="hint"><?php echo h(t('end_at_hint')); ?></small></label>
    </div>
    <label class="check"><input type="checkbox" name="use_global_window" value="1" <?php echo $useGlobalWindow ? 'checked' : ''; ?> onchange="document.getElementById('win').classList.toggle('disabled', this.checked)"> <?php echo h(t('use_global_window')); ?> (<?php echo h($s['work_start'] . '–' . $s['work_end']); ?>)</label>
    <div id="win" class="<?php echo $useGlobalWindow ? 'disabled' : ''; ?>">
      <div class="row2">
        <label><?php echo h(t('work_start')); ?><input type="time" name="work_start" dir="ltr" value="<?php echo h($c['work_start'] !== null ? $c['work_start'] : $s['work_start']); ?>"></label>
        <label><?php echo h(t('work_end')); ?><input type="time" name="work_end" dir="ltr" value="<?php echo h($c['work_end'] !== null ? $c['work_end'] : $s['work_end']); ?>"></label>
      </div>
      <div class="check-row"><span><?php echo h(t('work_days')); ?>:</span>
        <?php foreach ($weekOrder as $d): ?><label class="inline"><input type="checkbox" name="work_days[]" value="<?php echo $d; ?>" <?php echo in_array($d, $days, true) ? 'checked' : ''; ?>> <?php echo h($dayNames[$d]); ?></label><?php endforeach; ?>
      </div>
    </div>
    <label class="check"><input type="checkbox" name="respect_holidays" value="1" <?php echo $c['respect_holidays'] ? 'checked' : ''; ?>> <?php echo h(t('respect_holidays')); ?></label>
  </div>

  <div class="card">
    <h3><?php echo h(t('ivr_keys')); ?></h3>
    <p class="hint"><?php echo h(t('ivr_hint')); ?></p>
    <table class="table ivr">
      <thead><tr><th><?php echo h(t('key')); ?></th><th><?php echo h(t('action')); ?></th><th><?php echo h(t('target')); ?></th><th><?php echo h(t('tag')); ?></th></tr></thead>
      <tbody>
      <?php foreach (array('1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#') as $k):
        $key = $k === '*' ? 'star' : ($k === '#' ? 'hash' : $k);
        $cfg = isset($ivr['digits'][$k]) ? $ivr['digits'][$k] : array('action' => 'none', 'target' => '', 'context' => 'from-internal', 'audio_id' => 0, 'tag' => '');
        $act = $cfg['action']; ?>
        <tr class="ivr-row" data-key="<?php echo $key; ?>">
          <td class="key"><?php echo $k; ?></td>
          <td>
            <select name="ivr_action_<?php echo $key; ?>" class="ivr-action">
              <option value="none" <?php echo $act === 'none' ? 'selected' : ''; ?>>—</option>
              <?php foreach (Campaign::ivrActions() as $a): ?><option value="<?php echo $a; ?>" <?php echo $act === $a ? 'selected' : ''; ?>><?php echo h(t('ivr_' . $a)); ?></option><?php endforeach; ?>
            </select>
          </td>
          <td>
            <span class="ivr-target ivr-t-transfer" <?php echo $act !== 'transfer' ? 'hidden' : ''; ?>>
              <input type="text" name="ivr_target_<?php echo $key; ?>" dir="ltr" placeholder="<?php echo h(t('extension_or_queue')); ?>" value="<?php echo h(isset($cfg['target']) ? $cfg['target'] : ''); ?>" size="8">
              @<input type="text" name="ivr_context_<?php echo $key; ?>" dir="ltr" value="<?php echo h(isset($cfg['context']) ? $cfg['context'] : 'from-internal'); ?>" size="12">
            </span>
            <span class="ivr-target ivr-t-play" <?php echo $act !== 'play' ? 'hidden' : ''; ?>>
              <select name="ivr_audio_<?php echo $key; ?>">
                <?php foreach ($audio as $a): ?><option value="<?php echo $a['id']; ?>" <?php echo isset($cfg['audio_id']) && (int)$cfg['audio_id'] === (int)$a['id'] ? 'selected' : ''; ?>><?php echo h($a['name']); ?></option><?php endforeach; ?>
              </select>
            </span>
          </td>
          <td><input type="text" name="ivr_tag_<?php echo $key; ?>" value="<?php echo h(isset($cfg['tag']) ? $cfg['tag'] : ''); ?>" placeholder="<?php echo h(t('tag_placeholder')); ?>" size="12"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="form-actions">
  <button class="btn btn-primary" type="submit"><?php echo h($isNew ? t('create_and_import') : t('save')); ?></button>
  <a class="btn btn-ghost" href="<?php echo View::url($isNew ? '/campaigns' : '/campaigns/' . $c['id']); ?>"><?php echo h(t('cancel')); ?></a>
</div>
</form>
<script>window.AC_PAGE = 'campaign-form';</script>
