<?php
$FirstNumber = isset($_POST['FirstNumber']) ? $_POST['FirstNumber'] : '';
$SecondNumber = isset($_POST['SecondNumber']) ? $_POST['SecondNumber'] : '';
$ThirdNumber = isset($_POST['ThirdNumber']) ? $_POST['ThirdNumber'] : '';
$CalculatorResult = '';
if (isset($_POST['operator']) && is_numeric($FirstNumber) && is_numeric($SecondNumber) && is_numeric($ThirdNumber) && $ThirdNumber > 0) {
	$CalculatorResult = round((($FirstNumber + $SecondNumber) / $ThirdNumber) + 1, 2);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>محاسبه‌ی فاصله بین تماس‌ها</title>
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/theme.css">
<link rel="icon" href="assets/img/brand/favicon.svg" type="image/svg+xml">
<style>
	body { background: var(--color-bg-page); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
	.calc-card { max-width: 480px; width: 100%; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); padding: 32px; }
	.calc-card h1 { font-size: var(--font-size-lg); color: var(--color-text-heading); margin-bottom: 6px; text-align: center; }
	.calc-card .subtitle { color: var(--color-text-muted); font-size: var(--font-size-sm); text-align: center; margin-bottom: 24px; }
	.calc-field { margin-bottom: 16px; }
	.calc-field label { display: block; font-size: var(--font-size-sm); color: var(--color-text-body); margin-bottom: 6px; font-weight: 600; }
	.calc-field input[type="number"] { width: 100%; padding: 10px 12px; }
	.calc-result { margin-top: 8px; padding: 16px; background: var(--color-accent-soft); border-radius: var(--radius-sm); text-align: center; }
	.calc-result .value { font-size: 28px; font-weight: 700; color: var(--color-accent); }
	.calc-result .label { font-size: var(--font-size-sm); color: var(--color-text-muted); margin-top: 4px; }
	.calc-actions { margin-top: 20px; display: flex; gap: 10px; }
	.calc-actions .btn { flex: 1; text-align: center; }
	.back-link { display: block; text-align: center; margin-bottom: 18px; font-size: var(--font-size-sm); }
</style>
</head>
<body>
<div class="calc-card">
	<a href="index.php" class="back-link">← بازگشت به داشبورد</a>
	<h1>محاسبه‌گر فاصله بین تماس‌ها</h1>
	<p class="subtitle">به تنظیم درست فیلد «فاصله بین تماس‌ها» کمک می‌کند</p>
	<form action="calc.php" method="post">
		<div class="calc-field">
			<label for="FirstNumber">مدت زمان فایل صوتی (ثانیه)</label>
			<input type="number" step="any" name="FirstNumber" id="FirstNumber" required value="<?php echo htmlspecialchars($FirstNumber); ?>" />
		</div>
		<div class="calc-field">
			<label for="SecondNumber">زمان انتظار هر تماس (ثانیه)</label>
			<input type="number" step="any" name="SecondNumber" id="SecondNumber" required value="<?php echo htmlspecialchars($SecondNumber); ?>" />
		</div>
		<div class="calc-field">
			<label for="ThirdNumber">تعداد کانال‌های هم‌زمان ترانک</label>
			<input type="number" step="any" name="ThirdNumber" id="ThirdNumber" required value="<?php echo htmlspecialchars($ThirdNumber); ?>" />
		</div>

		<?php if ($CalculatorResult !== ''): ?>
		<div class="calc-result">
			<div class="value"><?php echo $CalculatorResult; ?></div>
			<div class="label">فاصله‌ی پیشنهادی بین تماس‌ها (ثانیه) — این عدد را دستی در فیلد «فاصله بین تماس‌ها» در تنظیمات وارد کنید</div>
		</div>
		<?php endif; ?>

		<div class="calc-actions">
			<button type="submit" name="operator" value="Computing" class="btn">محاسبه</button>
		</div>
	</form>
</div>
</body>
</html>
