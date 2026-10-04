<?php View::$title = t('nav_api'); ?>
<?php if ($newKey): ?>
<div class="alert alert-success"><?php echo h(t('new_key_notice')); ?><br><code class="key" dir="ltr"><?php echo h($newKey); ?></code></div>
<?php endif; ?>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('api_keys')); ?></h3>
    <table class="table">
      <thead><tr><th><?php echo h(t('name')); ?></th><th><?php echo h(t('key')); ?></th><th><?php echo h(t('user')); ?></th><th><?php echo h(t('allowed_ips')); ?></th><th><?php echo h(t('last_used')); ?></th><th></th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted center"><?php echo h(t('nothing_here')); ?></td></tr><?php endif; ?>
      <?php foreach ($rows as $k): ?><tr><td><?php echo h($k['name']); ?></td><td dir="ltr"><code><?php echo h($k['key_prefix']); ?>…</code></td><td><?php echo h($k['username']); ?></td><td dir="ltr"><small><?php echo h($k['allowed_ips'] ?: '*'); ?></small></td><td dir="ltr"><small><?php echo h(Util::fdate($k['last_used_at'])); ?></small></td><td><form method="post" class="inline" action="<?php echo View::url('/apikeys/' . $k['id'] . '/delete'); ?>" onsubmit="return confirm('<?php echo h(t('confirm_delete')); ?>')"><?php echo Auth::csrfField(); ?><button class="btn btn-sm btn-ghost" type="submit">✕</button></form></td></tr><?php endforeach; ?>
      </tbody>
    </table>
    <form method="post" action="<?php echo View::url('/apikeys'); ?>" class="filters"><?php echo Auth::csrfField(); ?>
      <input type="text" name="name" placeholder="<?php echo h(t('key_name')); ?>" required>
      <input type="text" name="allowed_ips" dir="ltr" placeholder="<?php echo h(t('allowed_ips_placeholder')); ?>">
      <button class="btn btn-primary btn-sm" type="submit"><?php echo h(t('create_key')); ?></button>
    </form>
  </div>
  <div class="card">
    <h3><?php echo h(t('api_docs')); ?></h3>
    <p class="hint"><?php echo h(t('api_intro')); ?> <code dir="ltr">X-API-Key</code></p>
<pre dir="ltr" class="code"># <?php echo h(t('api_ex_call')); ?>

curl -X POST <?php echo h($base); ?>/calls \
  -H "X-API-Key: ack_xxx" -H "Content-Type: application/json" \
  -d '{"phone":"09121234567","audio":"promo1","transfer":"201"}'

# <?php echo h(t('api_ex_status')); ?>

curl <?php echo h($base); ?>/calls/123 -H "X-API-Key: ack_xxx"

# <?php echo h(t('api_ex_campaign')); ?>

curl -X POST <?php echo h($base); ?>/campaigns \
  -H "X-API-Key: ack_xxx" -H "Content-Type: application/json" \
  -d '{"name":"Promo","audio":"promo1","start":true,
       "concurrent":3,"max_retries":1,
       "work_start":"09:00","work_end":"20:00",
       "ivr":{"digits":{"1":{"action":"transfer","target":"201","tag":"interested"},
                        "2":{"action":"dnc"}}},
       "contacts":[{"phone":"09121234567","name":"Ali"},
                   {"phone":"09351234567","audio":"promo2"}]}'

# <?php echo h(t('api_ex_results')); ?>

curl "<?php echo h($base); ?>/campaigns/5/contacts?status=completed" -H "X-API-Key: ack_xxx"

# <?php echo h(t('api_ex_other')); ?>

GET  /status            GET /audio          GET /campaigns?status=running
POST /campaigns/{id}/start|pause|resume|stop
GET  /dnc?phone=0912…   POST /dnc {"phones":[…]}   DELETE /dnc/0912…
</pre>
  </div>
</div>
