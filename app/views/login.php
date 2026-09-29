<?php $rtl = I18n::isRtl(); ?><!DOCTYPE html>
<html lang="<?php echo I18n::lang(); ?>" dir="<?php echo $rtl ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h(t('login')); ?> · AutoCaller</title>
<link rel="stylesheet" href="<?php echo View::url('assets/css/app.css?v=' . AC_VERSION); ?>">
<link rel="icon" href="<?php echo View::url('assets/img/favicon.svg'); ?>" type="image/svg+xml">
</head>
<body class="login-body">
<div class="login-box">
  <div class="brand big"><span class="dot"></span> AutoCaller</div>
  <p class="muted"><?php echo h(t('login_intro')); ?></p>
  <?php if (!empty($error)): ?><div class="alert alert-error"><?php echo h($error); ?></div><?php endif; ?>
  <form method="post" action="<?php echo View::url('/login'); ?>" autocomplete="off">
    <?php echo Auth::csrfField(); ?>
    <label><?php echo h(t('username')); ?><input type="text" name="username" value="<?php echo h(isset($username) ? $username : ''); ?>" required autofocus dir="ltr"></label>
    <label><?php echo h(t('password')); ?><input type="password" name="password" required dir="ltr"></label>
    <button class="btn btn-primary btn-block" type="submit"><?php echo h(t('login')); ?></button>
  </form>
  <p class="muted small"><?php echo h(t('login_root_hint')); ?><?php if (Settings::get('issabel_login') === '1'): ?> <?php echo h(t('login_issabel_hint')); ?><?php endif; ?></p>
  <div class="login-lang"><a href="<?php echo View::url('/lang/' . ($rtl ? 'en' : 'fa')); ?>"><?php echo $rtl ? 'English' : 'فارسی'; ?></a></div>
</div>
</body>
</html>
