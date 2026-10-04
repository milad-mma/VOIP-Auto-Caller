<?php
class PhonebookController extends Controller
{
    private function groups()
    {
        return $this->db->all('SELECT g.*, (SELECT COUNT(*) FROM phonebook p WHERE p.group_id = g.id) n FROM phonebook_groups g ORDER BY g.name');
    }

    public function index()
    {
        Auth::requireLogin();
        $q = Request::str('q', '', 64);
        $gid = Request::int('group', -1, -1);
        $page = Request::int('page', 1, 1);
        $per = 50;
        $where = array('1=1');
        $params = array();
        if ($q !== '') {
            $where[] = '(p.phone LIKE ? OR p.name LIKE ? OR p.notes LIKE ?)';
            $params[] = '%' . Util::toAsciiDigits($q) . '%';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($gid > 0) {
            $where[] = 'p.group_id = ?';
            $params[] = $gid;
        } elseif ($gid === 0) {
            $where[] = 'p.group_id IS NULL';
        }
        $w = implode(' AND ', $where);
        $total = (int)$this->db->val("SELECT COUNT(*) FROM phonebook p WHERE $w", $params);
        $rows = $this->db->all("SELECT p.*, g.name group_name FROM phonebook p LEFT JOIN phonebook_groups g ON g.id = p.group_id WHERE $w ORDER BY p.id DESC LIMIT " . (($page - 1) * $per) . ',' . $per, $params);
        $this->view('phonebook', array('rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'q' => $q, 'gid' => $gid, 'groups' => $this->groups(),
            'ungrouped' => (int)$this->db->val('SELECT COUNT(*) FROM phonebook WHERE group_id IS NULL'), 'all' => (int)$this->db->val('SELECT COUNT(*) FROM phonebook')));
    }

    /** Add contacts from a textarea: "phone name" per line, or phone,name,notes */
    public function add()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $gid = Request::int('group_id', 0, 0);
        $gid = $gid > 0 && $this->db->val('SELECT id FROM phonebook_groups WHERE id = ?', array($gid)) ? $gid : null;
        $rows = array();
        foreach (preg_split('/[\r\n]+/', (string)Request::post('numbers', '')) as $l) {
            $l = trim($l);
            if ($l === '') {
                continue;
            }
            $parts = preg_split('/[,;\t]|\s{2,}|\s+/', $l, 3);
            $rows[] = array(isset($parts[0]) ? $parts[0] : '', isset($parts[1]) ? $parts[1] : '', isset($parts[2]) ? $parts[2] : '');
        }
        $st = $this->insertRows($rows, array('phone' => 0, 'name' => 1, 'notes' => 2, 'has_header' => false), $gid);
        Audit::log('phonebook.add', 'phonebook', $gid, $st['added'] . ' added');
        Flash::set('success', t('pb_import_result', $st['added'], $st['duplicate'], $st['invalid']));
        $this->redirect('/phonebook' . ($gid ? '?group=' . $gid : ''));
    }

