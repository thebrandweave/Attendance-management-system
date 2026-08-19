<?php
session_start();
include("../config/db.php");

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$toastMessage = "";
$toastColor = "";

if (isset($_POST['token'])) {

    $input = $_POST['token'];

    // Get token from URL or direct token
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        parse_str(parse_url($input, PHP_URL_QUERY), $query);
        $token = $query['token'] ?? '';
    } else {
        $token = trim($input);
    }

    $today = date("Y-m-d");
    $now = date("Y-m-d H:i:s");

    /* ================= USER ================= */

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE qr_token = ?"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {

        $toastMessage = "Invalid QR Code ❌";
        $toastColor = "#ef4444";

    } else {

        $userId = $user['id'];

        require_once "../config/branch_helper.php";

        $userBranch = $user['branch'] ?? 'gdedutech';

        $attTable = getBranchTableNameOnly(
            $conn,
            $userBranch
        );

        /* ================= ATTENDANCE CHECK ================= */

        $stmt = $conn->prepare(
            "SELECT * FROM `$attTable`
             WHERE user_id = ? AND date = ?"
        );

        $stmt->bind_param(
            "is",
            $userId,
            $today
        );

        $stmt->execute();

        $attendance = $stmt->get_result()->fetch_assoc();

        if (!$attendance) {

            $toastMessage = "Please Check-In First ❌";
            $toastColor = "#ef4444";

        } else {

            /* ================= CASE 1: START LUNCH ================= */

            if (empty($attendance['lunch_out'])) {

                $stmt = $conn->prepare(
                    "UPDATE `$attTable`
                     SET lunch_out = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "si",
                    $now,
                    $attendance['id']
                );

                $stmt->execute();

                $toastMessage = "Lunch Started 🍴 Enjoy your meal!";
                $toastColor = "#6366f1";

            }

            /* ================= CASE 2: END LUNCH ================= */

            elseif (empty($attendance['lunch_in'])) {

                $lunchOutTime = strtotime(
                    $attendance['lunch_out']
                );

                $currentTime = strtotime($now);

                // Calculate lunch duration in seconds
                $difference = $currentTime - $lunchOutTime;

                /* ===== MINIMUM 60 SECONDS ===== */

                if ($difference < 60) {

                    $toastMessage =
                        "Please wait before ending lunch ⏳";

                    $toastColor = "#f59e0b";

                } else {

                    /* ===== CALCULATE LUNCH HOURS & MINUTES ===== */

                    $hours = floor($difference / 3600);

                    $minutes = floor(
                        ($difference % 3600) / 60
                    );

                    // Format duration nicely
                    if ($hours > 0 && $minutes > 0) {

                        $lunchDuration =
                            $hours . " hr " .
                            $minutes . " mins";

                    } elseif ($hours > 0) {

                        $lunchDuration =
                            $hours . " hr";

                    } else {

                        $lunchDuration =
                            $minutes . " mins";
                    }

                    /* ===== SAVE LUNCH END TIME ===== */

                    $stmt = $conn->prepare(
                        "UPDATE `$attTable`
                         SET lunch_in = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "si",
                        $now,
                        $attendance['id']
                    );

                    $stmt->execute();

                    /* ===== SUCCESS MESSAGE ===== */

                    $toastMessage =
                        "Lunch Ended ✅ Welcome back! " .
                        "🍴 Lunch Time: " .
                        $lunchDuration;

                    $toastColor = "#16a34a";
                }
            }

            /* ================= CASE 3: COMPLETED ================= */

            else {

                $toastMessage =
                    "Lunch already completed for today ❌";

                $toastColor = "#f59e0b";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Lunch Break</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #eef2f7, #dbeafe);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            width: 90%;
            max-width: 500px;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-align: center;
        }
        #reader {
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            border: 2px dashed #6366f1;
            background: #f9fafb;
        }
        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #1f2937;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-btn:hover { background: #000; }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transform: translateX(150%);
            transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 10000;
        }
        .toast.show { transform: translateX(0); }
    </style>
</head>
<body>

<div id="toast" class="toast"></div>

<div class="card">
    <h2 style="color: #1e293b;">Lunch Break 🍴</h2>
    <p style="color: #64748b; margin-bottom: 20px;">Scan to Start or End Lunch</p>
    
    <div id="reader"></div>
    
    <form method="POST" id="scanForm">
        <input type="hidden" name="token" id="token">
    </form>

    <a href="../admin/dashboard.php" class="back-param back-btn">⬅ Go Back</a>
</div>
<script>
    const toast = document.getElementById("toast");

    let scanned = false;
    let html5QrCode;

    function showToast(message, color = "#111827") {
        toast.innerText = message;
        toast.style.backgroundColor = color;
        toast.classList.add("show");

        setTimeout(() => {
            toast.classList.remove("show");
        }, 3000);
    }

    <?php if ($toastMessage): ?>
        showToast("<?php echo $toastMessage; ?>", "<?php echo $toastColor; ?>");
    <?php endif; ?>

    async function onScanSuccess(decodedText) {

        // Prevent multiple scans
        if (scanned) return;

        scanned = true;

        try {

            // Stop scanner immediately
            await html5QrCode.stop();

            showToast("Verifying QR... 🔄", "#6366f1");

            document.getElementById("token").value = decodedText;

            // Small delay before submit
            setTimeout(() => {
                document.getElementById("scanForm").submit();
            }, 500);

        } catch (err) {
            console.error(err);
            scanned = false;
        }
    }

    html5QrCode = new Html5Qrcode("reader");

    Html5Qrcode.getCameras()
        .then(devices => {

            if (devices && devices.length) {

                html5QrCode.start(
                    { facingMode: "environment" },
                    {
                        fps: 5,
                        qrbox: { width: 250, height: 250 }
                    },
                    onScanSuccess
                );
            }

        })
        .catch(err => {
            showToast("Camera Error ❌", "#ef4444");
        });
</script>

</body>
</html>