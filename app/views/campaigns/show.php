<?php
View::$title = $c['name'];
$op = Auth::can('operator');
$act = function ($do, $label, $cls = 'btn-sm', $confirm = '') use ($c) {
    $onc = $confirm ? ' onclick="return confirm(\'' . h($confirm) . '\')"' : '';
    return '<form method="post" class="inline" action="' . View::url('/campaigns/' . $c['id'] . '/action') . '"' . $onc . '>' . Auth::csrfField() . '<input type="hidden" name="do" value="' . $do . '"><button class="btn ' . $cls . '" type="submit">' . h($label) . '</button></form>';
};
$waitMsg = $c['last_error'] && strpos($c['last_error'], 'wait:') === 0 ? t('wait_' . substr($c['last_error'], 5)) : ($c['last_error'] ? $c['last_error'] : '');
?>
<div class="card head-card">
  <div class="head-left">
    <h2><?php echo h($c['name']); ?> <span class="badge badge-<?php echo h($c['status']); ?>" id="c-status"><?php echo h(t('cs_' . $c['status'])); ?></span></h2>
    <div class="muted small">#<?php echo (int)$c['id']; ?> · <?php echo h(t('created_at')); ?> <span dir="ltr"><?php echo h(Util::fdate($c['created_at'])); ?></span>
      <?php if ($c['started_at']): ?> · <?php echo h(t('started_at')); ?> <span dir="ltr"><?php echo h(Util::fdate($c['started_at'])); ?></span><?php endif; ?>
      <?php if ($c['finished_at']): ?> · <?php echo h(t('finished_at')); ?> <span dir="ltr"><?php echo h(Util::fdate($c['finished_at'])); ?></span><?php endif; ?>
    </div>
    <?php if ($waitMsg): ?><div class="alert alert-warn small" id="c-wait"><?php echo h($waitMsg); ?></div><?php endif; ?>
    <?php if ($c['description']): ?><p class="muted"><?php echo nl2br(h($c['description'])); ?></p><?php endif; ?>
  </div>
  <?php if ($op): ?>
  <div class="head-actions">
    <?php if (in_array($c['status'], array('draft', 'completed', 'stopped'), true)) echo $act('start', t('start'), 'btn-primary'); ?>
    <?php if ($c['status'] === 'scheduled') echo $act('start', t('start_now'), 'btn-primary'); ?>
    <?php if (in_array($c['status'], array('running', 'scheduled'), true)) echo $act('pause', t('pause'), 'btn-warn'); ?>
    <?php if ($c['status'] === 'paused') echo $act('resume', t('resume'), 'btn-primary'); ?>
    <?php if (in_array($c['status'], array('running', 'paused', 'scheduled'), true)) echo $act('stop', t('stop'), 'btn-danger', t('confirm_stop')); ?>
    <a class="btn btn-sm" href="<?php echo View::url('/campaigns/' . $c['id'] . '/edit'); ?>"><?php echo h(t('edit')); ?></a>
    <a class="btn btn-sm" href="<?php echo View::url('/campaigns/' . $c['id'] . '/import'); ?>"><?php echo h(t('import_contacts')); ?></a>
    <details class="menu">
      <summary class="btn btn-sm">⋯</summary>
      <div class="menu-body">
        <form method="post" action="<?php echo View::url('/campaigns/' . $c['id'] . '/action'); ?>"><?php echo Auth::csrfField(); ?><input type="hidden" name="do" value="duplicate"><label class="check"><input type="checkbox" name="with_contacts" value="1" checked> <?php echo h(t('with_contacts')); ?></label><button class="btn btn-sm btn-block" type="submit"><?php echo h(t('duplicate')); ?></button></form>
        <?php if (!in_array($c['status'], array('running', 'scheduled'), true)): ?>
          <?php echo $act('clear_contacts', t('clear_contacts'), 'btn-sm btn-block', t('confirm_clear')); ?>
          <?php if (Auth::can('admin')) echo $act('delete', t('delete_campaign'), 'btn-sm btn-danger btn-block', t('confirm_delete')); ?>
        <?php endif; ?>
      </div>
    </details>
  </div>
  <?php endif; ?>
</div>

