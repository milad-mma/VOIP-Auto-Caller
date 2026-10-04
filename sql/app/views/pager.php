<?php if ($pages > 1): ?>
<div class="pager">
  <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
    <a class="<?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo h($url . $i); ?>"><?php echo $i; ?></a>
  <?php endfor; ?>
  <span class="muted small"><?php echo $page; ?>/<?php echo $pages; ?></span>
</div>
<?php endif; ?>
