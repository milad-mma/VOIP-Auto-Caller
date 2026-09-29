<?php View::$title = t('audit_log'); $pages = max(1, (int)ceil($total / $per)); ?>
<div class="card">
  <table class="table compact">
    <thead><tr><th><?php echo h(t('time')); ?></th><th><?php echo h(t('user')); ?></th><th>IP</th><th><?php echo h(t('action')); ?></th><th><?php echo h(t('object')); ?></th><th><?php echo h(t('details')); ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?><tr><td dir="ltr"><small><?php echo h($r['created_at']); ?></small></td><td><?php echo h($r['username']); ?></td><td dir="ltr"><small><?php echo h($r['ip']); ?></small></td><td><code><?php echo h($r['action']); ?></code></td><td><?php echo h($r['object_type']); ?> <?php echo $r['object_id'] ? '#' . (int)$r['object_id'] : ''; ?></td><td><small><?php echo h($r['details']); ?></small></td></tr><?php endforeach; ?>
    </tbody>
  </table>
  <?php View::partial('pager', array('page' => $page, 'pages' => $pages, 'url' => View::url('/audit?page='))); ?>
</div>
