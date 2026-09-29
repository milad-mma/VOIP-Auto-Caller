<?php
$rtl = I18n::isRtl();
$u = Auth::check() ? Auth::user() : null;
$cur = Request::path();
$nav = array(
    array('/', 'nav_dashboard', 'viewer', 'M3 12l9-9 9 9M5 10v10h14V10'),
    array('/campaigns', 'nav_campaigns', 'viewer', 'M4 6h16M4 12h16M4 18h10'),
    array('/quick', 'nav_quick', 'operator', 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2'),
    array('/audio', 'nav_audio', 'viewer', 'M9 18V5l12-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm12-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'),
    array('/dnc', 'nav_dnc', 'viewer', 'M18.36 5.64a9 9 0 1 1-12.73 0M5.64 5.64l12.72 12.72'),
    array('/reports', 'nav_reports', 'viewer', 'M4 20V10M10 20V4M16 20v-7M22 20H2'),
    array('/settings', 'nav_settings', 'admin', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'),
    array('/users', 'nav_users', 'admin', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'),
    array('/apikeys', 'nav_api', 'operator', 'M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4'),
    array('/system', 'nav_system', 'admin', 'M22 12h-4l-3 9L9 3l-3 9H2'),
);
?><!DOCTYPE html>
<html lang="<?php echo I18n::lang(); ?>" dir="<?php echo $rtl ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h(View::$title !== '' ? View::$title . ' · ' : ''); ?>AutoCaller</title>
<link rel="stylesheet" href="<?php echo View::url('assets/css/app.css?v=' . AC_VERSION); ?>">
<link rel="icon" href="<?php echo View::url('assets/img/favicon.svg'); ?>" type="image/svg+xml">
<meta name="csrf" content="<?php echo h(Auth::csrfToken()); ?>">
<meta name="base" content="<?php echo h(View::url('')); ?>">
<script>(function(){try{var t=localStorage.getItem('ac-theme');if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
</head>
<body>
<div class="app">
<aside class="side">
  <div class="brand"><span class="dot"></span> AutoCaller <small>v<?php echo AC_VERSION; ?></small></div>
  <nav>
  <?php foreach ($nav as $n): if (!Auth::can($n[2])) continue; $active = ($n[0] === '/' ? $cur === '/' : strpos($cur, $n[0]) === 0); ?>
    <a href="<?php echo View::url($n[0]); ?>" class="<?php echo $active ? 'active' : ''; ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo $n[3]; ?>"/></svg>
      <span><?php echo h(t($n[1])); ?></span>
    </a>
  <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?php echo View::url('/lang/' . ($rtl ? 'en' : 'fa')); ?>" class="lang"><?php echo $rtl ? 'English' : 'فارسی'; ?></a>
    <button type="button" id="theme-toggle" class="theme-toggle" title="<?php echo h(t('toggle_theme')); ?>"><span class="ico-sun">☀</span><span class="ico-moon">☾</span></button>
  </div>
</aside>
<div class="main">
  <header class="top">
    <div class="top-title"><?php echo h(View::$title); ?></div>
    <div class="top-right">
      <span id="daemon-pill" class="pill pill-muted" title="dialer"><span class="led"></span> <span class="txt">…</span></span>
      <?php if ($u): ?>
      <a href="<?php echo View::url('/profile'); ?>" class="user"><?php echo h($u['display_name'] ?: $u['username']); ?> <small>(<?php echo h(t('role_' . $u['role'])); ?>)</small></a>
      <form method="post" action="<?php echo View::url('/logout'); ?>" class="inline"><?php echo Auth::csrfField(); ?><button class="btn btn-ghost btn-sm" type="submit"><?php echo h(t('logout')); ?></button></form>
      <?php endif; ?>
    </div>
  </header>
  <main class="content">
    <?php foreach (Flash::pull() as $f): ?>
      <div class="alert alert-<?php echo h($f['type']); ?>"><?php echo h($f['msg']); ?></div>
    <?php endforeach; ?>
    <?php echo $content; ?>
  </main>
  <footer class="foot">AutoCaller for Issabel/Elastix · <a href="https://imapro.ir" target="_blank" rel="noopener">imapro.ir</a></footer>
</div>
</div>
<script src="<?php echo View::url('assets/js/datepicker.js?v=' . AC_VERSION); ?>"></script>
<script src="<?php echo View::url('assets/js/app.js?v=' . AC_VERSION); ?>"></script>
</body>
</html>
