<?php
View::$title = t('nav_reports');
$qs = 'from=' . substr($range[0], 0, 10) . '&to=' . substr($range[1], 0, 10) . '&campaign_id=' . (int)$cid;
$rate = $summary['total'] ? round($summary['answered'] * 100 / $summary['total']) : 0;
$maxDay = 1;
foreach ($byDay as $d) { $maxDay = max($maxDay, (int)$d['total']); }
$hours = array_fill(0, 24, array('total' => 0, 'answered' => 0));
foreach ($byHour as $hh) { $hours[(int)$hh['h']] = array('total' => (int)$hh['total'], 'answered' => (int)$hh['answered']); }
$maxHour = 1;
foreach ($hours as $hh) { $maxHour = max($maxHour, $hh['total']); }
?>
<div class="card">
  <form method="get" class="filters">
    <label><?php echo h(t('from')); ?> <input type="date" name="from" dir="ltr" value="<?php echo substr($range[0], 0, 10); ?>"></label>
    <label><?php echo h(t('to')); ?> <input type="date" name="to" dir="ltr" value="<?php echo substr($range[1], 0, 10); ?>"></label>
    <select name="campaign_id"><option value="0"><?php echo h(t('all_campaigns')); ?></option><?php foreach ($campaigns as $cp): ?><option value="<?php echo $cp['id']; ?>" <?php echo $cid === (int)$cp['id'] ? 'selected' : ''; ?>><?php echo h($cp['name']); ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-primary" type="submit"><?php echo h(t('filter')); ?></button>
    <a class="btn btn-sm" href="<?php echo View::url('/reports/export?format=xlsx&' . $qs); ?>">⬇ Excel</a>
    <a class="btn btn-sm" href="<?php echo View::url('/reports/export?format=csv&' . $qs); ?>">⬇ CSV</a>
  </form>
</div>
<div class="grid stats">
  <div class="card stat"><div class="stat-v"><?php echo (int)$summary['total']; ?></div><div class="stat-l"><?php echo h(t('total_calls')); ?></div></div>
  <div class="card stat ok"><div class="stat-v"><?php echo (int)$summary['answered']; ?> <small><?php echo $rate; ?>%</small></div><div class="stat-l"><?php echo h(t('answered')); ?></div></div>
  <div class="card stat warn"><div class="stat-v"><?php echo (int)$summary['noanswer']; ?></div><div class="stat-l"><?php echo h(t('st_noanswer')); ?></div></div>
  <div class="card stat"><div class="stat-v"><?php echo (int)$summary['busy']; ?></div><div class="stat-l"><?php echo h(t('st_busy')); ?></div></div>
  <div class="card stat bad"><div class="stat-v"><?php echo (int)$summary['failed']; ?></div><div class="stat-l"><?php echo h(t('st_failed')); ?></div></div>
  <div class="card stat"><div class="stat-v"><?php echo (int)$summary['machine']; ?></div><div class="stat-l"><?php echo h(t('st_machine')); ?></div></div>
  <div class="card stat"><div class="stat-v"><?php echo Util::formatDuration((int)$summary['talk']); ?></div><div class="stat-l"><?php echo h(t('talk_time')); ?> · <?php echo h(t('avg')); ?> <?php echo Util::formatDuration((int)$summary['avg_talk']); ?></div></div>
</div>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('calls_per_day')); ?></h3>
    <div class="chart" dir="ltr" data-empty="<?php echo h(t('nothing_here')); ?>">
      <?php foreach ($byDay as $d): $ht = (int)round($d['total'] * 100 / $maxDay); $ha = (int)round($d['answered'] * 100 / $maxDay); ?>
        <div class="col" title="<?php echo h($d['d']); ?>: <?php echo (int)$d['total']; ?> / <?php echo (int)$d['answered']; ?>"><div class="b1" style="height:<?php echo $ht; ?>%"></div><div class="b2" style="height:<?php echo $ha; ?>%"></div><span><?php echo substr($d['d'], 5); ?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="legend"><span class="l1"></span> <?php echo h(t('total_calls')); ?> <span class="l2"></span> <?php echo h(t('answered')); ?></div>
  </div>
  <div class="card">
    <h3><?php echo h(t('calls_per_hour')); ?></h3>
    <div class="chart" dir="ltr">
      <?php foreach ($hours as $hIdx => $hh): $ht = (int)round($hh['total'] * 100 / $maxHour); $ha = (int)round($hh['answered'] * 100 / $maxHour); ?>
        <div class="col" title="<?php echo $hIdx; ?>:00 — <?php echo $hh['total']; ?> / <?php echo $hh['answered']; ?>"><div class="b1" style="height:<?php echo $ht; ?>%"></div><div class="b2" style="height:<?php echo $ha; ?>%"></div><span><?php echo $hIdx % 3 === 0 ? $hIdx : ''; ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="row2">
  <div class="card">
    <h3><?php echo h(t('by_campaign')); ?></h3>
    <table class="table compact"><thead><tr><th><?php echo h(t('campaign')); ?></th><th><?php echo h(t('total_calls')); ?></th><th><?php echo h(t('answered')); ?></th><th><?php echo h(t('pressed_key')); ?></th><th><?php echo h(t('talk_time')); ?></th></tr></thead><tbody>
    <?php foreach ($byCampaign as $r): ?><tr><td><a href="<?php echo View::url('/campaigns/' . $r['id']); ?>"><?php echo h($r['name']); ?></a></td><td><?php echo (int)$r['total']; ?></td><td><?php echo (int)$r['answered']; ?> (<?php echo $r['total'] ? round($r['answered'] * 100 / $r['total']) : 0; ?>%)</td><td><?php echo (int)$r['pressed']; ?></td><td><?php echo Util::formatDuration($r['talk']); ?></td></tr><?php endforeach; ?>
    <?php if (!$byCampaign): ?><tr><td colspan="5" class="muted center"><?php echo h(t('nothing_here')); ?></td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="card">
    <h3><?php echo h(t('dtmf_distribution')); ?></h3>
    <?php if (!$dtmf): ?><p class="muted"><?php echo h(t('no_responses_yet')); ?></p><?php endif; ?>
    <div class="chips"><?php foreach ($dtmf as $d): ?><span class="chip big"><?php echo h(t('key')); ?> <b><?php echo h($d['k']); ?></b>: <?php echo (int)$d['n']; ?></span> <?php endforeach; ?></div>
  </div>
</div>
