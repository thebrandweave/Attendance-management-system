<?php
if (!function_exists('getBranchAttendanceTable')) {
    /**
     * Sanitizes branch name and ensures branch attendance table exists in database.
     * Example: "gdedutech" -> "attendance_gdedutech"
     */
    function getBranchAttendanceTable($conn, $branchName) {
        $cleanName = strtolower(trim($branchName));
        $cleanName = preg_replace('/[^a-z0-9_]/', '', $cleanName);
        if (empty($cleanName)) {
            $cleanName = 'main';
        }

        $tableName = 'attendance_' . $cleanName;

        // Ensure table exists in MySQL database
        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `date` date NOT NULL,
          `check_in` datetime DEFAULT NULL,
          `check_out` datetime DEFAULT NULL,
          `status` varchar(50) DEFAULT NULL,
          `lunch_out` datetime DEFAULT NULL,
          `lunch_in` datetime DEFAULT NULL,
          `total_hours` decimal(5,2) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_user_date` (`user_id`, `date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $conn->query($sql);

        return "`$tableName`";
    }
}

if (!function_exists('getBranchTableNameOnly')) {
    function getBranchTableNameOnly($conn, $branchName) {
        return str_replace('`', '', getBranchAttendanceTable($conn, $branchName));
    }
}

if (!function_exists('ensureEmployeeSettingsColumns')) {
    function ensureEmployeeSettingsColumns($conn) {
        static $checked = false;
        if ($checked) return;

        $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $columnsNeeded = [
                'working_hours' => "DECIMAL(4,2) NOT NULL DEFAULT 8.00",
                'monthly_cl' => "DECIMAL(4,2) NOT NULL DEFAULT 2.00",
                'check_in_days' => "VARCHAR(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat'",
                'working_days_per_month' => "INT(11) DEFAULT 26"
            ];

            foreach ($columnsNeeded as $col => $definition) {
                $check = $conn->query("SHOW COLUMNS FROM `users` LIKE '$col'");
                if ($check && $check->num_rows == 0) {
                    @$conn->query("ALTER TABLE `users` ADD COLUMN `$col` $definition");
                }
            }
        }

        $checked = true;
    }
}

if (!function_exists('getEmployeeCheckInDaysArray')) {
    function getEmployeeCheckInDaysArray($checkInDaysStr, $branchName = '') {
        if (!empty($checkInDaysStr)) {
            $raw = is_array($checkInDaysStr) ? $checkInDaysStr : explode(',', $checkInDaysStr);
            $clean = array_values(array_filter(array_map('trim', $raw)));
            if (!empty($clean)) {
                return $clean;
            }
        }
        $isSundayWorking = in_array(strtolower(trim($branchName)), ['mudipu', 'thirthahalli'], true);
        return $isSundayWorking 
            ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] 
            : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    }
}

if (!function_exists('formatCheckInDaysDisplay')) {
    function formatCheckInDaysDisplay($checkInDaysStr, $branchName = '') {
        $days = getEmployeeCheckInDaysArray($checkInDaysStr, $branchName);
        $allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $monToSat = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $monToFri = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        if (count($days) === 7) {
            return 'All Days (Mon - Sun)';
        }
        if ($days === $monToSat) {
            return 'Mon - Sat (6 days)';
        }
        if ($days === $monToFri) {
            return 'Mon - Fri (5 days)';
        }

        return implode(', ', $days) . ' (' . count($days) . ' days)';
    }
}

if (!function_exists('calculateEmployeeWorkingDaysInCycle')) {
    function calculateEmployeeWorkingDaysInCycle($startDate, $endDate, $checkInDaysArray, $companyLeaves = []) {
        $totalDays = 0;
        $curr = strtotime($startDate);
        $end = strtotime($endDate);

        while ($curr <= $end) {
            $dateStr = date('Y-m-d', $curr);
            $dayName = date('D', $curr);

            if (in_array($dayName, $checkInDaysArray, true) && !in_array($dateStr, $companyLeaves, true)) {
                $totalDays++;
            }

            $curr = strtotime('+1 day', $curr);
        }

        return $totalDays;
    }
}
?>
