<?php
/**
 * Professional HTML Email Templates
 * Attendance & Leave Management System
 */

/**
 * Generate Company Leave Announcement Email HTML
 */
function getCompanyLeaveEmailTemplate($data) {
    $title       = htmlspecialchars($data['title'] ?? 'Company Holiday');
    // $leaveDate   = !empty($data['leave_date']) ? date('l, d F Y', strtotime($data['leave_date'])) : 'Scheduled Date';
    $description = !empty($data['description']) ? nl2br(htmlspecialchars($data['description'])) : 'No additional notes provided.';
    $branchName  = htmlspecialchars($data['branch_name'] ?? 'All Branches');
    $companyName = htmlspecialchars($data['company_name'] ?? 'The Brand Weave');
    $year        = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company Holiday Announcement</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#333333; -webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f6f9; padding:30px 10px;">
    <tr>
      <td align="center">
        <!-- Container Card -->
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.07); border:1px solid #e5e7eb;">
          
          <!-- Header -->
          <!-- <tr>
            <td style="background:linear-gradient(135deg, #111827 0%, #1f2937 100%); padding:28px 30px; text-align:center;">
              <div style="font-size:22px; font-weight:700; color:#ffffff; letter-spacing:0.5px;">{$companyName}</div>
              <div style="font-size:12px; font-weight:500; color:#9ca3af; margin-top:4px; text-transform:uppercase; letter-spacing:1px;">Attendance & Leave Management</div>
            </td>
          </tr> -->

          <!-- Banner -->
          <tr>
            <td style="background-color:#eff6ff; padding:16px 30px; border-bottom:1px solid #dbeafe; text-align:center;">
              <span style="display:inline-block; font-size:14px; font-weight:700; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.8px;">
                📅 Official Company Leave Announcement
              </span>
            </td>
          </tr>

          <!-- Main Content -->
          <tr>
            <td style="padding:32px 30px;">
              <p style="font-size:15px; line-height:1.6; color:#374151; margin-top:0; margin-bottom:20px;">
                Dear Team Member,
              </p>
              <p style="font-size:15px; line-height:1.6; color:#374151; margin-bottom:24px;">
We’re pleased to share the upcoming company holiday schedule approved for <strong>{$branchName}</strong>:              </p>

              <!-- Holiday Highlight Card -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8fafc; border-left:4px solid #3b82f6; border-radius:8px; padding:20px; margin-bottom:24px; border:1px solid #e2e8f0; border-left-width:4px;">
                <tr>
                  <td>
                    <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Occasion / Holiday</div>
                    <div style="font-size:20px; font-weight:700; color:#1e293b; margin-bottom:16px;">{$title}</div>

                    <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Scheduled Date</div>
                    <div style="font-size:16px; font-weight:600; color:#0f172a; margin-bottom:16px;">
                      🗓️ {$leaveDate}
                    </div>

                    <!-- <div style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Applicable Branch</div>
                    <div style="font-size:14px; font-weight:600; color:#334155; margin-bottom:16px;">
                      🏢 {$branchName}
                    </div> -->

                   
                  </td>
                </tr>
              </table>

              <p style="font-size:14px; line-height:1.6; color:#4b5563; margin-bottom:10px;">
              Wishing you a Happy Holiday
              </p>
              <p style="font-size:14px; line-height:1.6; color:#4b5563; margin-bottom:0;">
                Warm regards,
                <!-- <strong>GD EDU TECH Management</strong> -->
                {$companyName}
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f9fafb; padding:20px 30px; text-align:center; border-top:1px solid #f3f4f6; font-size:12px; color:#9ca3af; line-height:1.5;">
              This is an automated notification from the {$companyName} Employee Attendance System.<br>
              &copy; {$year} {$companyName}. All rights reserved.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Generate Leave Request Status Update (Approved / Rejected) Email HTML
 */
