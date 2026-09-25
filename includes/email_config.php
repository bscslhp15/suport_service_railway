<?php
return [
    'smtp_enabled' => filter_var(getenv('SMTP_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.mailtrap.io',
    'smtp_port' => (int) (getenv('SMTP_PORT') ?: 2525),
    'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'smtp_username' => getenv('SMTP_USERNAME') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_from' => getenv('SMTP_FROM') ?: 'noreply@passcollege.local',
    'smtp_from_name' => getenv('SMTP_FROM_NAME') ?: 'PASS College Support System',
];
