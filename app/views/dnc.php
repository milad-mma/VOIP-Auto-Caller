<?php View::$title = t('nav_dnc'); $op = Auth::can('operator'); $pages = max(1, (int)ceil($total / $per)); ?>
<div class="row2">
  <div class="card">
    <div class="card-head">
      <h3><?php echo h(t('dnc_list')); ?> <span class="badge badge-muted"><?php echo (int)$total; ?></span></h3>
      <form method="get" class="filters"><input type="text" name="q" dir="ltr" value="<?php echo h($q); ?>" placeholder="<?php echo h(t('search')); ?>"><button class="btn btn-sm" type="submit"><?php echo h(t('filter')); ?></button>
        <a class="btn btn-sm btn-ghost" href="<?php echo View::url('/dnc/export?format=xlsx'); ?>">⬇ Excel</a></form>
    </div>
    <table class="table">
      <thead><tr><th><?php echo h(t('phone')); ?></th><th><?php echo h(t('reason')); ?></th><th><?php echo h(t('source')); ?></th><th><?php echo h(t('created_at')); ?></th><?php if ($op): ?><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted center"><?php echo h(t('nothing_here')); ?></td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr><td dir="ltr"><strong><?php echo h($r['phone']); ?></strong></td><td><?php echo h($r['reason']); ?></td><td><span class="badge badge-muted"><?php echo h($r['source']); ?></span> <small class="muted"><?php echo h($r['username']); ?></small></td><td dir="ltr"><small><?php echo h($r['created_at']); ?></small></td>
        <?php if ($op): ?><td><form method="post" action="<?php echo View::url('/dnc/' . $r['id'] . '/delete'); ?>" class="inline"><?php echo Auth::csrfField(); ?><button class="btn btn-sm btn-ghost" type="submit">✕</button></form></td><?php endif; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php View::partial('pager', array('page' => $page, 'pages' => $pages, 'url' => View::url('/dnc?q=' . urlencode($q) . '&page='))); ?>
  </div>
  <?php if ($op): ?>
  <div class="card">
    <h3><?php echo h(t('add_to_dnc')); ?></h3>
    <p class="hint"><?php echo h(t('dnc_hint')); ?></p>
    <form method="post" action="<?php echo View::url('/dnc/add'); ?>"><?php echo Auth::csrfField(); ?>
      <textarea name="numbers" rows="5" dir="ltr" placeholder="09121234567&#10;09351234567"></textarea>
      <label><?php echo h(t('reason')); ?><input type="text" name="reason"></label>
      <button class="btn btn-primary" type="submit"><?php echo h(t('add')); ?></button>
    </form>
    <hr>
    <form method="post" enctype="multipart/form-data" action="<?php echo View::url('/dnc/import'); ?>"><?php echo Auth::csrfField(); ?>
      <label><?php echo h(t('import_file')); ?><input type="file" name="file" accept=".csv,.txt,.xlsx"></label>
      <button class="btn" type="submit"><?php echo h(t('import_now')); ?></button>
    </form>
  </div>
  <?php endif; ?>
</div>
