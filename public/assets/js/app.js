/* AutoCaller front-end (ES5, no dependencies) */
(function () {
  'use strict';
  var BASE = (document.querySelector('meta[name=base]') || {}).content || '';
  var CSRF = (document.querySelector('meta[name=csrf]') || {}).content || '';

  function $(s, root) { return (root || document).querySelector(s); }
  function $$(s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmtDur(s) { s = parseInt(s || 0, 10); if (s < 60) return s + 's'; return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }

  function xhr(method, url, data, cb) {
    var x = new XMLHttpRequest();
    x.open(method, url, true);
    x.setRequestHeader('Accept', 'application/json');
    x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    x.setRequestHeader('X-CSRF-Token', CSRF);
    x.onreadystatechange = function () {
      if (x.readyState !== 4) return;
      var j = null;
      try { j = JSON.parse(x.responseText); } catch (e) { j = { ok: false, error: 'bad_json', raw: x.responseText }; }
      cb(j, x.status);
    };
    if (data) {
      x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      var parts = [];
      for (var k in data) if (data.hasOwnProperty(k)) parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
      parts.push('_csrf=' + encodeURIComponent(CSRF));
      x.send(parts.join('&'));
    } else {
      x.send(null);
    }
  }

  // ---- wrap every table so it scrolls horizontally on small screens instead of breaking the layout ----
  $$('table.table').forEach(function (t) {
    if (t.parentNode && t.parentNode.classList && t.parentNode.classList.contains('table-wrap')) return;
    var w = document.createElement('div'); w.className = 'table-wrap';
    t.parentNode.insertBefore(w, t); w.appendChild(t);
  });

  // ---- Jalali date display for the Persian UI (dates coming from JSON) ----
  var RTL = document.documentElement.getAttribute('dir') === 'rtl';
  function toJalali(gy, gm, gd) { return window.AC_J.toJalali(gy, gm, gd); }
  function fdate(s) {
    if (!s) return '';
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(s);
    if (!m) return s;
    var d = RTL ? (function () { var j = toJalali(+m[1], +m[2], +m[3]); return j[0] + '/' + ('0' + j[1]).slice(-2) + '/' + ('0' + j[2]).slice(-2); })() : m[1] + '-' + m[2] + '-' + m[3];
    return m[4] ? d + ' ' + m[4] + ':' + m[5] : d;
  }
  window.AC_fdate = fdate;

  // ---- theme toggle ----
  var tt = $('#theme-toggle');
  if (tt) tt.addEventListener('click', function () {
    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (dark) document.documentElement.removeAttribute('data-theme'); else document.documentElement.setAttribute('data-theme', 'dark');
    try { localStorage.setItem('ac-theme', dark ? 'light' : 'dark'); } catch (e) {}
  });

  // ---- daemon pill in header (all pages) ----
  var pill = $('#daemon-pill');
  function refreshPill(d) {
    if (!pill) return;
    pill.className = 'pill ' + (d.alive && d.ami ? 'pill-ok' : 'pill-bad');
    $('.txt', pill).textContent = d.alive ? (d.ami ? 'Dialer ✓ ' + d.active_calls : 'AMI ✗') : 'Dialer ✗';
    pill.title = d.error || '';
  }
  var livePollers = [];
  function pollLive() {
    xhr('GET', BASE + '/dashboard/live.json', null, function (j) {
      if (!j || !j.ok) return;
      refreshPill(j.daemon);
      for (var i = 0; i < livePollers.length; i++) livePollers[i](j);
    });
  }
  if (pill) { pollLive(); setInterval(pollLive, 5000); }

  // ---- dashboard ----
  if (window.AC_PAGE === 'dashboard') {
    livePollers.push(function (j) {
      var tb = $('#live-calls tbody');
      $('#live-count').textContent = j.active.length;
      if (!j.active.length) { tb.innerHTML = '<tr><td colspan="4" class="muted center">—</td></tr>'; }
      else {
        tb.innerHTML = j.active.map(function (a) {
          return '<tr><td dir="ltr">' + esc(a.phone) + (a.name ? ' <small class="muted">' + esc(a.name) + '</small>' : '') + '</td><td><a href="' + BASE + '/campaigns/' + a.campaign_id + '">' + esc(a.campaign) + '</a></td><td><span class="badge badge-' + esc(a.status) + '">' + esc(a.status) + (a.dtmf ? ' ' + esc(a.dtmf) : '') + '</span></td><td>' + fmtDur(a.elapsed) + '</td></tr>';
        }).join('');
      }
      var rows = {};
      $$('#campaign-live tbody tr[data-id]').forEach(function (tr) { rows[tr.getAttribute('data-id')] = tr; });
      j.campaigns.forEach(function (c) {
        var tr = rows[c.id]; if (!tr) return;
        var pct = c.total_contacts ? Math.round(c.cnt_done * 100 / c.total_contacts) : 0;
        $('.bar-in', tr).style.width = pct + '%';
        $('.prog', tr).textContent = c.cnt_done + '/' + c.total_contacts;
        $('.ans', tr).textContent = c.cnt_answered;
        var b = $('.badge', tr); b.className = 'badge badge-' + c.status;
      });
      if (j.today) {
        $$('#today-stats [data-k]').forEach(function (el) {
          var k = el.getAttribute('data-k'); var v = j.today[k] || 0;
          el.textContent = k === 'talk' ? fmtDur(v) : v;
        });
      }
    });
  }

  // ---- campaign form: IVR row toggles ----
  if (window.AC_PAGE === 'campaign-form') {
    $$('.ivr-row').forEach(function (tr) {
      var sel = $('.ivr-action', tr);
      function upd() {
        $$('.ivr-target', tr).forEach(function (s) { s.hidden = true; });
        var t = $('.ivr-t-' + sel.value, tr); if (t) t.hidden = false;
      }
      sel.addEventListener('change', upd); upd();
    });
  }

  // ---- campaign show: contacts table + stats ----
  if (window.AC_PAGE === 'campaign-show') {
    var cid = window.AC_CAMPAIGN, page = 1, timer = null;
    var q = $('#ct-q'), st = $('#ct-status'), auto = $('#ct-auto');
    function loadContacts() {
      var url = BASE + '/campaigns/' + cid + '/contacts.json?page=' + page + '&status=' + encodeURIComponent(st.value) + '&q=' + encodeURIComponent(q.value);
      xhr('GET', url, null, function (j) {
        if (!j || !j.ok) return;
        var tb = $('#ct-table tbody');
        if (!j.rows.length) { tb.innerHTML = '<tr><td colspan="10" class="muted center">—</td></tr>'; }
        else {
          tb.innerHTML = j.rows.map(function (r) {
            var note = r.last_error || '';
            if (r.next_attempt_at && r.status === 'pending') note = '⏱ ' + fdate(r.next_attempt_at);
            if (r.hangup_cause && r.status !== 'completed') note = (note ? note + ' · ' : '') + r.hangup_cause;
            var act = '';
            if (window.AC_CAN_OP) {
              act = '<td class="ct-actions">';
              if (r.status === 'pending') act += '<button class="btn btn-sm btn-ghost" data-do="cancel" data-id="' + r.id + '" title="cancel">✕</button>';
              if (['pending', 'dialing', 'answered'].indexOf(r.status) === -1) act += '<button class="btn btn-sm btn-ghost" data-do="retry" data-id="' + r.id + '" title="retry">↻</button>';
              act += '<button class="btn btn-sm btn-ghost" data-do="dnc" data-id="' + r.id + '" title="DNC">⛔</button>';
              if (['dialing', 'answered'].indexOf(r.status) === -1) act += '<button class="btn btn-sm btn-ghost" data-do="delete" data-id="' + r.id + '" title="delete">🗑</button>';
              act += '</td>';
            }
            return '<tr><td dir="ltr"><strong>' + esc(r.phone) + '</strong></td><td>' + esc(r.name) + '</td><td><span class="badge badge-' + esc(r.status) + '">' + esc(window.AC_ST[r.status] || r.status) + '</span>' + (r.amd_result ? ' <small class="muted">' + esc(r.amd_result) + '</small>' : '') + '</td><td>' + r.attempts + '</td><td dir="ltr"><small>' + esc(fdate(r.last_attempt_at)) + '</small></td><td>' + (r.duration_sec ? fmtDur(r.duration_sec) : '') + '</td><td>' + esc(r.dtmf || '') + '</td><td>' + esc(r.result_tag || '') + '</td><td><small class="muted">' + esc(note) + '</small></td>' + act + '</tr>';
          }).join('');
        }
        var pages = Math.max(1, Math.ceil(j.total / j.per)), pg = $('#ct-pager'), html = '';
        if (pages > 1) {
          for (var i = Math.max(1, page - 3); i <= Math.min(pages, page + 3); i++) html += '<a href="#" data-p="' + i + '" class="' + (i === page ? 'active' : '') + '">' + i + '</a>';
          html += '<span class="muted small">' + page + '/' + pages + ' · ' + j.total + '</span>';
        } else html = '<span class="muted small">' + j.total + '</span>';
        pg.innerHTML = html;
      });
      xhr('GET', BASE + '/campaigns/' + cid + '/stats.json', null, function (j) {
        if (!j || !j.ok) return;
        var s = j.stats;
        var map = { total: s.total, pending: s.pending, active: s.dialing + s.answered, completed: s.completed, noanswer: s.noanswer, busy: s.busy, failed: s.failed + s.congestion + s.invalid, dnc: s.dnc + s.cancelled + s.machine };
        $$('#c-stats [data-k]').forEach(function (el) { el.textContent = map[el.getAttribute('data-k')]; });
        $('#c-bar').style.width = s.pct + '%'; $('#c-pct').textContent = s.pct + '%';
        var b = $('#c-status'); if (b && b.className.indexOf('badge-' + j.status) === -1) { location.reload(); }
      });
    }
    $('#ct-pager').addEventListener('click', function (e) { var a = e.target.closest ? e.target.closest('a[data-p]') : null; if (a) { e.preventDefault(); page = parseInt(a.getAttribute('data-p'), 10); loadContacts(); } });
    $('#ct-table').addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('button[data-do]') : null; if (!b) return;
      var d = b.getAttribute('data-do');
      if ((d === 'delete' || d === 'dnc') && !confirm('?')) return;
      xhr('POST', BASE + '/campaigns/' + cid + '/contacts/' + b.getAttribute('data-id') + '/action', { 'do': d }, function () { loadContacts(); });
    });
    q.addEventListener('input', function () { page = 1; clearTimeout(timer); timer = setTimeout(loadContacts, 300); });
    st.addEventListener('change', function () { page = 1; loadContacts(); });
    loadContacts();
    setInterval(function () { if (auto.checked && !document.hidden) loadContacts(); }, 4000);
  }

  // ---- phonebook inline edit ----
  if (window.AC_PAGE === 'phonebook') {
    var all = $('#pb-all'); if (all) all.addEventListener('change', function () { $$('#pb-table input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; }); });
    var tmr = {};
    $$('#pb-table .pb-f').forEach(function (f) {
      f.addEventListener('change', function () {
        var tr = f.closest('tr'), id = tr.getAttribute('data-id'), data = {};
        $$('.pb-f', tr).forEach(function (x) { data[x.getAttribute('data-f')] = x.value; });
        clearTimeout(tmr[id]);
        tmr[id] = setTimeout(function () { xhr('POST', BASE + '/phonebook/' + id + '/update', data, function (j) { tr.style.outline = j && j.ok ? '2px solid var(--ok)' : '2px solid var(--bad)'; setTimeout(function () { tr.style.outline = ''; }, 800); }); }, 250);
      });
    });
    $$('#pb-table .pb-del').forEach(function (b) {
      b.addEventListener('click', function () { if (!confirm('?')) return; var tr = b.closest('tr'); xhr('POST', BASE + '/phonebook/' + tr.getAttribute('data-id') + '/delete', {}, function (j) { if (j && j.ok) tr.parentNode.removeChild(tr); }); });
    });
  }

  // ---- quick call: phonebook suggestions ----
  if (window.AC_PAGE === 'quick') {
    var qp = $('#quick-phone'), qs = $('#quick-suggest'), qt = null;
    if (qp && qs) {
      qp.addEventListener('input', function () {
        clearTimeout(qt); var v = qp.value.trim(); if (v.length < 2) { qs.hidden = true; return; }
        qt = setTimeout(function () {
          xhr('GET', BASE + '/phonebook/search.json?q=' + encodeURIComponent(v), null, function (j) {
            if (!j || !j.ok || !j.rows.length) { qs.hidden = true; return; }
            qs.innerHTML = j.rows.map(function (r) { return '<div class="suggest-item" data-phone="' + esc(r.phone) + '" data-name="' + esc(r.name) + '"><b dir="ltr">' + esc(r.phone) + '</b> ' + esc(r.name) + (r.notes ? ' <small class="muted">' + esc(r.notes) + '</small>' : '') + '</div>'; }).join('');
            qs.hidden = false;
          });
        }, 200);
      });
      qs.addEventListener('mousedown', function (e) {
        var it = e.target.closest('.suggest-item'); if (!it) return;
        qp.value = it.getAttribute('data-phone'); var n = document.querySelector('#quick-form input[name=name]'); if (n && !n.value) n.value = it.getAttribute('data-name'); qs.hidden = true;
      });
      qp.addEventListener('blur', function () { setTimeout(function () { qs.hidden = true; }, 150); });
    }
  }

  // ---- settings ----
  if (window.AC_PAGE === 'settings') {
    var ct = document.querySelector('select[name=channel_tech]');
    function updCt() {
      var v = ct.value, ft = $('#f-trunk'), fp = $('#f-template');
      if (ft) ft.style.opacity = (v === 'sip' || v === 'pjsip' || v === 'custom') ? '1' : '.45';
      var tp = $('#trunk-pool'); if (tp) tp.style.outline = v === 'pool' ? '2px solid var(--pri)' : '';
      if (fp) fp.hidden = (v !== 'custom');
    }
    if (ct) { ct.addEventListener('change', updCt); updCt(); }
    var tb = $('#test-ami');
    if (tb) tb.addEventListener('click', function () {
      $('#test-ami-result').textContent = '…';
      xhr('POST', BASE + '/settings/test-ami', {}, function (j) { $('#test-ami-result').textContent = (j.ok ? '✓ ' : '✗ ') + (j.message || ''); });
    });
  }

  // ---- system logs ----
  if (window.AC_PAGE === 'system') {
    $$('.log-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        xhr('GET', BASE + '/system/log/' + b.getAttribute('data-log') + '?lines=300', null, function (j) { $('#log-view').textContent = j.log || '(empty)'; });
      });
    });
  }

  // closest() polyfill for old browsers
  if (!Element.prototype.closest) {
    Element.prototype.closest = function (s) { var el = this; while (el && el.nodeType === 1) { if (el.matches ? el.matches(s) : el.msMatchesSelector(s)) return el; el = el.parentElement; } return null; };
  }
})();
