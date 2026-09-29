<?php
View::$title = t('nav_settings');
$dayNames = array(t('day_sun'), t('day_mon'), t('day_tue'), t('day_wed'), t('day_thu'), t('day_fri'), t('day_sat'));
$weekOrder = I18n::isRtl() ? array(6, 0, 1, 2, 3, 4, 5) : array(1, 2, 3, 4, 5, 6, 0);
$days = array_map('intval', explode(',', $s['work_days']));
$sel = function ($name, array $opts, $cur) { $o = '<select name="' . $name . '">'; foreach ($opts as $k => $v) { $o .= '<option value="' . h($k) . '"' . ((string)$cur === (string)$k ? ' selected' : '') . '>' . h($v) . '</option>'; } return $o . '</select>'; };
$bool = function ($name, $cur) { return '<select name="' . $name . '"><option value="1"' . ($cur === '1' ? ' selected' : '') . '>' . h(t('on')) . '</option><option value="0"' . ($cur !== '1' ? ' selected' : '') . '>' . h(t('off')) . '</option></select>'; };
?>
<form method="post" action="<?php echo View::url('/settings'); ?>" class="form"><?php echo Auth::csrfField(); ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('outbound_settings')); ?></h3>
    <div class="row2">
      <label><?php echo h(t('callerid_name')); ?><input type="text" name="callerid_name" value="<?php echo h($s['callerid_name']); ?>"></label>
      <label><?php echo h(t('callerid_number')); ?><input type="text" name="callerid_number" dir="ltr" value="<?php echo h($s['callerid_number']); ?>"></label>
    </div>
    <div class="row2">
      <label><?php echo h(t('channel_tech')); ?><?php echo $sel('channel_tech', array('local' => 'Local (' . t('via_dialplan') . ')', 'sip' => 'SIP/trunk/number', 'pjsip' => 'PJSIP/number@trunk', 'custom' => t('custom_template')), $s['channel_tech']); ?><small class="hint"><?php echo h(t('channel_tech_hint')); ?></small></label>
      <label id="f-trunk"><?php echo h(t('trunk_name')); ?><input type="text" name="trunk_name" dir="ltr" value="<?php echo h($s['trunk_name']); ?>" list="trunks"><datalist id="trunks"><?php foreach ($trunks as $tr): ?><option value="<?php echo h(preg_replace('#^[A-Za-z]+/#', '', $tr['channelid'])); ?>"><?php echo h($tr['name'] . ' (' . $tr['tech'] . ')'); ?></option><?php endforeach; ?></datalist></label>
    </div>
    <div class="row2">
      <label id="f-template"><?php echo h(t('channel_template')); ?><input type="text" name="channel_template" dir="ltr" value="<?php echo h($s['channel_template']); ?>" placeholder="SIP/{trunk}/{number}"></label>
      <label><?php echo h(t('outbound_context')); ?><input type="text" name="outbound_context" dir="ltr" value="<?php echo h($s['outbound_context']); ?>"></label>
    </div>
    <div class="row2">
      <label><?php echo h(t('dial_prefix')); ?><input type="text" name="dial_prefix" dir="ltr" value="<?php echo h($s['dial_prefix']); ?>" placeholder="9"><small class="hint"><?php echo h(t('dial_prefix_hint')); ?></small></label>
      <label><?php echo h(t('country_code')); ?><input type="text" name="country_code" dir="ltr" value="<?php echo h($s['country_code']); ?>"></label>
    </div>
    <?php if ($trunks): ?><details><summary><?php echo h(t('detected_trunks')); ?></summary><table class="table compact" dir="ltr"><?php foreach ($trunks as $tr): ?><tr><td><?php echo (int)$tr['trunkid']; ?></td><td><?php echo h($tr['name']); ?></td><td><?php echo h($tr['tech']); ?></td><td><code><?php echo h($tr['channelid']); ?></code></td><td><?php echo $tr['disabled'] === 'on' ? h(t('disabled')) : ''; ?></td></tr><?php endforeach; ?></table></details><?php endif; ?>
  </div>
  <div class="card">
    <h3><?php echo h(t('capacity_defaults')); ?></h3>
    <div class="row3">
      <label><?php echo h(t('global_max_concurrent')); ?><input type="number" name="global_max_concurrent" min="1" max="500" value="<?php echo h($s['global_max_concurrent']); ?>"><small class="hint"><?php echo h(t('global_max_hint')); ?></small></label>
      <label><?php echo h(t('default_concurrent')); ?><input type="number" name="default_concurrent" min="1" max="200" value="<?php echo h($s['default_concurrent']); ?>"></label>
      <label><?php echo h(t('default_gap_ms')); ?><input type="number" name="default_gap_ms" min="0" step="100" value="<?php echo h($s['default_gap_ms']); ?>"></label>
    </div>
    <div class="row3">
      <label><?php echo h(t('default_ring_timeout')); ?><input type="number" name="default_ring_timeout" min="5" max="120" value="<?php echo h($s['default_ring_timeout']); ?>"></label>
      <label><?php echo h(t('default_max_retries')); ?><input type="number" name="default_max_retries" min="0" max="10" value="<?php echo h($s['default_max_retries']); ?>"></label>
      <label><?php echo h(t('default_retry_delay_min')); ?><input type="number" name="default_retry_delay_min" min="1" value="<?php echo h($s['default_retry_delay_min']); ?>"></label>
    </div>
    <div class="row3">
      <label><?php echo h(t('amd')); ?><?php echo $bool('amd_enabled', $s['amd_enabled']); ?></label>
      <label><?php echo h(t('cdr_lookup')); ?><?php echo $bool('cdr_lookup', $s['cdr_lookup']); ?></label>
      <label><?php echo h(t('stale_call_minutes')); ?><input type="number" name="stale_call_minutes" min="2" max="120" value="<?php echo h($s['stale_call_minutes']); ?>"></label>
    </div>
    <h3><?php echo h(t('global_window')); ?></h3>
    <div class="row3">
      <label><?php echo h(t('work_start')); ?><input type="time" name="work_start" dir="ltr" value="<?php echo h($s['work_start']); ?>"></label>
      <label><?php echo h(t('work_end')); ?><input type="time" name="work_end" dir="ltr" value="<?php echo h($s['work_end']); ?>"></label>
      <label><?php echo h(t('respect_holidays')); ?><?php echo $bool('respect_holidays', $s['respect_holidays']); ?></label>
    </div>
    <div class="check-row"><span><?php echo h(t('work_days')); ?>:</span><?php foreach ($weekOrder as $d): ?><label class="inline"><input type="checkbox" name="work_days[]" value="<?php echo $d; ?>" <?php echo in_array($d, $days, true) ? 'checked' : ''; ?>> <?php echo h($dayNames[$d]); ?></label><?php endforeach; ?></div>
  </div>