    public function import()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('error', t('upload_error'));
            $this->redirect('/phonebook');
        }
        $gid = Request::int('group_id', 0, 0);
        $newGroup = Request::str('new_group', '', 64);
        if ($newGroup !== '') {
            $gid = $this->ensureGroup($newGroup);
        }
        $gid = $gid > 0 ? $gid : null;
        $tmp = Config::storage('uploads/pb_' . Util::token(6));
        move_uploaded_file($_FILES['file']['tmp_name'], $tmp);
        try {
            $parsed = Importer::parseFile($tmp, $_FILES['file']['name']);
        } catch (Exception $e) {
            @unlink($tmp);
            Flash::set('error', t('parse_failed') . ': ' . $e->getMessage());
            $this->redirect('/phonebook');
        }
        @unlink($tmp);
        $rows = $parsed['rows'];
        if (!$rows) {
            Flash::set('error', t('file_empty'));
            $this->redirect('/phonebook');
        }
        $g = Importer::guessMapping($rows[0]);
        $map = array('phone' => $g['phone'], 'name' => $g['name'], 'notes' => -1, 'has_header' => $g['has_header']);
        // notes column: first header containing note/comment/توضیح/یادداشت
        if ($g['has_header']) {
            foreach ($rows[0] as $i => $h) {
                if (preg_match('/(note|comment|توضیح|یادداشت)/iu', (string)$h)) {
                    $map['notes'] = $i;
                }
            }
        }
        $st = $this->insertRows($rows, $map, $gid);
        Audit::log('phonebook.import', 'phonebook', $gid, $_FILES['file']['name'] . ' ' . $st['added'] . ' added');
        Flash::set('success', t('pb_import_result', $st['added'], $st['duplicate'], $st['invalid']));
        $this->redirect('/phonebook' . ($gid ? '?group=' . $gid : ''));
    }

    private function ensureGroup($name)
    {
        $name = mb_substr(trim($name), 0, 64, 'UTF-8');
        $id = $this->db->val('SELECT id FROM phonebook_groups WHERE name = ?', array($name));
        if ($id) {
            return (int)$id;
        }
        return $this->db->insert('phonebook_groups', array('name' => $name, 'created_at' => Util::now()));
    }

    private function insertRows(array $rows, array $map, $gid)
    {
        $cc = Settings::get('country_code', '98');
        $st = array('added' => 0, 'duplicate' => 0, 'invalid' => 0);
        $header = null;
        if (!empty($map['has_header']) && $rows) {
            $header = array_shift($rows);
        }
        $now = Util::now();
        $uid = Auth::user() ? (int)Auth::user()['id'] : null;
        $ins = $this->db->pdo()->prepare('INSERT IGNORE INTO phonebook (group_id, name, phone, notes, extra, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) {
            $phone = Util::normalizePhone(isset($r[$map['phone']]) ? $r[$map['phone']] : '', $cc);
            if ($phone === '') {
                $st['invalid']++;
                continue;
            }
            $name = $map['name'] >= 0 && isset($r[$map['name']]) ? mb_substr(trim($r[$map['name']]), 0, 128, 'UTF-8') : '';
            $notes = $map['notes'] >= 0 && isset($r[$map['notes']]) ? mb_substr(trim($r[$map['notes']]), 0, 255, 'UTF-8') : '';
            $extra = array();
            if ($header) {
                foreach ($r as $i => $v) {
                    if ($i === $map['phone'] || $i === $map['name'] || $i === $map['notes'] || $v === '' || !isset($header[$i]) || $header[$i] === '') {
                        continue;
                    }
                    $extra[mb_substr($header[$i], 0, 40, 'UTF-8')] = mb_substr($v, 0, 200, 'UTF-8');
                }
            }
            $ins->execute(array($gid, $name, $phone, $notes, $extra ? Util::json($extra) : null, $uid, $now, $now));
            if ($ins->rowCount() > 0) {
                $st['added']++;
            } else {
                $st['duplicate']++;
            }
        }
        return $st;
    }

    public function update($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $row = $this->db->one('SELECT * FROM phonebook WHERE id = ?', array((int)$p['id']));
        if (!$row) {
            $this->json(array('ok' => false, 'error' => 'not_found'), 404);
        }
        $phone = Util::normalizePhone(Request::str('phone', $row['phone'], 32), Settings::get('country_code', '98'));
        if ($phone === '') {
            $this->json(array('ok' => false, 'error' => 'invalid_phone'), 400);
        }
        $gid = Request::int('group_id', (int)$row['group_id'], 0);
        $this->db->update('phonebook', array('phone' => $phone, 'name' => Request::str('name', $row['name'], 128), 'notes' => Request::str('notes', $row['notes'], 255), 'group_id' => $gid > 0 ? $gid : null, 'updated_at' => Util::now()), 'id = ?', array((int)$row['id']));
        $this->json(array('ok' => true));
    }

    public function delete($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $this->db->exec('DELETE FROM phonebook WHERE id = ?', array((int)$p['id']));
        if (Request::wantsJson()) {
            $this->json(array('ok' => true));
        }
        $this->redirect('/phonebook');
    }

    /** bulk: delete selected / move selected to group / add selected to DNC */
    public function bulk()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $ids = Request::post('ids', array());
        $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : array();
        $do = Request::str('do', '', 16);
        if (!$ids) {
            Flash::set('error', t('nothing_selected'));
            $this->redirect('/phonebook');
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($do === 'delete') {
            $n = $this->db->exec("DELETE FROM phonebook WHERE id IN ($in)", $ids);
            Flash::set('success', t('deleted') . " ($n)");
        } elseif ($do === 'move') {
            $gid = Request::int('group_id', 0, 0);
            $this->db->exec("UPDATE phonebook SET group_id = ?, updated_at = NOW() WHERE id IN ($in)", array_merge(array($gid > 0 ? $gid : null), $ids));
            Flash::set('success', t('saved'));
        } elseif ($do === 'dnc') {
            $n = 0;
            foreach ($this->db->all("SELECT phone FROM phonebook WHERE id IN ($in)", $ids) as $r) {
                if (Dnc::add($r['phone'], 'phonebook', 'manual', Auth::user()['id'])) {
                    $n++;
                }
            }
            Flash::set('success', t('dnc_added', $n));
        }
        Audit::log('phonebook.bulk', 'phonebook', null, $do . ' ' . count($ids));
        $this->redirect('/phonebook');
    }

    public function groupSave()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $name = Request::str('name', '', 64);
        $id = Request::int('id', 0, 0);
        if ($name === '') {
            Flash::set('error', t('invalid_input'));
            $this->redirect('/phonebook');
        }
        if ($id) {
            $this->db->update('phonebook_groups', array('name' => $name), 'id = ?', array($id));
        } else {
            $id = $this->ensureGroup($name);
        }
        Flash::set('success', t('saved'));
        $this->redirect('/phonebook?group=' . $id);
    }

    public function groupDelete($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $id = (int)$p['id'];
        if (Request::int('with_contacts', 0, 0, 1) === 1) {
            $this->db->exec('DELETE FROM phonebook WHERE group_id = ?', array($id));
        } else {
            $this->db->exec('UPDATE phonebook SET group_id = NULL WHERE group_id = ?', array($id));
        }
        $this->db->exec('DELETE FROM phonebook_groups WHERE id = ?', array($id));
        Audit::log('phonebook.group_delete', 'phonebook', $id);
        Flash::set('success', t('deleted'));
        $this->redirect('/phonebook');
    }

    public function export()
    {
        Auth::requireLogin();
        $gid = Request::int('group', -1, -1);
        $where = $gid > 0 ? 'WHERE p.group_id = ?' : ($gid === 0 ? 'WHERE p.group_id IS NULL' : '');
        $params = $gid > 0 ? array($gid) : array();
        $rows = $this->db->all("SELECT p.*, g.name group_name FROM phonebook p LEFT JOIN phonebook_groups g ON g.id = p.group_id $where ORDER BY p.id", $params);
        $out = array();
        foreach ($rows as $r) {
            $out[] = array($r['phone'], $r['name'], $r['group_name'], $r['notes'], Util::fdate($r['created_at']));
        }
        Exporter::send(Request::str('format', 'xlsx', 8) === 'csv' ? 'csv' : 'xlsx', 'phonebook', array(t('phone'), t('name'), t('pb_group'), t('notes'), t('created_at')), $out);
    }

    /** JSON for autocomplete (quick call) */
    public function search()
    {
        Auth::requireLogin();
        $q = Request::str('q', '', 64);
        if ($q === '') {
            $this->json(array('ok' => true, 'rows' => array()));
        }
        $rows = $this->db->all('SELECT id, name, phone, notes FROM phonebook WHERE phone LIKE ? OR name LIKE ? ORDER BY name LIMIT 15', array('%' . Util::toAsciiDigits($q) . '%', '%' . $q . '%'));
        $this->json(array('ok' => true, 'rows' => $rows));
    }

    /** Add phonebook contacts (groups or all) into a campaign */
    public function toCampaign($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = Campaign::find($p['id']);
        if (!$c) {
            $this->notFound();
        }
        $gids = Request::post('groups', array());
        $gids = is_array($gids) ? array_values(array_filter(array_map('intval', $gids), function ($x) { return $x >= 0; })) : array();
        $all = Request::int('all', 0, 0, 1) === 1;
        if (!$all && !$gids) {
            Flash::set('error', t('nothing_selected'));
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        $where = '1=1';
        $params = array();
        if (!$all) {
            $parts = array();
            foreach ($gids as $g) {
                $parts[] = $g === 0 ? 'group_id IS NULL' : 'group_id = ?';
                if ($g !== 0) {
                    $params[] = $g;
                }
            }
            $where = '(' . implode(' OR ', $parts) . ')';
        }
        $rows = array();
        foreach ($this->db->all("SELECT phone, name, notes FROM phonebook WHERE $where", $params) as $r) {
            $rows[] = array($r['phone'], $r['name']);
        }
        $st = Importer::importContacts($c['id'], $rows, array('phone' => 0, 'name' => 1, 'audio' => -1, 'has_header' => false), array('skip_dnc' => Request::int('skip_dnc', 1, 0, 1) === 1, 'dedupe' => true));
        Audit::log('campaign.import_phonebook', 'campaign', $c['id'], $st);
        Flash::set('success', t('import_result', $st['added'], $st['duplicate'], $st['invalid'], $st['dnc'], $st['audio_missing']));
        $this->redirect('/campaigns/' . $c['id']);
    }
}
