<?php View::$title = t('nav_phonebook'); $op = Auth::can('operator'); $pages = max(1, (int)ceil($total / $per)); $base = '/phonebook?q=' . urlencode($q) . '&group=' . $gid; ?>
<div class="pb-layout">
  <aside class="card pb-groups">
    <h3><?php echo h(t('pb_groups')); ?></h3>
    <a class="pb-g <?php echo $gid === -1 ? 'active' : ''; ?>" href="<?php echo View::url('/phonebook'); ?>"><?php echo h(t('all')); ?> <span class="badge badge-muted"><?php echo (int)$all; ?></span></a>
    <a class="pb-g <?php echo $gid === 0 ? 'active' : ''; ?>" href="<?php echo View::url('/phonebook?group=0'); ?>"><?php echo h(t('pb_ungrouped')); ?> <span class="badge badge-muted"><?php echo (int)$ungrouped; ?></span></a>
    <?php foreach ($groups as $g): ?>
      <a class="pb-g <?php echo $gid === (int)$g['id'] ? 'active' : ''; ?>" href="<?php echo View::url('/phonebook?group=' . $g['id']); ?>"><?php echo h($g['name']); ?> <span class="badge badge-muted"><?php echo (int)$g['n']; ?></span></a>
    <?php endforeach; ?>
    <?php if ($op): ?>
    <form method="post" action="<?php echo View::url('/phonebook/groups'); ?>" class="pb-newgroup"><?php echo Auth::csrfField(); ?><input type="text" name="name" placeholder="<?php echo h(t('pb_new_group')); ?>" required><button class="btn btn-sm" type="submit">+</button></form>
    <?php if ($gid > 0): $cur = null; foreach ($groups as $g) { if ((int)$g['id'] === $gid) $cur = $g; } if ($cur): ?>
      <details class="pb-gedit"><summary><?php echo h(t('pb_edit_group')); ?></summary>
        <form method="post" action="<?php echo View::url('/phonebook/groups'); ?>"><?php echo Auth::csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$cur['id']; ?>"><input type="text" name="name" value="<?php echo h($cur['name']); ?>"><button class="btn btn-sm" type="submit"><?php echo h(t('rename')); ?></button></form>
        <form method="post" action="<?php echo View::url('/phonebook/groups/' . $cur['id'] . '/delete'); ?>" onsubmit="return confirm('<?php echo h(t('confirm_delete')); ?>')"><?php echo Auth::csrfField(); ?><label class="check small"><input type="checkbox" name="with_contacts" value="1"> <?php echo h(t('pb_delete_with_contacts')); ?></label><button class="btn btn-sm btn-danger" type="submit"><?php echo h(t('delete')); ?></button></form>
      </details>
    <?php endif; endif; endif; ?>
  </aside>

  <div class="pb-main">
    <div class="card">
      <div class="card-head">
        <form method="get" class="filters"><input type="hidden" name="group" value="<?php echo $gid; ?>"><input type="text" name="q" value="<?php echo h($q); ?>" placeholder="<?php echo h(t('search')); ?>"><button class="btn btn-sm" type="submit"><?php echo h(t('filter')); ?></button>
          <a class="btn btn-sm btn-ghost" href="<?php echo View::url('/phonebook/export?format=xlsx&group=' . $gid); ?>">⬇ Excel</a></form>
        <span class="muted small"><?php echo (int)$total; ?> <?php echo h(t('contacts')); ?></span>
      </div>
      <form method="post" action="<?php echo View::url('/phonebook/bulk'); ?>" id="pb-bulk"><?php echo Auth::csrfField(); ?>
      <table class="table" id="pb-table">
        <thead><tr><?php if ($op): ?><th><input type="checkbox" id="pb-all"></th><?php endif; ?><th><?php echo h(t('phone')); ?></th><th><?php echo h(t('name')); ?></th><th><?php echo h(t('pb_group')); ?></th><th><?php echo h(t('notes')); ?></th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="6" class="muted center"><?php echo h(t('nothing_here')); ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr data-id="<?php echo (int)$r['id']; ?>">
            <?php if ($op): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>"></td><?php endif; ?>
            <td dir="ltr"><?php if ($op): ?><input type="text" class="pb-f" data-f="phone" value="<?php echo h($r['phone']); ?>" size="12" dir="ltr"><?php else: ?><strong><?php echo h($r['phone']); ?></strong><?php endif; ?></td>
            <td><?php if ($op): ?><input type="text" class="pb-f" data-f="name" value="<?php echo h($r['name']); ?>" size="16"><?php else: ?><?php echo h($r['name']); ?><?php endif; ?></td>
            <td><?php if ($op): ?><select class="pb-f" data-f="group_id"><option value="0">—</option><?php foreach ($groups as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo (int)$r['group_id'] === (int)$g['id'] ? 'selected' : ''; ?>><?php echo h($g['name']); ?></option><?php endforeach; ?></select><?php else: ?><?php echo h($r['group_name']); ?><?php endif; ?></td>
            <td><?php if ($op): ?><input type="text" class="pb-f" data-f="notes" value="<?php echo h($r['notes']); ?>" size="18"><?php else: ?><?php echo h($r['notes']); ?><?php endif; ?></td>
            <td class="actions"><a class="btn btn-sm btn-ghost" href="<?php echo View::url('/quick?phone=' . urlencode($r['phone'])); ?>" title="<?php echo h(t('quick_call')); ?>">📞</a><?php if ($op): ?> <button type="button" class="btn btn-sm btn-ghost pb-del" title="<?php echo h(t('delete')); ?>">✕</button><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($op && $rows): ?>
      <div class="filters pb-bulkbar">
        <span class="muted small"><?php echo h(t('pb_with_selected')); ?>:</span>
        <select name="group_id"><option value="0">— <?php echo h(t('pb_ungrouped')); ?> —</option><?php foreach ($groups as $g): ?><option value="<?php echo $g['id']; ?>"><?php echo h($g['name']); ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm" name="do" value="move" type="submit"><?php echo h(t('pb_move')); ?></button>
        <button class="btn btn-sm" name="do" value="dnc" type="submit"><?php echo h(t('add_to_dnc')); ?></button>
        <button class="btn btn-sm btn-danger" name="do" value="delete" type="submit" onclick="return confirm('<?php echo h(t('confirm_delete')); ?>')"><?php echo h(t('delete')); ?></button>
      </div>
      <?php endif; ?>
      </form>
      <?php View::partial('pager', array('page' => $page, 'pages' => $pages, 'url' => View::url($base . '&page='))); ?>
    </div>

    <?php if ($op): ?>
    <div class="row2">
      <div class="card">
        <h3><?php echo h(t('add_numbers_manually')); ?></h3>
        <form method="post" action="<?php echo View::url('/phonebook/add'); ?>"><?php echo Auth::csrfField(); ?>
          <textarea name="numbers" rows="5" dir="ltr" placeholder="09121234567 Ali Ahmadi&#10;09351234567, Reza, VIP customer"></textarea>
          <label><?php echo h(t('pb_group')); ?><select name="group_id"><option value="0">—</option><?php foreach ($groups as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo $gid === (int)$g['id'] ? 'selected' : ''; ?>><?php echo h($g['name']); ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary btn-sm" type="submit"><?php echo h(t('add')); ?></button>
        </form>
      </div>
      <div class="card">
        <h3><?php echo h(t('import_file')); ?></h3>
        <form method="post" enctype="multipart/form-data" action="<?php echo View::url('/phonebook/import'); ?>"><?php echo Auth::csrfField(); ?>
          <label><?php echo h(t('file_csv_xlsx')); ?><input type="file" name="file" accept=".csv,.txt,.xlsx" required></label>
          <div class="row2">
            <label><?php echo h(t('pb_group')); ?><select name="group_id"><option value="0">—</option><?php foreach ($groups as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo $gid === (int)$g['id'] ? 'selected' : ''; ?>><?php echo h($g['name']); ?></option><?php endforeach; ?></select></label>
            <label><?php echo h(t('pb_or_new_group')); ?><input type="text" name="new_group" placeholder="<?php echo h(t('pb_new_group')); ?>"></label>
          </div>
          <button class="btn btn-sm" type="submit"><?php echo h(t('import_now')); ?></button>
          <p class="hint"><?php echo h(t('pb_import_hint')); ?></p>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>window.AC_PAGE = 'phonebook';</script>
