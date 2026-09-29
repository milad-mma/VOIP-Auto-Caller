<?php View::$title = t('nav_system'); $alive = $age !== null && $age < 30; ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('dialer_daemon')); ?> <span class="badge <?php echo $alive ? 'badge-running' : 'badge-stopped'; ?>"><?php echo h($alive ? t('running') : t('not_running')); ?></span></h3>
    <dl class="dl">
      <dt>PID</dt><dd><?php echo h($daemon['pid'] ?: '-'); ?></dd>
      <dt><?php echo h(t('started_at')); ?></dt><dd dir="ltr"><?php echo h($daemon['started_at']); ?></dd>
      <dt>Heartbeat</dt><dd dir="ltr"><?php echo h($daemon['heartbeat_at']); ?> <?php echo $age !== null ? '(' . $age . 's)' : ''; ?></dd>
      <dt>AMI</dt><dd><span class="badge <?php echo $daemon['ami_connected'] ? 'badge-running' : 'badge-stopped'; ?>"><?php echo $daemon['ami_connected'] ? h(t('connected')) : h(t('disconnected')); ?></span></dd>
      <dt><?php echo h(t('live_calls')); ?></dt><dd><?php echo (int)$daemon['active_calls']; ?></dd>
      <dt><?php echo h(t('last_error')); ?></dt><dd dir="ltr"><?php echo h($daemon['last_error'] ?: '-'); ?></dd>
    </dl>
    <?php if (!$alive): ?><div class="alert alert-warn"><?php echo h(t('daemon_down_hint')); ?><br><code dir="ltr">systemctl restart autocaller-dialer</code></div><?php endif; ?>
  </div>
  <div class="card">
    <h3><?php echo h(t('environment')); ?></h3>
    <dl class="dl">
      <?php foreach ($info as $k => $v): ?><dt><?php echo h($k); ?></dt><dd dir="ltr"><?php echo h($v); ?></dd><?php endforeach; ?>
    </dl>
  </div>
</div>
<div class="card">
  <div class="card-head"><h3><?php echo h(t('logs')); ?></h3>
    <div class="filters">
      <?php foreach ($logs as $n => $sz): ?><button class="btn btn-sm log-btn" data-log="<?php echo $n; ?>" type="button"><?php echo $n; ?> <small class="muted"><?php echo h($sz); ?></small></button><?php endforeach; ?>
      <button class="btn btn-sm log-btn" data-log="php-error" type="button">php-error</button>
      <a class="btn btn-sm btn-ghost" href="<?php echo View::url('/audit'); ?>"><?php echo h(t('audit_log')); ?></a>
    </div>
  </div>
  <pre id="log-view" dir="ltr" class="code log"><?php echo h(t('pick_a_log')); ?></pre>
</div>
<script>window.AC_PAGE = 'system';</script>
