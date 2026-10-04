<?php
/**
 * Calendar helpers: exact Jalali (Solar Hijri) <-> Gregorian conversion using the
 * 33-year-cycle "jalaali" algorithm (accurate for 1178..3177 Jalali, leap years included),
 * plus a tabular Islamic (Hijri) calendar for religious holidays.
 */
class Jalali
{
    private static function div($a, $b)
    {
        return (int)($a / $b); // truncation toward zero, like the reference implementation
    }

    private static function mod($a, $b)
    {
        return $a - self::div($a, $b) * $b;
    }

    /** @return array(leap, gy, march) */
    public static function jalCal($jy)
    {
        $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178);
        $bl = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        $jump = 0;
        for ($i = 1; $i < $bl; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ = $leapJ + self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
            $jp = $jm;
        }
        $n = $jy - $jp;
        $leapJ = $leapJ + self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);
        if (self::mod($jump, 33) === 4 && $jump - $n === 4) {
            $leapJ += 1;
        }
        $leapG = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;
        if ($jump - $n < 6) {
            $n = $n - $jump + self::div($jump + 4, 33) * 33;
        }
        $leap = self::mod(self::mod($n + 1, 33) - 1, 4);
        if ($leap === -1) {
            $leap = 4;
        }
        return array('leap' => $leap, 'gy' => $gy, 'march' => $march);
    }

    public static function isLeap($jy)
    {
        $r = self::jalCal($jy);
        return $r['leap'] === 0;
    }

    public static function monthLength($jy, $jm)
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        return self::isLeap($jy) ? 30 : 29;
    }

    /** Gregorian -> Julian Day Number */
    public static function g2d($gy, $gm, $gd)
    {
        $d = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4) + self::div(153 * self::mod($gm + 9, 12) + 2, 5) + $gd - 34840408;
        $d = $d - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;
        return $d;
    }

    /** JDN -> Gregorian array(y,m,d) */
    public static function d2g($jdn)
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::div(self::mod($j, 1461), 4) * 5 + 308;
        $gd = self::div(self::mod($i, 153), 5) + 1;
        $gm = self::mod(self::div($i, 153), 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);
        return array($gy, $gm, $gd);
    }

    public static function j2d($jy, $jm, $jd)
    {
        $r = self::jalCal($jy);
        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /** JDN -> Jalali array(y,m,d) */
    public static function d2j($jdn)
    {
        $g = self::d2g($jdn);
        $jy = $g[0] - 621;
        $r = self::jalCal($jy);
        $jdn1f = self::g2d($g[0], 3, $r['march']);
        $k = $jdn - $jdn1f;
        if ($k >= 0) {
            if ($k <= 185) {
                return array($jy, 1 + self::div($k, 31), self::mod($k, 31) + 1);
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }
        return array($jy, 7 + self::div($k, 30), self::mod($k, 30) + 1);
    }

    public static function toJalali($gy, $gm, $gd)
    {
        return self::d2j(self::g2d((int)$gy, (int)$gm, (int)$gd));
    }

    public static function toGregorian($jy, $jm, $jd)
    {
        return self::d2g(self::j2d((int)$jy, (int)$jm, (int)$jd));
    }

    public static function isValid($jy, $jm, $jd)
    {
        return $jy >= 1178 && $jy <= 3177 && $jm >= 1 && $jm <= 12 && $jd >= 1 && $jd <= self::monthLength($jy, $jm);
    }

    public static function monthNames()
    {
        return array('فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
    }

    // ---------------------------------------------------------------- Islamic (tabular / Kuwaiti algorithm)

    /** JDN -> Islamic array(y,m,d) */
    public static function d2i($jdn)
    {
        $l = $jdn - 1948440 + 10632;
        $n = (int)floor(($l - 1) / 10631);
        $l = $l - 10631 * $n + 354;
        $j = ((int)floor((10985 - $l) / 5316)) * ((int)floor((50 * $l) / 17719)) + ((int)floor($l / 5670)) * ((int)floor((43 * $l) / 15238));
        $l = $l - ((int)floor((30 - $j) / 15)) * ((int)floor((17719 * $j) / 50)) - ((int)floor($j / 16)) * ((int)floor((15238 * $j) / 43)) + 29;
        $m = (int)floor((24 * $l) / 709);
        $d = $l - (int)floor((709 * $m) / 24);
        $y = 30 * $n + $j - 30;
        return array($y, $m, $d);
    }

    /**
     * Official public holidays of Iran for a Jalali year.
     * Solar ones are exact; lunar ones come from the tabular Islamic calendar and may differ
     * by one day from the officially announced (moon-sighting based) dates.
     * @return array of array('date' => 'Y-m-d', 'title' => ..., 'lunar' => bool)
     */
    public static function iranHolidays($jy)
    {
        $solar = array(
            '1-1' => 'نوروز', '1-2' => 'نوروز', '1-3' => 'نوروز', '1-4' => 'نوروز',
            '1-12' => 'روز جمهوری اسلامی', '1-13' => 'روز طبیعت (سیزده‌بدر)',
            '3-14' => 'رحلت امام خمینی', '3-15' => 'قیام ۱۵ خرداد',
            '11-22' => 'پیروزی انقلاب اسلامی', '12-29' => 'ملی شدن صنعت نفت',
        );
        $lunar = array(
            '1-9' => 'تاسوعای حسینی', '1-10' => 'عاشورای حسینی', '2-20' => 'اربعین حسینی',
            '2-28' => 'رحلت پیامبر اکرم و شهادت امام حسن مجتبی', '2-30' => 'شهادت امام رضا',
            '3-8' => 'شهادت امام حسن عسکری', '3-17' => 'ولادت پیامبر اکرم و امام جعفر صادق',
            '6-3' => 'شهادت حضرت فاطمه زهرا', '7-13' => 'ولادت امام علی', '7-27' => 'مبعث پیامبر اکرم',
            '8-15' => 'ولادت حضرت قائم', '9-21' => 'شهادت امام علی', '10-1' => 'عید سعید فطر', '10-2' => 'تعطیل به مناسبت عید فطر',
            '10-25' => 'شهادت امام جعفر صادق', '12-10' => 'عید سعید قربان', '12-18' => 'عید سعید غدیر خم',
        );
        $out = array();
        $start = self::j2d($jy, 1, 1);
        $end = self::j2d($jy, 12, self::monthLength($jy, 12));
        for ($jdn = $start; $jdn <= $end; $jdn++) {
            $j = self::d2j($jdn);
            $g = self::d2g($jdn);
            $date = sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
            $k = $j[1] . '-' . $j[2];
            if (isset($solar[$k])) {
                $out[$date] = array('date' => $date, 'title' => $solar[$k], 'lunar' => false);
            }
            $i = self::d2i($jdn);
            $ik = $i[1] . '-' . $i[2];
            if (isset($lunar[$ik])) {
                // Safar has 29 days in some tabular years: treat 29 Safar as "end of Safar" when there is no 30th
                if (!isset($out[$date])) {
                    $out[$date] = array('date' => $date, 'title' => $lunar[$ik], 'lunar' => true);
                }
            } elseif ($i[1] === 2 && $i[2] === 29) {
                $next = self::d2i($jdn + 1);
                if ($next[1] === 3 && !isset($out[$date])) {
                    $out[$date] = array('date' => $date, 'title' => $lunar['2-30'], 'lunar' => true);
                }
            }
        }
        ksort($out);
        return array_values($out);
    }
}
