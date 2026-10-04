<?php
/**
 * Offline tests (no DB): run with  php tests/run.php
 */
define('APP_ROOT', dirname(__DIR__));
define('AC_CLI', true);
require APP_ROOT . '/app/bootstrap.php';
I18n::setLang('en');

$fails = 0;
function check($name, $cond, $info = '')
{
    global $fails;
    echo ($cond ? "  ok   " : "  FAIL ") . $name . ($cond ? '' : "  -> " . $info) . "\n";
    if (!$cond) {
        $fails++;
    }
}

echo "phone normalization\n";
$cases = array(
    '09121234567' => '09121234567', '9121234567' => '09121234567', '+989121234567' => '09121234567', '00989121234567' => '09121234567',
    '989121234567' => '09121234567', '0912 123 4567' => '09121234567', '0912-123-4567' => '09121234567', '۰۹۱۲۱۲۳۴۵۶۷' => '09121234567',
    '02188776655' => '02188776655', '2188776655' => '02188776655', '9.12345679E9' => '', 'abc' => '', '' => '', '  ' => '', '009123' => '09123',
    '+98 (912) 123-4567' => '09121234567',
);
foreach ($cases as $in => $exp) {
    $got = Util::normalizePhone($in, '98');
    check("normalize '$in'", $got === $exp, "got '$got' expected '$exp'");
}
check('dialSafe strips injection', Util::dialSafe("9012\r\nApplication: System") === '9012ApplicationSystem');
check('oneLine strips CRLF', Util::oneLine("a\r\nb\x00c") === 'a b c');

echo "xlsx numberToString\n";
check('sci', XlsxReader::numberToString('9.123456789E9') === '9123456789', XlsxReader::numberToString('9.123456789E9'));
check('plain', XlsxReader::numberToString('09121234567') === '09121234567');
check('float .0', XlsxReader::numberToString('123.0') === '123');

echo "csv parse (windows-1256 + BOM + delimiter)\n";
$tmp = tempnam(sys_get_temp_dir(), 'ac');
file_put_contents($tmp, "\xEF\xBB\xBFphone;name;audio\n09121234567;علی;promo1\n۰۹۳۵۱۲۳۴۵۶۷;;\n");
$p = Importer::parseFile($tmp, 'x.csv');
check('csv rows', count($p['rows']) === 3 && $p['rows'][1][0] === '09121234567' && $p['rows'][1][1] === 'علی', json_encode($p['rows']));
check('csv persian digits converted', $p['rows'][2][0] === '09351234567', $p['rows'][2][0]);
$g = Importer::guessMapping($p['rows'][0]);
check('guess mapping', $g['phone'] === 0 && $g['name'] === 1 && $g['audio'] === 2 && $g['has_header'], json_encode($g));
$cp1256 = iconv('UTF-8', 'Windows-1256', "شماره,نام\n09121234567,رضا\n");
file_put_contents($tmp, $cp1256);
$p = Importer::parseFile($tmp, 'y.csv');
check('cp1256 decoded', $p['rows'][1][1] === 'رضا', json_encode($p['rows']));
$g = Importer::guessMapping($p['rows'][0]);
check('guess mapping persian header', $g['phone'] === 0 && $g['name'] === 1, json_encode($g));
$g = Importer::guessMapping(array('09121234567', 'x'));
check('guess mapping no header', $g['phone'] === 0 && !$g['has_header'], json_encode($g));

echo "xlsx roundtrip\n";
$x = $tmp . '.xlsx';
check('xlsx write', XlsxWriter::write($x, array('phone', 'name', 'n'), array(array('09121234567', 'علی', '12'), array('0021', 'a,b "q"', '3.5'))));
$rows = XlsxReader::read($x);
check('xlsx read', count($rows) === 3 && $rows[1][0] === '09121234567' && $rows[1][1] === 'علی' && $rows[2][1] === 'a,b "q"' && $rows[2][2] === '3.5', json_encode($rows));
$p = Importer::parseFile($x, 'z.xlsx');
check('xlsx via parseFile', $p['type'] === 'xlsx' && count($p['rows']) === 3);
@unlink($x);
@unlink($tmp);

echo "ivr config\n";
$ivr = Campaign::cleanIvr(array('timeout_sec' => 99, 'digits' => array(
    '1' => array('action' => 'transfer', 'target' => '201; rm -rf', 'context' => 'from-internal', 'tag' => 'interested'),
    '2' => array('action' => 'dnc'), '3' => array('action' => 'none'), 'x' => array('action' => 'hangup'),
    '4' => array('action' => 'transfer', 'target' => ''), '5' => array('action' => 'play', 'audio_id' => 0), '6' => array('action' => 'play', 'audio_id' => 7),
    '*' => array('action' => 'replay'),
)));
check('ivr timeout clamped', $ivr['timeout_sec'] === 30);
check('ivr transfer target sanitized', $ivr['digits']['1']['target'] === '201rmrf', $ivr['digits']['1']['target']);
check('ivr drops none/invalid/incomplete', !isset($ivr['digits']['3']) && !isset($ivr['digits']['x']) && !isset($ivr['digits']['4']) && !isset($ivr['digits']['5']));
check('ivr keeps play/dnc/star', $ivr['digits']['6']['audio_id'] === 7 && $ivr['digits']['2']['action'] === 'dnc' && $ivr['digits']['*']['action'] === 'replay');
$rt = Campaign::parseIvr(Util::json($ivr));
check('ivr json roundtrip', $rt['digits']['1']['tag'] === 'interested');

