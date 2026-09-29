<?php View::$title = t('nav_campaigns'); $pages = max(1, (int)ceil($total / $per)); ?>
<div class="card">
  <div class="card-head">
    <form method="get" class="filters">
      <input type="hidden" name="kind" value="<?php echo h($kind); ?>">
      <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="<?php echo h(t('search')); ?>">
      <select name="status" onchange="this.form.submit()">
        <option value=""><?php echo h(t('all_statuses')); ?></option>
        <?php foreach (array('draft', 'scheduled', 'running', 'paused', 'completed', 'stopped') as $s): ?>
          <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo h(t('cs_' . $s)); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm" type="submit"><?php echo h(t('filter')); ?></button>
      <a class="btn btn-ghost btn-sm" href="<?php echo View::url('/campaigns?kind=' . ($kind === 'quick' ? 'campaign' : 'quick')); ?>"><?php echo h($kind === 'quick' ? t('show_campaigns') : t('show_quick_calls')); ?></a>
    </form>
    <?php if (Auth::can('operator')): ?><a class="btn btn-primary" href="<?php echo View::url('/campaigns/new'); ?>">+ <?php echo h(t('new_campaign')); ?></a><?php endif; ?>
  </div>
  <table class="table">
    <thead><tr><th>#</th><th><?php echo h(t('name')); ?></th><th><?php echo h(t('status')); ?></th><th><?php echo h(t('progress')); ?></th><th><?php echo h(t('answered')); ?></th><th><?php echo h(t('created_at')); ?></th><th><?php echo h(t('created_by')); ?></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted center"><?php echo h(t('nothing_here')); ?></td></tr><?php endif; ?>
    <?php foreach ($rows as $c): $pct = $c['total_contacts'] ? (int)round($c['cnt_done'] * 100 / $c['total_contacts']) : 0; ?>
      <tr>
        <td class="muted"><?php echo (int)$c['id']; ?></td>
        <td><a href="<?php echo View::url('/campaigns/' . $c['id']); ?>"><strong><?php echo h($c['name']); ?></strong></a></td>
        <td><span class="badge badge-<?php echo h($c['status']); ?>"><?php echo h(t('cs_' . $c['status'])); ?></span></td>
        <td><div class="bar"><div class="bar-in" style="width:<?php echo $pct; ?>%"></div></div><small><?php echo (int)$c['cnt_done']; ?>/<?php echo (int)$c['total_contacts']; ?> (<?php echo $pct; ?>%)</small></td>
        <td><?php echo (int)$c['cnt_answered']; ?></td>
        <td dir="ltr"><small><?php echo h($c['created_at']); ?></small></td>
        <td><small><?php echo h($c['creator']); ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php View::partial('pager', array('page' => $page, 'pages' => $pages, 'url' => View::url('/campaigns?kind=' . $kind . '&status=' . urlencode($status) . '&q=' . urlencode($q) . '&page='))); ?>
</div>
