<?php
/**
* @file
* پاک‌سازی کامل تاریخچه‌ی تماس‌ها: حذف فایل‌های اکسل آپلودشده و رکوردهای مرتبط در دیتابیس
* imapro.ir
*/

require_once('connection.php');

// حذف فایل‌های csv آپلودشده در پوشه‌ی files (تاریخچه‌ی نمایش‌داده‌شده در پنل)
$files = glob($basepath."files/*.csv");
if ($files) {
    foreach ($files as $f) {
        @unlink($f);
    }
}

// حذف رکوردهای تاریخچه از دیتابیس (فقط رکوردهای مربوط به شماره‌ها، نه لاگ‌های دیگر)
mysqli_query($connection, "DELETE FROM logs WHERE type='field'");

header("Location: index.php");
exit;
