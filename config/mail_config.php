<?php
/**
 * Multi-Branch Email & SMTP Configuration
 * Attendance & Leave Management System
 *
 * Supports independent SMTP senders for all 3 branches:
 * 1. gdedutech (Mangalore Branch) - Active
 * 2. mudipu (Mudipu Branch) - Placeholder for future
 * 3. thirthahalli (Thirthahalli Branch) - Placeholder for future
 */

return [
    // -------------------------------------------------------------
    // Global Fallback Defaults
    // -------------------------------------------------------------
    'default' => [
        'smtp_enabled' => false,
        'smtp_host'    => 'smtp.gmail.com',
        'smtp_port'    => 587,
        'smtp_secure'  => 'tls',
        'smtp_user'    => '',
        'smtp_pass'    => '',
        'from_email'   => 'hr@thebrandweave.com',
        'from_name'    => 'The Brand Weave HR',
        'company_name' => 'The Brand Weave',
        'support_email'=> 'support@thebrandweave.com'
    ],

    // -------------------------------------------------------------
    // Branch-Specific SMTP Configurations
    // -------------------------------------------------------------
    'branches' => [
        // =========================================================
        // 1. GDEDUTECH (Mangalore Branch) - ACTIVE
        // =========================================================
        'gdedutech' => [
            'branch_title' => 'GD Edu Tech (Mangalore Branch)',
            // Set to true when you enter the Gmail address and App Password below
            'smtp_enabled' => true,
            'smtp_host'    => 'smtp.gmail.com',
            'smtp_port'    => 587,
            'smtp_secure'  => 'tls',
            
            // Enter your Mangalore branch Gmail and 16-character Google App Password here:
            'smtp_user'    => 'gdedutech24@gmail.com', // Replace with your GD Edu Tech Gmail
            'smtp_pass'    => 'mcoa gbua rhgv ccoj',                              // Replace with your 16-character Google App Password
            
            // Sender Details displayed to recipient employees
            'from_email'   => 'gdedutech.attendance@gmail.com',
            'from_name'    => 'GD Edu Tech ',
            'company_name' => 'GD Edu Tech - Mangalore',
            'support_email'=> 'gdedutech24@gmail.com'
        ],

        // =========================================================
        // 2. MUDIPU BRANCH - (Disabled for now)
        // =========================================================
        'mudipu' => [
            'branch_title' => 'Mudipu Branch',
            'smtp_enabled' => false,
            'smtp_host'    => 'smtp.gmail.com',
            'smtp_port'    => 587,
            'smtp_secure'  => 'tls',
            'smtp_user'    => '',
            'smtp_pass'    => '',
            'from_email'   => 'hr.mudipu@thebrandweave.com',
            'from_name'    => 'Mudipu Branch HR',
            'company_name' => 'The Brand Weave - Mudipu',
            'support_email'=> 'hr.mudipu@thebrandweave.com'
        ],

        // =========================================================
        // 3. THIRTHAHALLI BRANCH - (Disabled for now)
        // =========================================================
        'thirthahalli' => [
            'branch_title' => 'Thirthahalli Branch',
            'smtp_enabled' => false,
            'smtp_host'    => 'smtp.gmail.com',
            'smtp_port'    => 587,
            'smtp_secure'  => 'tls',
            'smtp_user'    => '',
            'smtp_pass'    => '',
            'from_email'   => 'hr.thirthahalli@thebrandweave.com',
            'from_name'    => 'Thirthahalli Branch HR',
            'company_name' => 'The Brand Weave - Thirthahalli',
            'support_email'=> 'hr.thirthahalli@thebrandweave.com'
        ]
    ],

    // -------------------------------------------------------------
    // Logging Configuration
    // -------------------------------------------------------------
    'log_enabled'  => true,
    'log_file'     => dirname(__DIR__) . '/logs/email_notifications.log'
];
