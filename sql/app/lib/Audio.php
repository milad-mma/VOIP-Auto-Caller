<?php
/**
 * Audio prompt management: upload, convert to 8kHz/16bit/mono WAV, duration.
 */
class Audio
{
    public static function allowedExt()
    {
        return array('wav', 'mp3', 'gsm', 'ogg', 'm4a', 'aac', 'flac', 'wma', 'ulaw', 'alaw', 'sln');
    }

    /**
     * @param array $file entry from $_FILES
     * @param string $logicalName name used in CSV audio column; derived from file name if empty
     * @return array audio row
     * @throws RuntimeException
     */
    public static function upload(array $file, $logicalName = '', $userId = null)
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(t('upload_error') . ' (' . (isset($file['error']) ? $file['error'] : '?') . ')');
        }
        if ($file['size'] > 60 * 1024 * 1024) {
            throw new RuntimeException(t('file_too_big'));
        }
        $orig = (string)$file['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, self::allowedExt(), true)) {
            throw new RuntimeException(t('bad_audio_type'));
        }
        $logicalName = trim((string)$logicalName);
        if ($logicalName === '') {
            $logicalName = pathinfo($orig, PATHINFO_FILENAME);
        }
        $logicalName = self::cleanName($logicalName);
        $db = Db::get();
        if ($db->val('SELECT id FROM audio_files WHERE name = ?', array($logicalName))) {
            throw new RuntimeException(t('audio_name_exists'));
        }
        $tmpIn = Config::storage('tmp/in_' . Util::token(6) . '.' . $ext);
        if (!move_uploaded_file($file['tmp_name'], $tmpIn)) {
            throw new RuntimeException(t('upload_error'));
        }
        $id = $db->insert('audio_files', array(
            'name' => $logicalName, 'original_name' => mb_substr($orig, 0, 255, 'UTF-8'), 'path' => 'audio/pending',
            'created_by' => $userId === null ? null : (int)$userId, 'created_at' => Util::now(),
        ));
        $rel = 'audio/ac_' . $id . '.wav';
        $dest = Config::storage($rel);
        try {
            self::convert($tmpIn, $dest);
        } catch (Exception $e) {
            @unlink($tmpIn);
            $db->exec('DELETE FROM audio_files WHERE id = ?', array($id));
            throw $e;
        }
        @unlink($tmpIn);
        @chmod($dest, 0664);
        $db->update('audio_files', array(
            'path' => $rel, 'duration_sec' => self::duration($dest), 'size_bytes' => (int)filesize($dest),
        ), 'id = ?', array($id));
        return $db->one('SELECT * FROM audio_files WHERE id = ?', array($id));
    }

    public static function cleanName($n)
    {
        $n = Util::toAsciiDigits($n);
        $n = preg_replace('/[^\p{L}\p{N}_\-]/u', '_', $n);
        $n = trim($n, '_');
        if ($n === '') {
            $n = 'audio_' . Util::token(3);
        }
        return mb_substr($n, 0, 100, 'UTF-8');
    }

    /** Convert anything to 8kHz 16-bit mono PCM WAV (Asterisk native "wav" format). */
    public static function convert($in, $out)
    {
        $sox = Util::which('sox');
        $ffmpeg = Util::which('ffmpeg');
        $rc = 1;
        $log = '';
        if ($sox) {
            $log = Util::exec($sox . ' ' . escapeshellarg($in) . ' -r 8000 -c 1 -b 16 -e signed-integer ' . escapeshellarg($out), $rc);
            if ($rc === 0 && is_file($out) && filesize($out) > 100) {
                return true;
            }
            @unlink($out);
        }
        if ($ffmpeg) {
            $log = Util::exec($ffmpeg . ' -y -i ' . escapeshellarg($in) . ' -ar 8000 -ac 1 -acodec pcm_s16le ' . escapeshellarg($out), $rc);
            if ($rc === 0 && is_file($out) && filesize($out) > 100) {
                return true;
            }
            @unlink($out);
        }
        // last resort: asterisk's own converter (wav/gsm/ulaw/alaw/sln only)
        $ast = Config::get('app', 'asterisk_bin');
        if (is_executable($ast)) {
            $log = Util::exec(escapeshellcmd($ast) . ' -rx ' . escapeshellarg('file convert ' . $in . ' ' . $out), $rc);
            if (is_file($out) && filesize($out) > 100) {
                return true;
            }
        }
        Logger::error('audio convert failed: ' . $log);
        throw new RuntimeException(t('convert_failed') . ' ' . Util::oneLine($log, 160));
    }

    /** seconds, from the WAV header (8kHz 16bit mono = 16000 bytes/sec) */
    public static function duration($wav)
    {
        $size = @filesize($wav);
        if (!$size) {
            return 0;
        }
        $fp = fopen($wav, 'rb');
        $hdr = fread($fp, 44);
        fclose($fp);
        if (strlen($hdr) >= 36 && substr($hdr, 0, 4) === 'RIFF') {
            $u = unpack('vchannels/Vrate/VbyteRate', substr($hdr, 22, 10));
            if (!empty($u['byteRate'])) {
                return (int)round(($size - 44) / $u['byteRate']);
            }
        }
        return (int)round(($size - 44) / 16000);
    }

    public static function delete($id)
    {
        $db = Db::get();
        $a = $db->one('SELECT * FROM audio_files WHERE id = ?', array((int)$id));
        if (!$a) {
            return false;
        }
        $inUse = (int)$db->val("SELECT COUNT(*) FROM campaigns WHERE audio_id = ? AND status IN ('running','paused','scheduled')", array((int)$id));
        if ($inUse) {
            throw new RuntimeException(t('audio_in_use'));
        }
        $db->exec('UPDATE campaigns SET audio_id = NULL WHERE audio_id = ?', array((int)$id));
        $db->exec('UPDATE campaign_contacts SET audio_id = NULL WHERE audio_id = ?', array((int)$id));
        $db->exec('DELETE FROM audio_files WHERE id = ?', array((int)$id));
        @unlink(Config::storage($a['path']));
        return true;
    }
}
