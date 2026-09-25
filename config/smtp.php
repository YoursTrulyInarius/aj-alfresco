<?php
// =============================================
//  SMTP Configuration — A&J Alfresco
//  Using Gmail SMTP + PHPMailer
// =============================================

define('SMTP_HOST',     getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT',     (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'YOUR_SMTP_USERNAME');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'YOUR_SMTP_PASSWORD');
define('SMTP_FROM',     getenv('SMTP_FROM') ?: 'YOUR_SMTP_FROM');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'A&J Alfresco');
