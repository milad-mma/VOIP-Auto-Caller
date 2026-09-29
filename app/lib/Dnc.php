<?php
class Dnc
{
    public static function has($phone)
    {
        $p = Util::normalizePhone($phone, Settings::get('country_code', '98'));
        if ($p === '') {
            return false;
        }
        return (bool)Db::get()->val('SELECT COUNT(*) FROM dnc WHERE phone = ?', array($p));
    }

    public static function add($phone, $reason = '', $source = 'manual', $userId = null)
    {
        $p = Util::normalizePhone($phone, Settings::get('country_code', '98'));
        if ($p === '') {
            return false;
        }
        Db::get()->run(
            'INSERT INTO dnc (phone, reason, source, created_by, created_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)',
            array($p, Util::oneLine($reason, 250), $source, $userId === null ? null : (int)$userId, Util::now())
        );
        // cancel pending contacts with this number in any active campaign
        Db::get()->exec("UPDATE campaign_contacts SET status = 'dnc', updated_at = NOW() WHERE phone = ? AND status = 'pending'", array($p));
        return $p;
    }

    public static function remove($phone)
    {
        $p = Util::normalizePhone($phone, Settings::get('country_code', '98'));
        return Db::get()->exec('DELETE FROM dnc WHERE phone = ?', array($p));
    }
}