<div class="grid stats" id="c-stats">
  <div class="card stat"><div class="stat-v" data-k="total"><?php echo $stats['total']; ?></div><div class="stat-l"><?php echo h(t('contacts')); ?></div></div>
  <div class="card stat"><div class="stat-v" data-k="pending"><?php echo $stats['pending']; ?></div><div class="stat-l"><?php echo h(t('st_pending')); ?></div></div>
  <div class="card stat live"><div class="stat-v" data-k="active"><?php echo $stats['dialing'] + $stats['answered']; ?></div><div class="stat-l"><?php echo h(t('in_progress')); ?></div></div>
  <div class="card stat ok"><div class="stat-v" data-k="completed"><?php echo $stats['completed']; ?></div><div class="stat-l"><?php echo h(t('st_completed')); ?></div></div>
  <div class="card stat warn"><div class="stat-v" data-k="noanswer"><?php echo $stats['noanswer']; ?></div><div class="stat-l"><?php echo h(t('st_noanswer')); ?></div></div>
  <div class="card stat"><div class="stat-v" data-k="busy"><?php echo $stats['busy']; ?></div><div class="stat-l"><?php echo h(t('st_busy')); ?></div></div>
  <div class="card stat bad"><div class="stat-v" data-k="failed"><?php echo $stats['failed'] + $stats['congestion'] + $stats['invalid']; ?></div><div class="stat-l"><?php echo h(t('st_failed')); ?></div></div>
  <div class="card stat"><div class="stat-v" data-k="dnc"><?php echo $stats['dnc'] + $stats['cancelled'] + $stats['machine']; ?></div><div class="stat-l"><?php echo h(t('skipped')); ?></div></div>
</div>
<div class="card slim"><div class="bar big"><div class="bar-in" id="c-bar" style="width:<?php echo $stats['pct']; ?>%"></div></div><small class="muted" id="c-pct"><?php echo $stats['pct']; ?>%</small></div>

<div class="row2">
  <div class="card">
    <h3><?php echo h(t('summary')); ?></h3>
    <dl class="dl">
      <dt><?php echo h(t('default_audio')); ?></dt><dd><?php echo $audio ? h($audio['name']) . ' (' . Util::formatDuration($audio['duration_sec']) . ') <audio controls preload="none" src="' . View::url('/audio/' . $audio['id'] . '/play') . '"></audio>' : '<span class="muted">' . h(t('none_per_contact')) . '</span>'; ?></dd>
      <dt><?php echo h(t('concurrent')); ?></dt><dd><bdi><?php echo (int)$c['concurrent']; ?></bdi></dd>
      <dt><?php echo h(t('gap_ms')); ?></dt><dd><bdi><?php echo (int)$c['gap_ms']; ?></bdi></dd>
      <dt><?php echo h(t('ring_timeout')); ?></dt><dd><bdi><?php echo (int)$c['ring_timeout']; ?></bdi></dd>
      <dt><?php echo h(t('retries')); ?></dt><dd><bdi><?php echo (int)$c['max_retries']; ?></bdi> × <bdi><?php echo (int)$c['retry_delay_min']; ?></bdi> <?php echo h(t('minutes')); ?><?php if ($c['retry_on']): ?> <small class="muted">(<?php echo h(implode(t('list_sep'), array_map(function ($x) { return t('st_' . trim($x)); }, explode(',', $c['retry_on'])))); ?>)</small><?php endif; ?></dd>
      <dt><?php echo h(t('schedule')); ?></dt><dd>
        <?php if ($c['start_at'] || $c['end_at']): ?><bdi><?php echo h(Util::fdate($c['start_at']) ?: '…'); ?></bdi> → <bdi><?php echo h(Util::fdate($c['end_at']) ?: '…'); ?></bdi> · <?php else: ?><span class="muted"><?php echo h(t('no_date_limit')); ?></span> · <?php endif; ?>
        <bdi><?php echo h(($c['work_start'] ? $c['work_start'] : Settings::get('work_start')) . '–' . ($c['work_end'] ? $c['work_end'] : Settings::get('work_end'))); ?></bdi>
        <?php if ($c['work_days']): $dn = array(t('day_sun'), t('day_mon'), t('day_tue'), t('day_wed'), t('day_thu'), t('day_fri'), t('day_sat')); ?> · <?php echo h(implode(t('list_sep'), array_map(function ($d) use ($dn) { return $dn[(int)$d]; }, explode(',', $c['work_days'])))); ?><?php endif; ?>
      </dd>
      <dt><?php echo h(t('channel')); ?></dt><dd dir="ltr" class="ltr-cell"><code><?php echo h(Campaign::channelFor($eff, '09XXXXXXXXX')); ?></code><br><small class="muted">CID <?php echo h(Dialer::callerIdString($eff['callerid_name'], $eff['callerid_number'])); ?></small></dd>
      <dt><?php echo h(t('ivr_keys')); ?></dt><dd>
        <?php if (!$ivr['digits']): ?><span class="muted"><?php echo h(t('none')); ?></span><?php endif; ?>
        <?php foreach ($ivr['digits'] as $k => $d): ?><span class="chip"><b><?php echo h($k); ?></b> <?php echo h(t('ivr_' . $d['action'])); ?><?php echo $d['action'] === 'transfer' ? ' → ' . h($d['target']) : ''; ?><?php echo $d['tag'] ? ' [' . h($d['tag']) . ']' : ''; ?></span> <?php endforeach; ?>
      </dd>
    </dl>
  </div>
  <div class="card">
    <h3><?php echo h(t('responses')); ?></h3>
    <?php if (!$dtmf && !$tags): ?><p class="muted"><?php echo h(t('no_responses_yet')); ?></p><?php endif; ?>
    <?php if ($dtmf): ?><div class="chips"><?php foreach ($dtmf as $d): ?><span class="chip"><?php echo h(t('key')); ?> <b><?php echo h($d['k']); ?></b>: <?php echo (int)$d['n']; ?></span> <?php endforeach; ?></div><?php endif; ?>
    <?php if ($tags): ?><table class="table compact"><thead><tr><th><?php echo h(t('tag')); ?></th><th><?php echo h(t('count')); ?></th></tr></thead><tbody><?php foreach ($tags as $tg): ?><tr><td><?php echo h($tg['result_tag']); ?></td><td><?php echo (int)$tg['n']; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <div class="btn-row">
      <a class="btn btn-sm" href="<?php echo View::url('/campaigns/' . $c['id'] . '/export?format=xlsx'); ?>">⬇ Excel</a>
      <a class="btn btn-sm" href="<?php echo View::url('/campaigns/' . $c['id'] . '/export?format=csv'); ?>">⬇ CSV</a>
      <a class="btn btn-sm" href="<?php echo View::url('/campaigns/' . $c['id'] . '/export?format=xlsx&status=completed'); ?>">⬇ <?php echo h(t('answered_only')); ?></a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3><?php echo h(t('contacts')); ?></h3>
    <div class="filters">
      <input type="text" id="ct-q" placeholder="<?php echo h(t('search')); ?>">
      <select id="ct-status">
        <option value=""><?php echo h(t('all_statuses')); ?></option>
        <option value="active"><?php echo h(t('in_progress')); ?></option>
        <option value="pressed"><?php echo h(t('pressed_key')); ?></option>
        <?php foreach (CallStatus::all() as $st): ?><option value="<?php echo $st; ?>"><?php echo h(t('st_' . $st)); ?></option><?php endforeach; ?>
      </select>
      <label class="check small"><input type="checkbox" id="ct-auto" checked> <?php echo h(t('auto_refresh')); ?></label>
    </div>
  </div>
  <table class="table" id="ct-table">
    <thead><tr><th><?php echo h(t('phone')); ?></th><th><?php echo h(t('name')); ?></th><th><?php echo h(t('status')); ?></th><th><?php echo h(t('attempts')); ?></th><th><?php echo h(t('last_attempt')); ?></th><th><?php echo h(t('duration')); ?></th><th><?php echo h(t('dtmf')); ?></th><th><?php echo h(t('result_tag')); ?></th><th><?php echo h(t('note')); ?></th><?php if ($op): ?><th></th><?php endif; ?></tr></thead>
    <tbody><tr><td colspan="10" class="muted center">…</td></tr></tbody>
  </table>
  <div class="pager" id="ct-pager"></div>
