<?php
return [
    'smtp_enabled' => true,
    'smtp_host' => 'smtp.mailtrap.io', // Use Mailtrap for testing, or your SMTP provider
    'smtp_port' => 2525, // Mailtrap port, adjust for your provider
    'smtp_encryption' => 'tls',
    'smtp_username' => 'your_mailtrap_username', // Replace with your Mailtrap credentials
    'smtp_password' => 'your_mailtrap_password', // Replace with your Mailtrap credentials
    'smtp_from' => 'noreply@passcollege.local',
    'smtp_from_name' => 'PASS College Support System',
];
