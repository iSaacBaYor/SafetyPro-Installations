<?php
/**
 * SafetyPro Contact Form API
 * Handles contact form submissions and sends emails via SMTP
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rate limiting (simple implementation)
session_start();
$current_time = time();
$rate_limit_window = 300; // 5 minutes
$max_requests = 5;

if (!isset($_SESSION['last_request_time'])) {
    $_SESSION['last_request_time'] = $current_time;
    $_SESSION['request_count'] = 1;
} else {
    if (($current_time - $_SESSION['last_request_time']) < $rate_limit_window) {
        $_SESSION['request_count']++;
        if ($_SESSION['request_count'] > $max_requests) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
            exit();
        }
    } else {
        $_SESSION['last_request_time'] = $current_time;
        $_SESSION['request_count'] = 1;
    }
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Configuration - UPDATE THESE WITH YOUR DETAILS
$config = [
    'to_email' => 'safetyproinstallations@gmail.com',
    'from_email' => 'noreply@safetypro.com',
    'from_name' => 'SafetyPro Website',
    'subject_prefix' => 'SafetyPro Contact Form - ',
    
    // SMTP Configuration (for better email delivery)
    'smtp_host' => 'smtp.gmail.com', // Change to your SMTP server
    'smtp_port' => 587,
    'smtp_username' => 'safetyproinstallations@gmail.com', // Your Gmail
    'smtp_password' => '', // Your Gmail App Password (set this!)
    'smtp_encryption' => 'tls'
];

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
$required_fields = ['name', 'email', 'message'];
foreach ($required_fields as $field) {
    if (empty($input[$field]) || !is_string($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
        exit();
    }
}

// Sanitize and validate input data
$name = htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
$message = htmlspecialchars(trim($input['message']), ENT_QUOTES, 'UTF-8');
$phone = isset($input['phone']) ? htmlspecialchars(trim($input['phone']), ENT_QUOTES, 'UTF-8') : '';
$service = isset($input['service']) ? htmlspecialchars(trim($input['service']), ENT_QUOTES, 'UTF-8') : '';

// Additional validation
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

if (strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name must be between 2 and 100 characters']);
    exit();
}

if (strlen($message) < 10 || strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message must be between 10 and 2000 characters']);
    exit();
}

/**
 * SMTP Email Function using PHPMailer-like functionality
 */
function sendSMTPEmail($config, $to, $subject, $body, $replyTo = null) {
    // If SMTP password is not set, fall back to basic mail()
    if (empty($config['smtp_password'])) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    // SMTP implementation would go here
    // For now, using basic mail() function
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'Reply-To: ' . ($replyTo ?: $config['from_email']),
        'X-Mailer: SafetyPro Contact API v1.0'
    ];
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

// Create email subject
$subject = $config['subject_prefix'] . "New inquiry from " . $name;

