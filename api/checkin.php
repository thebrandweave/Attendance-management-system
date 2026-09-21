<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$lifetime = 60 * 60 * 24 * 30;
session_set_cookie_params($lifetime);

session_start();

include("../config/db.php");
require_once "../config/branch_helper.php";

date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$toastMessage = "";
$toastColor = "";

if (isset($_POST['token'])) {

    $input = $_POST['token'];

    // Extract token safely
    if (filter_var($input, FILTER_VALIDATE_URL)) {

        parse_str(
            parse_url($input, PHP_URL_QUERY),
            $query
        );

        $token = $query['token'] ?? '';

    } else {

        $token = trim($input);
    }


    $today = date("Y-m-d");

    $now = new DateTime(
        'now',
        new DateTimeZone('Asia/Kolkata')
    );

    $currentTime = $now->format("Y-m-d H:i:s");
    $timeOnly = $now->format("H:i:s");


    /*
    =========================================
    GET USER FROM QR TOKEN
    =========================================
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE qr_token = ?
    ");

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    $stmt->close();


    if (!$user) {

        $toastMessage =
            "Access Denied: Invalid QR Code";

        $toastColor = "#ef4444";

    } else {

        /*
        =========================================
        USER / BRANCH DETAILS
        =========================================
        */

        $userId = (int)$user['id'];

        $userBranch =
            $user['branch'] ?? 'gdedutech';

        $attTable = getBranchTableNameOnly(
            $conn,
            $userBranch
        );

        $isThirthahalliBranch = (
            strtolower(trim($userBranch))
            === "thirthahalli"
        );


        /*
        =========================================
        CHECK TODAY ATTENDANCE
        =========================================
        */

        $stmt = $conn->prepare("
            SELECT *
            FROM `$attTable`
            WHERE user_id = ?
            AND date = ?
        ");

        $stmt->bind_param(
            "is",
            $userId,
            $today
        );

        $stmt->execute();

        $attendance =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();


        /*
        =========================================
        ONLY CREATE ATTENDANCE IF NOT EXISTS
        =========================================
        */

        if (!$attendance) {

            /*
            =========================================
            CHECK-IN STATUS
            =========================================
            */

            if ($isThirthahalliBranch) {

                /*
                THIRTHAHALLI

                Up to 10:00 AM = Present
                After 10:00 AM = Late
                From 1:00 PM = Half Day
                */

                if ($timeOnly >= "13:00:00") {

                    $status = "Half Day";

                } elseif ($timeOnly <= "10:00:00") {

                    $status = "Present";

                } else {

                    $status = "Late";
                }

            } else {

                /*
                OTHER BRANCHES
                Existing rules
                */

                if (
                    $timeOnly >= "13:00:00" &&
                    $timeOnly <= "14:00:00"
                ) {

                    $status = "Half Day";

                } elseif (
                    $timeOnly <= "09:46:00"
                ) {

                    $status = "Present";

                } elseif (
                    $timeOnly <= "10:00:00"
                ) {

                    $status = "Late";

                } else {

                    $status = "Half Day";
                }
            }


            /*
            =========================================
            INSERT ATTENDANCE
            =========================================
            */

            $stmt = $conn->prepare("
                INSERT INTO `$attTable`
                (
                    user_id,
                    date,
                    check_in,
                    status
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isss",
                $userId,
                $today,
                $currentTime,
                $status
            );

            if ($stmt->execute()) {

                $toastMessage =
                    "✅ Check-In Successful. Status: $status";

                $toastColor = "#16a34a";
            }

            $stmt->close();

        } else {

            $toastMessage =
                "Attendance already recorded for today.";

            $toastColor = "#f59e0b";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>QR Check In</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
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
            background: #111827;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #000;
        }

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
            transition: transform 0.5s cubic-bezier(
                0.68,
                -0.55,
                0.265,
                1.55
            );
            z-index: 10000;
        }

        .toast.show {
            transform: translateX(0);
        }

    </style>
</head>

<body>

<div id="toast" class="toast"></div>

<div class="card">

    <h2 style="color:#1e293b;">
        Check-In 🕒
    </h2>

    <p style="color:#64748b; margin-bottom:20px;">
        Scan QR to start your day
    </p>

    <div id="reader"></div>

    <form method="POST" id="scanForm">
        <input
            type="hidden"
            name="token"
            id="token"
        >
    </form>

    <a
        href="../admin/dashboard.php"
        class="back-btn"
    >
        ⬅ Go Back
    </a>

</div>


<script>

const toast = document.getElementById("toast");

function showToast(
    message,
    color = "#111827"
) {

    toast.innerText = message;

    toast.style.backgroundColor = color;

    toast.classList.add("show");

    setTimeout(() => {

        toast.classList.remove("show");

    }, 3500);
}


/*
=========================================
PHP TOAST MESSAGE
=========================================
*/

<?php if ($toastMessage): ?>

showToast(
    <?= json_encode($toastMessage) ?>,
    <?= json_encode($toastColor) ?>
);

<?php endif; ?>


/*
=========================================
QR SCANNER
=========================================
*/

let html5QrCode =
    new Html5Qrcode("reader");

let isProcessing = false;


/*
=========================================
WHEN QR IS SCANNED
=========================================
*/

function onScanSuccess(decodedText) {

    // Prevent multiple submissions
    if (isProcessing) {
        return;
    }

    isProcessing = true;

    toast.innerText =
        "Processing Check-in... ⏳";

    toast.style.backgroundColor =
        "#6366f1";

    toast.classList.add("show");


    html5QrCode.stop()

        .then(() => {

            document
                .getElementById("token")
                .value = decodedText.trim();

            document
                .getElementById("scanForm")
                .submit();

        })

        .catch(() => {

            // Submit even if scanner stop fails

            document
                .getElementById("token")
                .value = decodedText.trim();

            document
                .getElementById("scanForm")
                .submit();

        });
}


/*
=========================================
START CAMERA
=========================================
*/

Html5Qrcode.getCameras()

.then(devices => {

    if (
        devices &&
        devices.length
    ) {

        /*
        Try to find rear camera
        */

        let cameraId =
            devices[0].id;

        for (
            let i = 0;
            i < devices.length;
            i++
        ) {

            const label =
                (
                    devices[i].label ||
                    ""
                ).toLowerCase();

            if (
                label.includes("back") ||
                label.includes("rear") ||
                label.includes("environment")
            ) {

                cameraId =
                    devices[i].id;

                break;
            }
        }


        html5QrCode.start(

            cameraId,

            {
                fps: 10,

                qrbox: {
                    width: 250,
                    height: 250
                }
            },

            onScanSuccess,

            () => {
                // Ignore normal QR scan failures
            }

        )

        .catch(err => {

            console.error(
                "Camera start error:",
                err
            );

            showToast(
                "Camera permission denied or camera unavailable ❌",
                "#ef4444"
            );

        });

    } else {

        showToast(
            "No Camera Found ❌",
            "#ef4444"
        );
    }

})

.catch(err => {

    console.error(
        "Camera Error:",
        err
    );

    showToast(
        "Camera Error ❌",
        "#ef4444"
    );

});

</script>

</body>
</html>