<?php View::$title = t('nav_users'); $me = Auth::user(); ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('users')); ?></h3>
    <table class="table">
      <thead><tr><th><?php echo h(t('username')); ?></th><th><?php echo h(t('display_name')); ?></th><th><?php echo h(t('role')); ?></th><th><?php echo h(t('source')); ?></th><th><?php echo h(t('active')); ?></th><th><?php echo h(t('last_login')); ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <form method="post" action="<?php echo View::url('/users/' . $u['id']); ?>"><?php echo Auth::csrfField(); ?>
          <td dir="ltr"><strong><?php echo h($u['username']); ?></strong></td>
          <td><input type="text" name="display_name" value="<?php echo h($u['display_name']); ?>" size="14"></td>
          <td><select name="role" <?php echo (int)$u['id'] === (int)$me['id'] ? 'disabled' : ''; ?>><?php foreach (array('admin', 'operator', 'viewer') as $r): ?><option value="<?php echo $r; ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>><?php echo h(t('role_' . $r)); ?></option><?php endforeach; ?></select></td>
          <td><span class="badge badge-muted"><?php echo h($u['auth_source']); ?></span></td>
          <td><select name="is_active" <?php echo (int)$u['id'] === (int)$me['id'] ? 'disabled' : ''; ?>><option value="1" <?php echo $u['is_active'] ? 'selected' : ''; ?>><?php echo h(t('yes')); ?></option><option value="0" <?php echo !$u['is_active'] ? 'selected' : ''; ?>><?php echo h(t('no')); ?></option></select></td>
          <td dir="ltr"><small><?php echo h($u['last_login_at']); ?><br><?php echo h($u['last_login_ip']); ?></small></td>
          <td class="actions"><input type="password" name="password" placeholder="<?php echo h(t('new_password')); ?>" size="10" autocomplete="new-password"> <button class="btn btn-sm" type="submit"><?php echo h(t('save')); ?></button></td>
          </form>
        </tr>
        <?php if ((int)$u['id'] !== (int)$me['id']): ?><tr class="sub"><td colspan="7"><form method="post" class="inline" action="<?php echo View::url('/users/' . $u['id'] . '/delete'); ?>" onsubmit="return confirm('<?php echo h(t('confirm_delete')); ?>')"><?php echo Auth::csrfField(); ?><button class="btn btn-sm btn-ghost" type="submit"><?php echo h(t('delete')); ?></button></form></td></tr><?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3><?php echo h(t('new_user')); ?></h3>
    <form method="post" action="<?php echo View::url('/users'); ?>" autocomplete="off"><?php echo Auth::csrfField(); ?>
      <label><?php echo h(t('username')); ?> *<input type="text" name="username" dir="ltr" required pattern="[A-Za-z0-9_.@\-]{2,64}"></label>
      <label><?php echo h(t('display_name')); ?><input type="text" name="display_name"></label>
      <label><?php echo h(t('password')); ?> *<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label><?php echo h(t('role')); ?><select name="role"><?php foreach (array('viewer', 'operator', 'admin') as $r): ?><option value="<?php echo $r; ?>"><?php echo h(t('role_' . $r)); ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-primary" type="submit"><?php echo h(t('create')); ?></button>
    </form>
    <div class="hint">
      <p><b><?php echo h(t('role_admin')); ?>:</b> <?php echo h(t('role_admin_desc')); ?></p>
      <p><b><?php echo h(t('role_operator')); ?>:</b> <?php echo h(t('role_operator_desc')); ?></p>
      <p><b><?php echo h(t('role_viewer')); ?>:</b> <?php echo h(t('role_viewer_desc')); ?></p>
    </div>
  </div>
</div>
