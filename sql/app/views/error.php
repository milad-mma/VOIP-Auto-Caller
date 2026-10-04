<?php View::$title = (string)$code; ?>
<div class="card narrow center">
  <h2><?php echo (int)$code; ?></h2>
  <p><?php echo h($message); ?></p>
  <a class="btn" href="<?php echo View::url('/'); ?>"><?php echo h(t('back_home')); ?></a>
</div>
