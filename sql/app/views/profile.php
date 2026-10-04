<?php View::$title = t('profile'); ?>
<div class="card narrow">
  <h3><?php echo h($u['username']); ?> <span class="badge badge-muted"><?php echo h(t('role_' . $u['role'])); ?></span></h3>
  <form method="post" action="<?php echo View::url('/profile'); ?>" autocomplete="off"><?php echo Auth::csrfField(); ?>
    <label><?php echo h(t('display_name')); ?><input type="text" name="display_name" value="<?php echo h($u['display_name']); ?>"></label>
    <?php if ($u['auth_source'] === 'local' || $u['password_hash'] !== ''): ?>
    <label><?php echo h(t('current_password')); ?><input type="password" name="current_password" autocomplete="current-password"></label>
    <label><?php echo h(t('new_password')); ?><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
    <?php else: ?><p class="hint"><?php echo h(t('issabel_user_no_password')); ?></p><?php endif; ?>
    <button class="btn btn-primary" type="submit"><?php echo h(t('save')); ?></button>
  </form>
</div>
