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
?>
