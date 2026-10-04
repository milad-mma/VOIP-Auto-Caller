/* AutoCaller date picker: Jalali (Solar Hijri) when the UI is RTL/Persian, Gregorian otherwise.
   Pure ES5, no dependencies. Replaces <input type=date> and <input type=datetime-local>:
   the visible field shows the calendar of the UI language, a hidden field keeps the ISO value the server expects. */
(function () {
  'use strict';
  function div(a, b) { return ~~(a / b); }
  function mod(a, b) { return a - ~~(a / b) * b; }
  var J = {
    jalCal: function (jy) {
      var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
      var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0], jm, jump = 0, leap, leapG, march, n, i;
      for (i = 1; i < bl; i += 1) { jm = breaks[i]; jump = jm - jp; if (jy < jm) break; leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4); jp = jm; }
      n = jy - jp;
      leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
      if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
      leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
      march = 20 + leapJ - leapG;
      if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
      leap = mod(mod(n + 1, 33) - 1, 4); if (leap === -1) leap = 4;
      return { leap: leap, gy: gy, march: march };
    },
    isLeap: function (jy) { return J.jalCal(jy).leap === 0; },
    monthLength: function (jy, jm) { return jm <= 6 ? 31 : (jm <= 11 ? 30 : (J.isLeap(jy) ? 30 : 29)); },
    g2d: function (gy, gm, gd) {
      var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
      return d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    },
    d2g: function (jdn) {
      var j = 4 * jdn + 139361631; j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
      var i = div(mod(j, 1461), 4) * 5 + 308;
      var gd = div(mod(i, 153), 5) + 1, gm = mod(div(i, 153), 12) + 1, gy = div(j, 1461) - 100100 + div(8 - gm, 6);
      return [gy, gm, gd];
    },
    j2d: function (jy, jm, jd) { var r = J.jalCal(jy); return J.g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1; },
    d2j: function (jdn) {
      var g = J.d2g(jdn), jy = g[0] - 621, r = J.jalCal(jy), jdn1f = J.g2d(g[0], 3, r.march), k = jdn - jdn1f;
      if (k >= 0) { if (k <= 185) return [jy, 1 + div(k, 31), mod(k, 31) + 1]; k -= 186; }
      else { jy -= 1; k += 179; if (r.leap === 1) k += 1; }
      return [jy, 7 + div(k, 30), mod(k, 30) + 1];
    },
    toJalali: function (gy, gm, gd) { return J.d2j(J.g2d(gy, gm, gd)); },
    toGregorian: function (jy, jm, jd) { return J.d2g(J.j2d(jy, jm, jd)); },
    // day of week of a jalali date: 0 = Saturday ... 6 = Friday
    dow: function (jy, jm, jd) { return mod(J.j2d(jy, jm, jd) + 2, 7); }
  };
  window.AC_J = J;

  var RTL = document.documentElement.getAttribute('dir') === 'rtl';
  var FA_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  var EN_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var FA_DOW = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];        // Saturday first
  var EN_DOW = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];  // Monday first
  var T = RTL ? { today: 'امروز', clear: 'پاک', ok: 'تأیید', time: 'ساعت' } : { today: 'Today', clear: 'Clear', ok: 'OK', time: 'Time' };

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function toAscii(s) { return String(s).replace(/[۰-۹]/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c); }).replace(/[٠-٩]/g, function (c) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(c); }); }
  function gLeap(y) { return (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0; }
  function gMonthLen(y, m) { return [31, gLeap(y) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1]; }

  // "calendar date" = [y,m,d] in the UI calendar; ISO = gregorian yyyy-mm-dd
  function isoToCal(iso) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); if (!m) return null; var g = [+m[1], +m[2], +m[3]]; return RTL ? J.toJalali(g[0], g[1], g[2]) : g; }
  function calToIso(c) { var g = RTL ? J.toGregorian(c[0], c[1], c[2]) : c; return g[0] + '-' + pad(g[1]) + '-' + pad(g[2]); }
  function calMonthLen(y, m) { return RTL ? J.monthLength(y, m) : gMonthLen(y, m); }
  function calValid(c) { return c && c[1] >= 1 && c[1] <= 12 && c[2] >= 1 && c[2] <= calMonthLen(c[0], c[1]) && c[0] > 1000 && c[0] < 3000; }
  function fmtCal(c) { return RTL ? c[0] + '/' + pad(c[1]) + '/' + pad(c[2]) : c[0] + '-' + pad(c[1]) + '-' + pad(c[2]); }
  function parseCal(s) { var m = /^\s*(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\s*$/.exec(toAscii(s)); if (!m) return null; var c = [+m[1], +m[2], +m[3]]; return calValid(c) ? c : null; }
  function todayCal() { var d = new Date(); return isoToCal(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())); }
  // first weekday column index of a month (0..6 in the UI's week order)
  function firstCol(y, m) {
    if (RTL) return J.dow(y, m, 1);
    var js = new Date(y, m - 1, 1).getDay(); // 0 = Sunday
    return (js + 6) % 7; // Monday first
  }

  var openPicker = null;
  function closePicker() { if (openPicker) { openPicker.parentNode.removeChild(openPicker); openPicker = null; } }
  document.addEventListener('mousedown', function (e) { if (openPicker && !openPicker.contains(e.target) && !(e.target.classList && e.target.classList.contains('ac-date'))) closePicker(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePicker(); });

  function build(input, hidden, withTime) {
    var cur = isoToCal(hidden.value) || todayCal();
    var viewY = cur[0], viewM = cur[1];
    var timeVal = (function () { var m = /T(\d{2}):(\d{2})/.exec(hidden.value || ''); return m ? [+m[1], +m[2]] : [9, 0]; })();
    var selected = isoToCal(hidden.value);

    function commit(c, close) {
      if (!c) { hidden.value = ''; input.value = ''; }
      else {
        var iso = calToIso(c);
        if (withTime) iso += 'T' + pad(timeVal[0]) + ':' + pad(timeVal[1]);
        hidden.value = iso;
        input.value = fmtCal(c) + (withTime ? ' ' + pad(timeVal[0]) + ':' + pad(timeVal[1]) : '');
      }
      var ev; try { ev = new Event('change', { bubbles: true }); } catch (e) { ev = document.createEvent('Event'); ev.initEvent('change', true, true); }
      hidden.dispatchEvent(ev);
      if (close) closePicker();
    }

    function render() {
      var p = openPicker;
      var months = RTL ? FA_MONTHS : EN_MONTHS, dows = RTL ? FA_DOW : EN_DOW;
      var h = '<div class="ac-dp-head"><button type="button" class="ac-dp-nav" data-d="-1">‹</button>' +
        '<select class="ac-dp-m">' + months.map(function (n, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === viewM ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select>' +
        '<input type="number" class="ac-dp-y" value="' + viewY + '" min="1200" max="2500">' +
        '<button type="button" class="ac-dp-nav" data-d="1">›</button></div>';
      h += '<div class="ac-dp-grid">' + dows.map(function (d) { return '<span class="ac-dp-dow">' + d + '</span>'; }).join('');
      var fc = firstCol(viewY, viewM), len = calMonthLen(viewY, viewM), t = todayCal();
      for (var i = 0; i < fc; i++) h += '<span></span>';
      for (var d = 1; d <= len; d++) {
        var cls = 'ac-dp-day';
        if (selected && selected[0] === viewY && selected[1] === viewM && selected[2] === d) cls += ' sel';
        if (t[0] === viewY && t[1] === viewM && t[2] === d) cls += ' today';
        var col = (fc + d - 1) % 7;
        if ((RTL && col === 6) || (!RTL && col === 6)) cls += ' off'; // Friday (fa) / Sunday (en)
        h += '<button type="button" class="' + cls + '" data-day="' + d + '">' + d + '</button>';
      }
      h += '</div>';
      if (withTime) h += '<div class="ac-dp-time"><label>' + T.time + '</label><input type="number" class="ac-dp-hh" min="0" max="23" value="' + pad(timeVal[0]) + '">:<input type="number" class="ac-dp-mm" min="0" max="59" value="' + pad(timeVal[1]) + '"></div>';
      h += '<div class="ac-dp-foot"><button type="button" class="ac-dp-today">' + T.today + '</button><button type="button" class="ac-dp-clear">' + T.clear + '</button>' + (withTime ? '<button type="button" class="ac-dp-ok btn btn-primary btn-sm">' + T.ok + '</button>' : '') + '</div>';
      p.innerHTML = h;
      p.querySelector('.ac-dp-m').addEventListener('change', function () { viewM = +this.value; render(); });
      p.querySelector('.ac-dp-y').addEventListener('change', function () { var y = +toAscii(this.value); if (y > 1000 && y < 3000) { viewY = y; render(); } });
      Array.prototype.forEach.call(p.querySelectorAll('.ac-dp-nav'), function (b) {
        b.addEventListener('click', function () { viewM += +b.getAttribute('data-d'); if (viewM < 1) { viewM = 12; viewY--; } if (viewM > 12) { viewM = 1; viewY++; } render(); });
      });
      Array.prototype.forEach.call(p.querySelectorAll('.ac-dp-day'), function (b) {
        b.addEventListener('click', function () { selected = [viewY, viewM, +b.getAttribute('data-day')]; commit(selected, !withTime); if (withTime) render(); });
      });
      p.querySelector('.ac-dp-today').addEventListener('click', function () { selected = todayCal(); viewY = selected[0]; viewM = selected[1]; commit(selected, !withTime); if (withTime) render(); });
      p.querySelector('.ac-dp-clear').addEventListener('click', function () { selected = null; commit(null, true); });
      if (withTime) {
        var hh = p.querySelector('.ac-dp-hh'), mm = p.querySelector('.ac-dp-mm');
        function upT() { timeVal = [Math.min(23, Math.max(0, +toAscii(hh.value) || 0)), Math.min(59, Math.max(0, +toAscii(mm.value) || 0))]; if (selected) commit(selected, false); }
        hh.addEventListener('change', upT); mm.addEventListener('change', upT);
        p.querySelector('.ac-dp-ok').addEventListener('click', function () { if (!selected) selected = todayCal(); commit(selected, true); });
      }
    }

    closePicker();
    var p = document.createElement('div'); p.className = 'ac-dp'; openPicker = p;
    document.body.appendChild(p);
    render();
    var r = input.getBoundingClientRect(), pw = p.offsetWidth;
    var left = RTL ? (r.right + window.pageXOffset - pw) : (r.left + window.pageXOffset);
    left = Math.max(8, Math.min(left, document.documentElement.clientWidth - pw - 8));
    p.style.top = (r.bottom + window.pageYOffset + 4) + 'px';
    p.style.left = left + 'px';
  }

  function enhance(orig) {
    if (orig.getAttribute('data-ac-dp')) return;
    var withTime = orig.type === 'datetime-local';
    var hidden = document.createElement('input');
    hidden.type = 'hidden'; hidden.name = orig.name; hidden.value = orig.value;
    var input = document.createElement('input');
    input.type = 'text'; input.className = (orig.className ? orig.className + ' ' : '') + 'ac-date'; input.setAttribute('dir', 'ltr'); input.autocomplete = 'off';
    input.placeholder = RTL ? (withTime ? '۱۴۰۴/۰۷/۰۷ ۰۹:۰۰' : '۱۴۰۴/۰۷/۰۷') : (withTime ? 'YYYY-MM-DD HH:MM' : 'YYYY-MM-DD');
    if (orig.required) input.required = true;
    if (orig.id) input.id = orig.id;
    var c = isoToCal(orig.value);
    if (c) { var m = /T(\d{2}):(\d{2})/.exec(orig.value); input.value = fmtCal(c) + (withTime && m ? ' ' + m[1] + ':' + m[2] : ''); }
    orig.parentNode.insertBefore(hidden, orig);
    orig.parentNode.insertBefore(input, orig);
    orig.parentNode.removeChild(orig);
    input.setAttribute('data-ac-dp', '1');
    input.addEventListener('focus', function () { build(input, hidden, withTime); });
    input.addEventListener('click', function () { if (!openPicker) build(input, hidden, withTime); });
    // typing a date by hand
    input.addEventListener('change', function () {
      var v = toAscii(input.value).trim();
      if (v === '') { hidden.value = ''; return; }
      var tm = /\s+(\d{1,2}):(\d{2})\s*$/.exec(v), date = tm ? v.replace(tm[0], '') : v;
      var cal = parseCal(date);
      if (!cal) { input.classList.add('invalid'); hidden.value = ''; return; }
      input.classList.remove('invalid');
      var iso = calToIso(cal);
      if (withTime) iso += 'T' + pad(tm ? +tm[1] : 9) + ':' + pad(tm ? +tm[2] : 0);
      hidden.value = iso;
      input.value = fmtCal(cal) + (withTime ? ' ' + iso.substr(11, 5) : '');
    });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('input[type=date], input[type=datetime-local]'), enhance);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.AC_datepicker = { enhance: enhance, fmt: function (iso) { var c = isoToCal(iso); return c ? fmtCal(c) : ''; } };
})();