echo "channel strings\n";
$eff = array('dial_prefix' => '9', 'channel_tech' => 'local', 'trunk_name' => '', 'channel_template' => '', 'outbound_context' => 'from-internal');
check('local', Campaign::channelFor($eff, '09121234567') === 'Local/909121234567@from-internal/n', Campaign::channelFor($eff, '09121234567'));
$eff['channel_tech'] = 'sip'; $eff['trunk_name'] = 'my trunk';
check('sip trunk', Campaign::channelFor($eff, '09121234567') === 'SIP/mytrunk/909121234567', Campaign::channelFor($eff, '09121234567'));
$eff['channel_tech'] = 'pjsip';
check('pjsip', Campaign::channelFor($eff, '09121234567') === 'PJSIP/909121234567@mytrunk');
$eff['channel_tech'] = 'custom'; $eff['channel_template'] = 'DAHDI/g0/{number}';
check('custom', Campaign::channelFor($eff, '09121234567') === 'DAHDI/g0/909121234567');
$eff['dial_prefix'] = "9\nfoo";
check('prefix injection', Campaign::channelFor($eff, '0912') === 'DAHDI/g0/9foo0912');

echo "status maps\n";
check('reason 4', CallStatus::fromReason(4) === 'answered');
check('reason 5', CallStatus::fromReason(5) === 'busy');
check('reason 3', CallStatus::fromReason(3) === 'noanswer');
check('reason 8', CallStatus::fromReason(8) === 'congestion');
check('reason 0', CallStatus::fromReason(0) === 'failed');
check('cause 17', CallStatus::fromCause(17) === 'busy');
check('cause 19', CallStatus::fromCause(19) === 'noanswer');
check('cause 1', CallStatus::fromCause(1) === 'invalid');

echo "polyfills / crypto\n";
$h = password_hash('secret123', PASSWORD_DEFAULT);
check('bcrypt', strpos($h, '$2y$') === 0 && password_verify('secret123', $h) && !password_verify('x', $h));
check('token', strlen(Util::token(16)) === 32);
check('hash_equals', hash_equals('abc', 'abc') && !hash_equals('abc', 'abd'));

echo "router\n";
$r = new Router();
$r->add('GET', '/campaigns/{id}/export', 'X@y');
$r->add('*', '/api/v1/(?P<path>.*)', 'X@y');
$ref = new ReflectionClass('Router');
$prop = $ref->getProperty('routes');
$prop->setAccessible(true);
$routes = $prop->getValue($r);
check('route regex', preg_match($routes[0]['r'], '/campaigns/12/export', $m) && $m['id'] === '12');
check('route raw regex', preg_match($routes[1]['r'], '/api/v1/campaigns/5/start', $m) && $m['path'] === 'campaigns/5/start');

echo "ami packet parsing\n";
$ref = new ReflectionClass('Ami');
$ami = $ref->newInstanceWithoutConstructor();
$buf = $ref->getProperty('buf');
$buf->setAccessible(true);
$buf->setValue($ami, "Response: Success\r\nActionID: ac1\r\nMessage: ok\r\n\r\nEvent: OriginateResponse\r\nActionID: ac-5-1-abc\r\nResponse: Success\r\nReason: 4\r\nUniqueid: 1700000000.12\r\n\r\nEvent: Hangup\r\nUniqueid: 1700000000.12\r\nCause: 16\r\nCause-txt: Normal Clearing\r\n\r\nResponse: Follows\r\nActionID: ac2\r\nOutput: line1\r\nOutput: line2\r\n\r\n");
$parse = $ref->getMethod('parse');
$parse->setAccessible(true);
$parse->invoke($ami);
$ev = $ami->events();
check('events parsed', count($ev) === 2 && $ev[0]['Event'] === 'OriginateResponse' && $ev[0]['Reason'] === '4' && $ev[1]['Cause-txt'] === 'Normal Clearing', json_encode($ev));
$resp = $ami->popResponse('ac1');
check('response parsed', $resp && $resp['Message'] === 'ok');
$resp = $ami->popResponse('ac2');
check('multi Output', $resp && isset($resp['Output_list']) && count($resp['Output_list']) === 2, json_encode($resp));

echo "schedule window (no DB holidays)\n";
$c = array('start_at' => null, 'end_at' => null, 'work_start' => '09:00', 'work_end' => '18:00', 'work_days' => '1,2,3', 'respect_holidays' => 0);
$mon10 = strtotime('next monday 10:00');
$mon20 = strtotime('next monday 20:00');
$fri10 = strtotime('next friday 10:00');
list($ok1) = Schedule::canDial($c, $mon10);
list($ok2, $r2) = Schedule::canDial($c, $mon20);
list($ok3, $r3) = Schedule::canDial($c, $fri10);
check('in window', $ok1);
check('off hours', !$ok2 && $r2 === 'off_hours', $r2);
check('off day', !$ok3 && $r3 === 'off_day', $r3);
$c['work_start'] = '22:00'; $c['work_end'] = '02:00'; $c['work_days'] = '';
list($ok4) = Schedule::canDial($c, strtotime('next monday 23:30'));
list($ok5) = Schedule::canDial($c, strtotime('next monday 12:00'));
check('window across midnight', $ok4 && !$ok5);
$c['start_at'] = date('Y-m-d H:i:s', time() + 3600);
list($ok6, $r6) = Schedule::canDial($c, time());
check('not started', !$ok6 && $r6 === 'not_started');

echo "\n" . ($fails ? "$fails FAILED" : "ALL PASSED") . "\n";
exit($fails ? 1 : 0);
