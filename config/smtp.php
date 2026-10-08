<?php
// =============================================
//  SMTP Configuration — A&J Alfresco
//  Using Gmail SMTP + PHPMailer
// =============================================

$localSmtpConfig = __DIR__ . '/smtp.local.php';
if (is_file($localSmtpConfig)) {
	require_once $localSmtpConfig;
}

$smtpSetting = static function ($name, $localValue, $default = '') {
	$environmentValue = getenv($name);
	return $environmentValue === false ? ($localValue ?? $default) : $environmentValue;
};

define('SMTP_HOST',     $smtpSetting('SMTP_HOST', $smtpHost ?? null, 'smtp.gmail.com'));
define('SMTP_PORT',     (int)$smtpSetting('SMTP_PORT', $smtpPort ?? null, '587'));
define('SMTP_USERNAME', $smtpSetting('SMTP_USERNAME', $smtpUsername ?? null));
define('SMTP_PASSWORD', $smtpSetting('SMTP_PASSWORD', $smtpPassword ?? null));
define('SMTP_FROM',     $smtpSetting('SMTP_FROM', $smtpFrom ?? null));
define('SMTP_FROM_NAME', $smtpSetting('SMTP_FROM_NAME', $smtpFromName ?? null, 'A&J Alfresco'));
