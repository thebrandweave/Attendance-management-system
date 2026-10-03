<?php
/**
 * Backend Employee Emails Storage & Registry
 * Attendance Management System
 *
 * All employee emails are stored here in the backend (not exposed in the admin panel UI).
 * The system checks this mapping and the `users.email` database column when sending:
 * 1. Company Leave Announcements
 * 2. Leave Request Approval / Rejection Notifications
 *
 * To sync these emails into the MySQL database, run: backend/sync_emails.php
 */

return [
    // === GDEDUTECH BRANCH EMPLOYEES ===
    'EMP7690' => ['name' => 'Abdul Rahman Shazil', 'email' => '', 'branch' => 'gdedutech'],
    'EMP8752' => ['name' => 'Ameena sunaira', 'email' => '', 'branch' => 'gdedutech'],
    'EMP8596' => ['name' => 'Ashika NH', 'email' => '', 'branch' => 'gdedutech'],
    'EMP9925' => ['name' => 'ChaithanyaShree KV', 'email' => '', 'branch' => 'gdedutech'],
    'EMP5532' => ['name' => 'Katherin Robin CK', 'email' => '', 'branch' => 'gdedutech'],
    'EMP3389' => ['name' => 'Mahammad Mufeez', 'email' => '', 'branch' => 'gdedutech'],
    'EMP8123' => ['name' => 'Mohammed Riyaz', 'email' => '', 'branch' => 'gdedutech'],
    'EMP3350' => ['name' => 'Mohammed Twayyib Ibrahim', 'email' => '', 'branch' => 'gdedutech'],
    'EMP2806' => ['name' => 'Muhammed Shafeel', 'email' => '', 'branch' => 'gdedutech'],
    'EMP6835' => ['name' => 'Punith NS', 'email' => '', 'branch' => 'gdedutech'],
    'EMP2783' => ['name' => 'Sunil Kumar M', 'email' => '', 'branch' => 'gdedutech'],

    // === MUDIPU BRANCH EMPLOYEES ===
    'EMP5776' => ['name' => 'Afreeda', 'email' => '', 'branch' => 'mudipu'],
    'EMP2773' => ['name' => 'Afreen S', 'email' => '', 'branch' => 'mudipu'],
    'EMP7224' => ['name' => 'Afreena', 'email' => '', 'branch' => 'mudipu'],
    'EMP9543' => ['name' => 'Ashya Ramshi', 'email' => '', 'branch' => 'mudipu'],
    'EMP7272' => ['name' => 'Asiyath Nishana', 'email' => '', 'branch' => 'mudipu'],
    'EMP4580' => ['name' => 'Ayishath Nazmina', 'email' => '', 'branch' => 'mudipu'],
    'EMP3809' => ['name' => 'Azmeena', 'email' => '', 'branch' => 'mudipu'],
    'EMP7595' => ['name' => 'Bathish', 'email' => '', 'branch' => 'mudipu'],
    'EMP6506' => ['name' => 'demo', 'email' => '', 'branch' => 'mudipu'],
    'EMP4642' => ['name' => 'demo1', 'email' => '', 'branch' => 'mudipu'],
    'EMP8642' => ['name' => 'Devaki', 'email' => '', 'branch' => 'mudipu'],
    'EMP3206' => ['name' => 'ESTHER', 'email' => '', 'branch' => 'mudipu'],
    'EMP4078' => ['name' => 'Fathimath Afreena', 'email' => '', 'branch' => 'mudipu'],
    'EMP3618' => ['name' => 'FATHIMATH AZOOFA', 'email' => '', 'branch' => 'mudipu'],
    'EMP4270' => ['name' => 'FATHIMATH MUBASHIRA', 'email' => '', 'branch' => 'mudipu'],
    'EMP8243' => ['name' => 'FATHIMATH NISHANA', 'email' => '', 'branch' => 'mudipu'],
    'EMP4018' => ['name' => 'Fathimath Rayana', 'email' => '', 'branch' => 'mudipu'],
    'EMP3234' => ['name' => 'Fathimath Razeena', 'email' => '', 'branch' => 'mudipu'],
    'EMP3313' => ['name' => 'Fayaz', 'email' => '', 'branch' => 'mudipu'],
    'EMP1721' => ['name' => 'Hisham Abbas', 'email' => '', 'branch' => 'mudipu'],
    'EMP6591' => ['name' => 'jaseela', 'email' => '', 'branch' => 'mudipu'],
    'EMP6792' => ['name' => 'MAIMUNA MEHAVEEN', 'email' => '', 'branch' => 'mudipu'],
    'EMP8527' => ['name' => 'Mehek', 'email' => '', 'branch' => 'mudipu'],
    'EMP3352' => ['name' => 'Muneeza', 'email' => '', 'branch' => 'mudipu'],
    'EMP9322' => ['name' => 'Murshida', 'email' => '', 'branch' => 'mudipu'],
    'EMP1032' => ['name' => 'R LAKSHMI PRASANNA', 'email' => '', 'branch' => 'mudipu'],
    'EMP7296' => ['name' => 'Rabiya', 'email' => '', 'branch' => 'mudipu'],
    'EMP1805' => ['name' => 'Rafida', 'email' => '', 'branch' => 'mudipu'],
    'EMP7905' => ['name' => 'Rahila', 'email' => '', 'branch' => 'mudipu'],
    'EMP9141' => ['name' => 'Ramshi', 'email' => '', 'branch' => 'mudipu'],
    'EMP7726' => ['name' => 'Rasheeda', 'email' => '', 'branch' => 'mudipu'],
    'EMP6551' => ['name' => 'Rayiza', 'email' => '', 'branch' => 'mudipu'],
    'EMP8893' => ['name' => 'Raziya', 'email' => '', 'branch' => 'mudipu'],
    'EMP3310' => ['name' => 'Razweena', 'email' => '', 'branch' => 'mudipu'],
    'EMP8069' => ['name' => 'Rimaza', 'email' => '', 'branch' => 'mudipu'],
    'EMP6871' => ['name' => 'Rushda', 'email' => '', 'branch' => 'mudipu'],
    'EMP2602' => ['name' => 'Shafeeka', 'email' => '', 'branch' => 'mudipu'],
    'EMP2666' => ['name' => 'Shahala', 'email' => '', 'branch' => 'mudipu'],
    'EMP5455' => ['name' => 'Shazma', 'email' => '', 'branch' => 'mudipu'],
    'EMP1432' => ['name' => 'Shifana', 'email' => '', 'branch' => 'mudipu'],
    'EMP9865' => ['name' => 'Suhana', 'email' => '', 'branch' => 'mudipu'],
    'EMP7122' => ['name' => 'Swali', 'email' => '', 'branch' => 'mudipu'],
    'EMP2353' => ['name' => 'Swaliya', 'email' => '', 'branch' => 'mudipu'],
    'EMP2562' => ['name' => 'Swathi', 'email' => '', 'branch' => 'mudipu'],
    'EMP4737' => ['name' => 'Thazira', 'email' => '', 'branch' => 'mudipu'],
    'EMP4565' => ['name' => 'VARA LAKSHMI', 'email' => '', 'branch' => 'mudipu'],
    'EMP1139' => ['name' => 'ZAKIYA', 'email' => '', 'branch' => 'mudipu'],
    'EMP6788' => ['name' => 'Zohara', 'email' => '', 'branch' => 'mudipu'],

    // === THIRTHAHALLI BRANCH EMPLOYEES ===
    'EMP7363' => ['name' => 'Anushree', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP4957' => ['name' => 'Asma S', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP8506' => ['name' => 'Devin d\'souza', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP4003' => ['name' => 'Farath naaz', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP8889' => ['name' => 'Hapsa A ', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP3246' => ['name' => 'Joyal d\'souza', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP7823' => ['name' => 'Mohammad Adil', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP7019' => ['name' => 'P.vidya', 'email' => '', 'branch' => 'Thirthahalli'],
    'EMP4645' => ['name' => 'Ramesh K N', 'email' => '', 'branch' => 'Thirthahalli'],
];