</div>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('security_ui')); ?></h3>
    <div class="row3">
      <label><?php echo h(t('login_max_attempts')); ?><input type="number" name="login_max_attempts" min="0" value="<?php echo h($s['login_max_attempts']); ?>"></label>
      <label><?php echo h(t('login_lock_minutes')); ?><input type="number" name="login_lock_minutes" min="1" value="<?php echo h($s['login_lock_minutes']); ?>"></label>
      <label><?php echo h(t('retention_days')); ?><input type="number" name="retention_days" min="0" value="<?php echo h($s['retention_days']); ?>"><small class="hint"><?php echo h(t('retention_hint')); ?></small></label>
    </div>
    <div class="row3">
      <label><?php echo h(t('issabel_login')); ?><?php echo $bool('issabel_login', $s['issabel_login']); ?></label>
      <label><?php echo h(t('issabel_admin_role')); ?><?php echo $sel('issabel_admin_role', array('admin' => t('role_admin'), 'operator' => t('role_operator'), 'viewer' => t('role_viewer')), $s['issabel_admin_role']); ?></label>
      <label><?php echo h(t('api_enabled')); ?><?php echo $bool('api_enabled', $s['api_enabled']); ?></label>
    </div>
    <label><?php echo h(t('ui_lang')); ?><?php echo $sel('ui_lang', array('fa' => 'فارسی', 'en' => 'English'), $s['ui_lang']); ?></label>
  </div>
  <div class="card">
    <h3>AMI</h3>
    <p class="hint"><?php echo h(t('ami_hint')); ?></p>
    <dl class="dl"><dt>Host</dt><dd dir="ltr"><?php echo h($ami['host'] . ':' . $ami['port']); ?></dd><dt>User</dt><dd dir="ltr"><?php echo h($ami['user']); ?></dd><dt>Config</dt><dd dir="ltr"><code><?php echo h(APP_ROOT . '/config/config.php'); ?></code></dd></dl>
    <button class="btn btn-sm" type="button" id="test-ami"><?php echo h(t('test_ami')); ?></button> <span id="test-ami-result" class="muted small"></span>
  </div>
</div>
<div class="form-actions"><button class="btn btn-primary" type="submit"><?php echo h(t('save')); ?></button></div>
</form>

<div class="card" id="holidays">
  <div class="card-head"><h3><?php echo h(t('holidays')); ?></h3>
    <form method="post" action="<?php echo View::url('/settings/holidays/add'); ?>" class="filters"><?php echo Auth::csrfField(); ?><input type="date" name="hdate" dir="ltr" required><input type="text" name="title" placeholder="<?php echo h(t('title')); ?>"><button class="btn btn-sm" type="submit"><?php echo h(t('add')); ?></button></form>
  </div>
  <p class="hint"><?php echo h(t('holidays_hint')); ?></p>
  <form method="post" action="<?php echo View::url('/settings/holidays/iran'); ?>" class="filters"><?php echo Auth::csrfField(); ?>
    <?php $jy = Jalali::toJalali(date('Y'), date('n'), date('j')); $jy = $jy[0]; ?>
    <select name="jy"><?php for ($y = $jy - 1; $y <= $jy + 2; $y++): ?><option value="<?php echo $y; ?>" <?php echo $y === $jy ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select>
    <button class="btn btn-sm btn-primary" type="submit"><?php echo h(t('import_iran_holidays')); ?></button>
    <span class="hint"><?php echo h(t('import_iran_holidays_hint')); ?></span>
  </form>
  <div class="chips">
    <?php foreach ($holidays as $hd): ?><span class="chip"><span dir="ltr"><?php echo h(Util::fdate($hd['hdate'], false)); ?></span> <?php echo h($hd['title']); ?> <form method="post" class="inline" action="<?php echo View::url('/settings/holidays/' . $hd['id'] . '/delete'); ?>"><?php echo Auth::csrfField(); ?><button class="x" type="submit">✕</button></form></span> <?php endforeach; ?>
    <?php if (!$holidays): ?><span class="muted"><?php echo h(t('nothing_here')); ?></span><?php endif; ?>
  </div>
</div>
<script>window.AC_PAGE = 'settings';</script>
