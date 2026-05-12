<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

function sendEmployeeMail($to, $subject, $message) {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = 'wearebrandweave@gmail.com';

        $mail->Password = 'phew qhrm dypd fefq';

        $mail->SMTPSecure = 'tls';

        $mail->Port = 587;

        $mail->setFrom(
            'wearebrandweave@gmail.com',
            'GD EDU TECH'
        );

        $mail->addAddress($to);

        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = "
        <div style='
            font-family:Poppins,sans-serif;
            padding:20px;
            background:#f3f4f6;
        '>

            <div style='
                max-width:600px;
                margin:auto;
                background:white;
                border-radius:12px;
                overflow:hidden;
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

            </div>

        </div>
        ";

        $mail->send();

        return true;

    } catch (Exception $e) {

        echo $mail->ErrorInfo;

        return false;
    }
}
?>