function getLeaveStatusEmailTemplate($data) {
    $empName     = htmlspecialchars($data['employee_name'] ?? 'Employee');
    $rawDateVal  = $data['leave_date'] ?? '';
    $leaveDate   = !empty($rawDateVal) ? (strtotime($rawDateVal) ? date('l, d F Y', strtotime($rawDateVal)) : htmlspecialchars($rawDateVal)) : 'Requested Date';
    $leaveType   = htmlspecialchars($data['leave_type'] ?? 'Leave');
    $reason      = !empty($data['reason']) ? nl2br(htmlspecialchars($data['reason'])) : 'N/A';
    $statusRaw   = strtolower($data['status'] ?? 'pending');
    $isApproved  = ($statusRaw === 'approved' || $statusRaw === 'approve');
    $companyName = htmlspecialchars($data['company_name'] ?? 'The Brand Weave');
    $year        = date('Y');

    $statusTitle = $isApproved ? 'Leave Request Approved ✅' : 'Leave Request Rejected ❌';
    $statusColor = $isApproved ? '#16a34a' : '#dc2626';
    $statusBg    = $isApproved ? '#ecfdf5' : '#fef2f2';
    $statusBorder= $isApproved ? '#a7f3d0' : '#fecaca';

    $actionMsg   = $isApproved
        ? 'Your leave request has been <strong>approved</strong> by management. This day has been logged as <strong>Paid Leave (PL)</strong> on your attendance record.'
        : 'Your leave request was <strong>not approved</strong> at this time. If you require further clarification, please get in touch with your branch manager or HR.';

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leave Request Notification</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#333333; -webkit-font-smoothing:antialiased;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f6f9; padding:30px 10px;">
    <tr>
      <td align="center">
        <!-- Container Card -->
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.07); border:1px solid #e5e7eb;">
          
          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg, #111827 0%, #1f2937 100%); padding:28px 30px; text-align:center;">
              <div style="font-size:22px; font-weight:700; color:#ffffff; letter-spacing:0.5px;">{$companyName}</div>
              <div style="font-size:12px; font-weight:500; color:#9ca3af; margin-top:4px; text-transform:uppercase; letter-spacing:1px;">Attendance & Leave Management</div>
            </td>
          </tr>

          <!-- Status Banner -->
          <tr>
            <td style="background-color:{$statusBg}; padding:18px 30px; border-bottom:1px solid {$statusBorder}; text-align:center;">
              <span style="display:inline-block; font-size:16px; font-weight:700; color:{$statusColor}; letter-spacing:0.5px;">
                {$statusTitle}
              </span>
            </td>
          </tr>

          <!-- Main Content -->
          <tr>
            <td style="padding:32px 30px;">
              <p style="font-size:15px; line-height:1.6; color:#374151; margin-top:0; margin-bottom:16px;">
                Dear <strong>{$empName}</strong>,
              </p>
              <p style="font-size:15px; line-height:1.6; color:#374151; margin-bottom:24px;">
                Your leave application has been reviewed by management. Here is the summary of your request:
              </p>

              <!-- Leave Details Box -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:24px; overflow:hidden;">
                <tr>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; width:38%; font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;">Employee Name</td>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:14px; font-weight:600; color:#111827;">{$empName}</td>
                </tr>
                <tr>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;">Employee ID</td>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:14px; font-weight:600; color:#111827;">{$empCode}</td>
                </tr>
                <tr>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;">Leave Date</td>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:14px; font-weight:600; color:#111827;">{$leaveDate}</td>
                </tr>
                <tr>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;">Leave Type</td>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:14px; font-weight:600; color:#111827;">{$leaveType}</td>
                </tr>
                <tr>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;">Reason Provided</td>
                  <td style="padding:12px 18px; border-bottom:1px solid #e5e7eb; font-size:14px; color:#374151;">{$reason}</td>
                </tr>
                <tr>
                  <td style="padding:12px 18px; font-size:13px; font-weight:600; color:#6b7280; text-transform:uppercase;">Decision Status</td>
                  <td style="padding:12px 18px; font-size:14px; font-weight:700; color:{$statusColor};">
                    {$statusTitle}
                  </td>
                </tr>
              </table>

              <!-- Notice Message -->
              <div style="background-color:{$statusBg}; border-left:4px solid {$statusColor}; padding:14px 18px; border-radius:6px; margin-bottom:24px; font-size:14px; line-height:1.5; color:#1f2937;">
                {$actionMsg}
              </div>

              <p style="font-size:14px; line-height:1.6; color:#4b5563; margin-bottom:0;">
                Best regards,<br>
                <strong>HR & Operations Management</strong><br>
                {$companyName}
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#f9fafb; padding:20px 30px; text-align:center; border-top:1px solid #f3f4f6; font-size:12px; color:#9ca3af; line-height:1.5;">
              This is an automated notification from the {$companyName} Employee Attendance System.<br>
              &copy; {$year} {$companyName}. All rights reserved.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