// Create HTML email body
$html_body = "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SafetyPro Contact Form</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            line-height: 1.6; 
            color: #374151; 
            margin: 0; 
            padding: 0; 
            background-color: #f9fafb;
        }
        .container { 
            max-width: 600px; 
            margin: 20px auto; 
            background: white; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header { 
            background: linear-gradient(135deg, #ea580c, #f97316); 
            color: white; 
            padding: 30px 20px; 
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .header .shield {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
        .content { 
            padding: 30px 20px; 
        }
        .field { 
            margin-bottom: 20px; 
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 15px;
        }
        .field:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .label { 
            font-weight: 600; 
            color: #1f2937; 
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }
        .value { 
            font-size: 16px;
            color: #374151;
            word-wrap: break-word;
        }
        .message-box { 
            background: #f8fafc; 
            padding: 20px; 
            border-radius: 8px; 
            border-left: 4px solid #f97316;
            font-style: italic;
        }
        .footer { 
            background: #1f2937; 
            color: #9ca3af; 
            padding: 20px; 
            text-align: center; 
            font-size: 12px;
        }
        .footer a {
            color: #f97316;
            text-decoration: none;
        }
        .priority {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <span class='shield'>🛡️</span>
            <h1>SafetyPro Contact Form</h1>
            <p style='margin: 5px 0 0 0; opacity: 0.9;'>New Customer Inquiry</p>
        </div>
        
        <div class='content'>
            <div class='field'>
                <span class='label'>Customer Name</span>
                <div class='value'><strong>{$name}</strong></div>
            </div>
            
            <div class='field'>
                <span class='label'>Email Address</span>
                <div class='value'><a href='mailto:{$email}' style='color: #f97316; text-decoration: none;'>{$email}</a></div>
            </div>
            
            <div class='field'>
                <span class='label'>Phone Number</span>
                <div class='value'>" . ($phone ?: '<em>Not provided</em>') . "</div>
            </div>
            
            <div class='field'>
                <span class='label'>Service Interest</span>
                <div class='value'>" . ($service ?: '<em>Not specified</em>') . "</div>
            </div>
            
            <div class='field'>
                <span class='label'>Customer Message</span>
                <div class='message-box'>{$message}</div>
            </div>
            
            <div class='field'>
                <span class='label'>Submission Details</span>
                <div class='value'>
                    <strong>Date:</strong> " . date('F j, Y \a\t g:i A T') . "<br>
                    <strong>IP Address:</strong> " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . "<br>
                    <strong>User Agent:</strong> " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "
                </div>
            </div>
        </div>
        
        <div class='footer'>
            <p><strong>Action Required:</strong> <span class='priority'>Respond within 24 hours</span></p>
            <p>SafetyPro Installations - Smart Home Security Solutions</p>
            <p>Email: <a href='mailto:safetyproinstallations@gmail.com'>safetyproinstallations@gmail.com</a> | Phone: +233 24 123 4567</p>
        </div>
    </div>
</body>
</html>";

// Log the submission attempt
$log_entry = date('Y-m-d H:i:s') . " - Contact form submission from: {$email} ({$name}) - IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . "\n";
error_log($log_entry, 3, 'contact_submissions.log');

// Send email
try {
    $mail_sent = sendSMTPEmail($config, $config['to_email'], $subject, $html_body, $email);
    
    if ($mail_sent) {
        // Log successful submission
        error_log("SUCCESS: " . $log_entry, 3, 'contact_submissions.log');
        
        // Send auto-reply to customer
        $auto_reply_subject = "Thank you for contacting SafetyPro - We'll be in touch soon!";
        $auto_reply_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ea580c, #f97316); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
                .footer { background: #374151; color: white; padding: 15px; text-align: center; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🛡️ Thank You for Contacting SafetyPro!</h2>
                </div>
                <div class='content'>
                    <p>Dear {$name},</p>
                    <p>Thank you for your interest in SafetyPro's smart home security solutions. We have received your message and will respond within 24 hours.</p>
                    <p><strong>Your message:</strong></p>
                    <div style='background: white; padding: 15px; border-left: 4px solid #f97316; margin: 15px 0;'>
                        {$message}
                    </div>
                    <p>In the meantime, feel free to:</p>
                    <ul>
                        <li>Browse our <a href='https://yourwebsite.com/services.html' style='color: #f97316;'>services</a></li>
                        <li>Check out our <a href='https://yourwebsite.com/shop.html' style='color: #f97316;'>product catalog</a></li>
                        <li>Call us directly at +233 24 123 4567</li>
                    </ul>
                    <p>Best regards,<br><strong>The SafetyPro Team</strong></p>
                </div>
                <div class='footer'>
                    <p>SafetyPro Installations | safetyproinstallations@gmail.com | +233 24 123 4567</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Send auto-reply (optional)
        sendSMTPEmail($config, $email, $auto_reply_subject, $auto_reply_body, $config['to_email']);
        
        // Return success response
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully! We\'ll get back to you within 24 hours.',
            'timestamp' => date('c')
        ]);
        
    } else {
        throw new Exception('Failed to send email');
    }
    
} catch (Exception $e) {
    // Log error
    error_log("ERROR: " . $log_entry . " - " . $e->getMessage(), 3, 'contact_submissions.log');
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Sorry, there was an error sending your message. Please try again or contact us directly at safetyproinstallations@gmail.com',
        'error_code' => 'EMAIL_SEND_FAILED'
    ]);
}
?>
            <p>SafetyPro Installations - Smart Home Security Solutions</p>
        </div>
    </div>
</body>
</html>
";

// Email headers
$headers = array(
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $from_email,
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion(),
    'X-Priority: 1'
);

// Send email
$mail_sent = mail($to_email, $subject, $html_body, implode("\r\n", $headers));

if ($mail_sent) {
    // Log successful submission (optional)
    error_log("Contact form submission from: {$email} - {$name}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Message sent successfully! We\'ll get back to you within 24 hours.'
    ]);
} else {
    // Log error
    error_log("Failed to send contact form email from: {$email}");
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Sorry, there was an error sending your message. Please try again or contact us directly.'
    ]);
}
?>
