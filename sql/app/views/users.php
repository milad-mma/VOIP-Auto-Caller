<?php View::$title = t('nav_users'); $me = Auth::user(); $super = Auth::isSuper(); ?>
<?php if (!$super): ?><p class="alert alert-info"><?php echo h(t('not_root_notice')); ?></p><?php endif; ?>
<div class="card" id="issabel">
  <div class="card-head"><h3><?php echo h(t('issabel_users')); ?> <span class="badge badge-muted"><?php echo count($issabel); ?></span></h3>
    <span class="muted small"><?php echo h(t('issabel_users_hint')); ?></span></div>
  <?php if (!$issabelEnabled): ?><p class="alert alert-warn"><?php echo h(t('issabel_login_disabled')); ?> <a href="<?php echo View::url('/settings'); ?>"><?php echo h(t('nav_settings')); ?></a></p>
  <?php elseif (!$aclOk): ?><p class="alert alert-error"><?php echo h(t('issabel_acl_unreachable')); ?></p>
  <?php elseif (!$issabel): ?><p class="muted"><?php echo h(t('nothing_here')); ?></p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th><?php echo h(t('username')); ?></th><th><?php echo h(t('display_name')); ?></th><th><?php echo h(t('issabel_groups')); ?></th><th><?php echo h(t('access_state')); ?></th><th><?php echo h(t('role')); ?></th><th><?php echo h(t('active')); ?></th><th><?php echo h(t('last_login')); ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($issabel as $iu): $isMe = $iu['name'] === $me['username']; ?>
      <tr>
        <form method="post" action="<?php echo View::url('/users/issabel'); ?>"><?php echo Auth::csrfField(); ?><input type="hidden" name="username" value="<?php echo h($iu['name']); ?>">
        <td dir="ltr"><strong><?php echo h($iu['name']); ?></strong><?php if ($iu['is_admin']): ?> <span class="badge badge-running">Issabel admin</span><?php endif; ?></td>
        <td><?php echo h($iu['description']); ?><?php echo $iu['extension'] !== '' ? ' <small class="muted">ext ' . h($iu['extension']) . '</small>' : ''; ?></td>
        <td><small><?php echo h(implode(', ', $iu['groups'])); ?></small></td>
        <td><span class="badge <?php echo $iu['state'] === 'local' ? 'badge-paused' : ($iu['state'] === 'linked' ? 'badge-completed' : 'badge-muted'); ?>"><?php echo h(t('access_' . $iu['state'])); ?></span></td>
        <?php if ($iu['state'] === 'local'): ?>
          <td><?php echo h(t('role_' . $iu['role'])); ?></td><td><?php echo h($iu['active'] ? t('yes') : t('no')); ?></td>
          <td dir="ltr"><small><?php echo h(Util::fdate($iu['last_login_at'])); ?></small></td><td><small class="muted"><?php echo h(t('managed_below')); ?></small></td>
        <?php elseif (!$super && $iu['role'] === 'admin' && !$isMe): ?>
          <td><?php echo h(t('role_' . $iu['role'])); ?></td><td><?php echo h($iu['active'] ? t('yes') : t('no')); ?></td>
          <td dir="ltr"><small><?php echo h(Util::fdate($iu['last_login_at'])); ?></small></td><td><small class="muted"><?php echo h(t('only_root_manages_admins')); ?></small></td>
        <?php else: ?>
          <td><select name="role" <?php echo $isMe ? 'disabled' : ''; ?>><?php foreach (array('admin', 'operator', 'viewer') as $r): if ($r === 'admin' && !$super && $iu['role'] !== 'admin') continue; ?><option value="<?php echo $r; ?>" <?php echo $iu['role'] === $r ? 'selected' : ''; ?>><?php echo h(t('role_' . $r)); ?></option><?php endforeach; ?></select></td>
          <td><select name="is_active" <?php echo $isMe ? 'disabled' : ''; ?>><option value="1" <?php echo $iu['active'] ? 'selected' : ''; ?>><?php echo h(t('yes')); ?></option><option value="0" <?php echo !$iu['active'] ? 'selected' : ''; ?>><?php echo h(t('no')); ?></option></select></td>
          <td dir="ltr"><small><?php echo h(Util::fdate($iu['last_login_at'])); ?></small></td>
          <td class="actions"><?php if ($super && !$isMe): ?><input type="password" name="password" placeholder="<?php echo h(t('app_password')); ?><?php echo !empty($iu['has_pw']) ? ' ✓' : ''; ?>" size="10" autocomplete="new-password" title="<?php echo h(t('app_password_hint')); ?>"><?php endif; ?> <?php if (!$isMe): ?><button class="btn btn-sm" type="submit"><?php echo h(t('save')); ?></button><?php endif; ?><?php if ($super && !$isMe && $iu['state'] === 'linked'): ?> <button class="btn btn-sm btn-ghost" type="submit" name="reset" value="1" title="<?php echo h(t('reset_issabel_user_hint')); ?>" onclick="return confirm('<?php echo h(t('reset_issabel_user_hint')); ?>')">↺</button><?php endif; ?></td>
        <?php endif; ?>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="row2">
  <div class="card">
    <h3><?php echo h(t('local_users')); ?></h3>
    <table class="table">
      <thead><tr><th><?php echo h(t('username')); ?></th><th><?php echo h(t('display_name')); ?></th><th><?php echo h(t('role')); ?></th><th><?php echo h(t('source')); ?></th><th><?php echo h(t('active')); ?></th><th><?php echo h(t('last_login')); ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): $isRoot = Auth::isSuperRow($u); $peerAdmin = !$super && $u['role'] === 'admin' && (int)$u['id'] !== (int)$me['id']; $locked = ($isRoot && !$super) || (int)$u['id'] === (int)$me['id'] || $peerAdmin; $canPw = ($super || ($u['auth_source'] === 'local' && !$isRoot)) && !$peerAdmin; ?>
        <tr>
          <form method="post" action="<?php echo View::url('/users/' . $u['id']); ?>"><?php echo Auth::csrfField(); ?>
          <td dir="ltr"><strong><?php echo h($u['username']); ?></strong><?php if ($isRoot): ?> <span class="badge badge-running" title="<?php echo h(t('root_protected')); ?>">superuser</span><?php endif; ?></td>
          <td><?php if ($locked && !$super): ?><?php echo h($u['display_name']); ?><?php else: ?><input type="text" name="display_name" value="<?php echo h($u['display_name']); ?>" size="14"><?php endif; ?></td>
          <td><?php if ($locked || $isRoot): ?><?php echo h(t('role_' . $u['role'])); ?><?php else: ?><select name="role"><?php foreach (array('admin', 'operator', 'viewer') as $r): if ($r === 'admin' && !$super && $u['role'] !== 'admin') continue; ?><option value="<?php echo $r; ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>><?php echo h(t('role_' . $r)); ?></option><?php endforeach; ?></select><?php endif; ?></td>
          <td><span class="badge badge-muted"><?php echo h($u['auth_source']); ?></span><?php if ($u['auth_source'] === 'issabel' && $u['password_hash'] !== ''): ?> <small class="muted">+<?php echo h(t('app_password')); ?></small><?php endif; ?></td>
          <td><?php if ($locked || $isRoot): ?><?php echo h($u['is_active'] ? t('yes') : t('no')); ?><?php else: ?><select name="is_active"><option value="1" <?php echo $u['is_active'] ? 'selected' : ''; ?>><?php echo h(t('yes')); ?></option><option value="0" <?php echo !$u['is_active'] ? 'selected' : ''; ?>><?php echo h(t('no')); ?></option></select><?php endif; ?></td>
          <td dir="ltr"><small><?php echo h(Util::fdate($u['last_login_at'])); ?><br><?php echo h($u['last_login_ip']); ?></small></td>
          <td class="actions"><?php if ($isRoot && !$super): ?><small class="muted"><?php echo h(t('root_protected')); ?></small><?php elseif ($peerAdmin): ?><small class="muted"><?php echo h(t('only_root_manages_admins')); ?></small><?php else: ?><?php if ($canPw && (int)$u['id'] !== (int)$me['id']): ?><input type="password" name="password" placeholder="<?php echo h(t('new_password')); ?>" size="10" autocomplete="new-password"> <?php endif; ?><button class="btn btn-sm" type="submit"><?php echo h(t('save')); ?></button><?php endif; ?></td>
          </form>
        </tr>
        <?php if ((int)$u['id'] !== (int)$me['id'] && !$isRoot && !$peerAdmin): ?><tr class="sub"><td colspan="7"><form method="post" class="inline" action="<?php echo View::url('/users/' . $u['id'] . '/delete'); ?>" onsubmit="return confirm('<?php echo h(t('confirm_delete')); ?>')"><?php echo Auth::csrfField(); ?><button class="btn btn-sm btn-ghost" type="submit"><?php echo h(t('delete')); ?></button></form></td></tr><?php endif; ?>
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
      <label><?php echo h(t('role')); ?><select name="role"><?php foreach (array('viewer', 'operator', 'admin') as $r): if ($r === 'admin' && !$super) continue; ?><option value="<?php echo $r; ?>"><?php echo h(t('role_' . $r)); ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-primary" type="submit"><?php echo h(t('create')); ?></button>
    </form>
    <div class="hint">
      <p><b><?php echo h(t('role_admin')); ?>:</b> <?php echo h(t('role_admin_desc')); ?></p>
      <p><b><?php echo h(t('role_operator')); ?>:</b> <?php echo h(t('role_operator_desc')); ?></p>
      <p><b><?php echo h(t('role_viewer')); ?>:</b> <?php echo h(t('role_viewer_desc')); ?></p>
    </div>
  </div>
</div>
