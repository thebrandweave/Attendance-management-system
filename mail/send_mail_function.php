<?php

function sendEmployeeMail($to, $subject, $message) {

    $headers = "MIME-Version: 1.0" . "\r\n";

    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

    $headers .= "From: GD EDU TECH Attendance " . "\r\n";

    $headers .= "" . "\r\n";

    $headers .= "X-Mailer: PHP/" . phpversion();

    $htmlMessage = "
    <div style='
        font-family:Poppins,Arial,sans-serif;
        padding:20px;
        background:#f4f6f9;
    '>

        <div style='
            max-width:600px;
            margin:auto;
            background:white;
            border-radius:12px;
            overflow:hidden;
            box-shadow:0 5px 15px rgba(0,0,0,0.1);
        '>

            <div style='
                background:#111827;
                color:white;
                padding:18px;
                text-align:center;
                font-size:22px;
                font-weight:600;
            '>
                GD EDU TECH
            </div>

            <div style='padding:25px;'>

                $message

            </div>

            <div style='
                background:#f3f4f6;
                padding:15px;
                text-align:center;
                font-size:12px;
                color:#6b7280;
            '>
                Attendance Management System
            </div>

        </div>

    </div>
    ";

    return mail($to, $subject, $htmlMessage, $headers);
}
?>