</div>

<?php if ($op): ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('add_numbers_manually')); ?></h3>
    <form method="post" action="<?php echo View::url('/campaigns/' . $c['id'] . '/contacts/add'); ?>"><?php echo Auth::csrfField(); ?>
      <textarea name="numbers" rows="4" dir="ltr" placeholder="09121234567 Ali&#10;09351234567&#10;02188776655 Company"></textarea>
      <button class="btn btn-sm" type="submit"><?php echo h(t('add')); ?></button>
    </form>
  </div>
  <div class="card">
    <h3><?php echo h(t('requeue')); ?></h3>
    <p class="hint"><?php echo h(t('requeue_hint')); ?></p>
    <form method="post" action="<?php echo View::url('/campaigns/' . $c['id'] . '/action'); ?>"><?php echo Auth::csrfField(); ?><input type="hidden" name="do" value="requeue">
      <div class="check-row">
      <?php foreach (array('noanswer', 'busy', 'congestion', 'failed', 'machine', 'cancelled') as $st): ?><label class="inline"><input type="checkbox" name="statuses[]" value="<?php echo $st; ?>" <?php echo in_array($st, array('noanswer', 'busy'), true) ? 'checked' : ''; ?>> <?php echo h(t('st_' . $st)); ?> (<?php echo (int)$stats[$st]; ?>)</label><?php endforeach; ?>
      </div>
      <button class="btn btn-sm" type="submit"><?php echo h(t('requeue')); ?></button>
    </form>
  </div>
</div>
<?php endif; ?>
<script>window.AC_PAGE = 'campaign-show'; window.AC_CAMPAIGN = <?php echo (int)$c['id']; ?>; window.AC_CAN_OP = <?php echo $op ? 'true' : 'false'; ?>;
window.AC_ST = <?php $m = array(); foreach (CallStatus::all() as $st) { $m[$st] = t('st_' . $st); } echo Util::json($m); ?>;</script>
