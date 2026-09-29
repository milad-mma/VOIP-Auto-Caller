<?php View::$title = t('nav_dashboard'); ?>
<div class="grid stats" id="today-stats">
  <div class="card stat"><div class="stat-v" data-k="total"><?php echo (int)$today['total']; ?></div><div class="stat-l"><?php echo h(t('calls_today')); ?></div></div>
  <div class="card stat ok"><div class="stat-v" data-k="answered"><?php echo (int)$today['answered']; ?></div><div class="stat-l"><?php echo h(t('answered')); ?></div></div>
  <div class="card stat warn"><div class="stat-v" data-k="noanswer"><?php echo (int)$today['noanswer']; ?></div><div class="stat-l"><?php echo h(t('st_noanswer')); ?></div></div>
  <div class="card stat"><div class="stat-v" data-k="busy"><?php echo (int)$today['busy']; ?></div><div class="stat-l"><?php echo h(t('st_busy')); ?></div></div>
  <div class="card stat bad"><div class="stat-v" data-k="failed"><?php echo (int)$today['failed']; ?></div><div class="stat-l"><?php echo h(t('st_failed')); ?></div></div>
  <div class="card stat"><div class="stat-v" data-k="talk"><?php echo Util::formatDuration((int)$today['talk']); ?></div><div class="stat-l"><?php echo h(t('talk_time')); ?></div></div>
</div>

<div class="row2">
  <div class="card">
    <div class="card-head"><h3><?php echo h(t('active_campaigns')); ?></h3>
      <?php if (Auth::can('operator')): ?><a class="btn btn-primary btn-sm" href="<?php echo View::url('/campaigns/new'); ?>">+ <?php echo h(t('new_campaign')); ?></a><?php endif; ?>
    </div>
    <table class="table" id="campaign-live">
      <thead><tr><th><?php echo h(t('name')); ?></th><th><?php echo h(t('status')); ?></th><th><?php echo h(t('progress')); ?></th><th><?php echo h(t('answered')); ?></th></tr></thead>
      <tbody>
      <?php if (!$campaigns): ?><tr><td colspan="4" class="muted center"><?php echo h(t('no_active_campaigns')); ?></td></tr><?php endif; ?>
      <?php foreach ($campaigns as $c): $pct = $c['total_contacts'] ? (int)round($c['cnt_done'] * 100 / $c['total_contacts']) : 0; ?>
        <tr data-id="<?php echo $c['id']; ?>">
          <td><a href="<?php echo View::url('/campaigns/' . $c['id']); ?>"><?php echo h($c['name']); ?></a></td>
          <td><span class="badge badge-<?php echo h($c['status']); ?>"><?php echo h(t('cs_' . $c['status'])); ?></span> <small class="muted wait"><?php echo $c['last_error'] && strpos($c['last_error'], 'wait:') === 0 ? h(t('wait_' . substr($c['last_error'], 5))) : ''; ?></small></td>
          <td><div class="bar"><div class="bar-in" style="width:<?php echo $pct; ?>%"></div></div><small class="prog"><?php echo (int)$c['cnt_done']; ?>/<?php echo (int)$c['total_contacts']; ?></small></td>
          <td class="ans"><?php echo (int)$c['cnt_answered']; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <div class="card-head"><h3><?php echo h(t('live_calls')); ?> <span class="badge badge-running" id="live-count">0</span></h3></div>
    <table class="table" id="live-calls">
      <thead><tr><th><?php echo h(t('phone')); ?></th><th><?php echo h(t('campaign')); ?></th><th><?php echo h(t('status')); ?></th><th><?php echo h(t('elapsed')); ?></th></tr></thead>
      <tbody><tr><td colspan="4" class="muted center"><?php echo h(t('no_live_calls')); ?></td></tr></tbody>
    </table>
  </div>
</div>

<?php if ($recent): ?>
<div class="card">
  <div class="card-head"><h3><?php echo h(t('recent_finished')); ?></h3><a href="<?php echo View::url('/campaigns'); ?>"><?php echo h(t('all')); ?> →</a></div>
  <table class="table">
    <thead><tr><th><?php echo h(t('name')); ?></th><th><?php echo h(t('status')); ?></th><th><?php echo h(t('contacts')); ?></th><th><?php echo h(t('answered')); ?></th><th><?php echo h(t('finished_at')); ?></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $c): ?>
      <tr><td><a href="<?php echo View::url('/campaigns/' . $c['id']); ?>"><?php echo h($c['name']); ?></a></td><td><span class="badge badge-<?php echo h($c['status']); ?>"><?php echo h(t('cs_' . $c['status'])); ?></span></td><td><?php echo (int)$c['total_contacts']; ?></td><td><?php echo (int)$c['cnt_answered']; ?></td><td dir="ltr"><?php echo h($c['finished_at']); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<script>window.AC_PAGE = 'dashboard';</script>
