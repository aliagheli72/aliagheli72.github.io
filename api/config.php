<?php
/**
 * NeuroTech Frontiers cPanel form configuration.
 *
 * 1) Create a MySQL database/user in cPanel.
 * 2) Import api/schema.sql in phpMyAdmin.
 * 3) Fill these values before uploading to public_html/api/config.php.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'alipour3_neuro',
        'user' => 'alipour3_neuro',
        'pass' => '7yLf5svHnGvtX9pkKb7s',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'enabled' => true,
        'to' => 'info@alipouragheli.com',
        'from' => 'no-reply@alipouragheli.com',
        'from_name' => 'NeuroTech Frontiers Website',
    ],
    // Optional. Leave empty to disable Turnstile verification in PHP.
    'turnstile_secret' => '',
];
