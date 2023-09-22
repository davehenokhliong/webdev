<?php
session_start(); 

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

start();

function start() {
    if(isset($_POST['login'])) {
        if (authenticate()) {
            return;
        } else {
            display_login_form("Unknown user - we don't have the records for ". $_POST['email'] ." in the system", FALSE);
        }
    } else if(isset($_GET['action']) && $_GET['action'] == 'Logout') { //if logout is detected, then logout
        logout();
    } else if(isset($_GET['token']) && $_GET['token'] == $_SESSION['token']) { //if token received from otp email the same as the session one, it's correct
        display_secured_content();
    } else {
        if (authenticate()) {
            return;
        } else {
            display_login_form();
        }
    }
}

function display_login_form($msg='') {
    echo '<head> 
            <meta charset="UTF-8"> 
            <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
            <title>Gradebook</title> 
            <style>
                body {
                    background-color: #f2f2f2;
                }
                
                .login-form {
                    background-color: #fff;
                    border-radius: 5px;
                    padding: 20px;
                    margin: 50px auto;
                    max-width: 500px;
                }
                
                .login-form h1 {
                    text-align: center;
                    margin-bottom: 30px;
                }
                
                .error-msg {
                    color: red;
                    margin-bottom: 10px;
                }
            </style>
        </head>';
    echo '<body> 
            <div class="login-form">
                <h1><b>Gradebook Accessing Page</b></h1>
                <form action="login.php" method="post"> 
                    <fieldset name="logininfo">
                        <legend>My Gradebooks</legend>
                        <label for="email">Email:</label>
                        <input type="text" name="email" id="email" class="form-control">
                        <input type="submit" name="login" value="Login" class="btn">
                    </fieldset>
                </form>';
    if ($msg) {
        echo '<div class="error-msg">'.$msg.'</div>';
    }
    echo '</div>
        </body>';
}
 
function authenticate() {
    define("DB_HOST", "mydb");
    define("USERNAME", "dummy");
    define("PASSWORD", "c3322b");
    define("DB_NAME", "db3322");
    $conn=mysqli_connect(DB_HOST, USERNAME, PASSWORD, DB_NAME) or die('Error! '. mysqli_connect_error($conn));

    $query = 'SELECT * FROM user';
    $result = mysqli_query($conn, $query) or die ('Failed to query '.mysqli_error($conn));

    if (isset($_SESSION['email'])) { //if already authenticated
        if ($_SESSION['time_email_inputted'] + 300 < time()){
            display_login_form("Session expired. Please login again.");
            logout();
        } 
        return true;

    } else if (isset($_POST['email'])) {
        $email = $_POST['email'];

        // Check if the email is valid with the correct domain
        $valid_domains = array('cs.hku.hk', 'connect.hku.hk');
        $email_parts = explode('@', $email);
        if (count($email_parts) !== 2 || !in_array($email_parts[1], $valid_domains)) {
            display_login_form("Must be an email address with @cs.hku.hk or @connect.hku.hk");
            return false;
        }

        while ($row = mysqli_fetch_array($result)) {
            if ($email == $row['email']) {

                // store email and uid in session
                $_SESSION['email'] = $row['email']; 
                $_SESSION['uid'] = $row['uid'];

                // store time email inputted and the session
                $time_email_inputted = time();
                $_SESSION['time_email_inputted'] = $time_email_inputted; //this the 300secs one
            
                // make otp now and the session
                $otp = make_otp($conn, $email, $time_email_inputted);
                $_SESSION['token'] = $otp;
                
                // send otp to email and make session for time otp sent
                send_otp($email, $otp);
                $time_otp_sent = time();
                $_SESSION['time_otp_sent'] = $time_otp_sent; //this the 60secs one

                session_write_close();
                return true;
            }   
        }
        return false;
    }
}

function make_otp($conn, $email, $time_email_inputted){
    $otp = bin2hex(random_bytes(8));
    $query = "UPDATE user SET secret = '$otp' WHERE email='$email'";
    $query2 = "UPDATE user SET timestamp = '$time_email_inputted' WHERE email='$email'";
    $result = mysqli_query($conn, $query);
    $result2 = mysqli_query($conn, $query2);
    return $otp;
}

function send_otp($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        //template                  
        $mail->isSMTP();                                            
        $mail->Host       = 'testmail.cs.hku.hk';                    
        $mail->SMTPAuth   = false;                                   
        $mail->Port       = 25;                                 

        //Sender
        $mail->setFrom('c3322@cs.hku.hk', 'COMP3322');
        $mail->addAddress($email, 'name of recipient');  
        
        //Content
        $mail->isHTML(true);                                  
        $mail->Subject = 'Send by PHPMailer';

        $mail->Body    = 'Click this link: http://localhost:9080/login.php?token='.$otp;
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        display_login_form("Please check your email for the authentication URL.");

    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function display_secured_content() {
    if ($_SESSION['time_otp_sent'] + 60 > time()) { //if time now less than 60 seconds, go to index
        header('location: ./courseinfo/index.php?uid='.$_SESSION['uid']);
    } else { //if time more than 60 seconds, fail to authenticate and logout
        display_login_form('Fail to authenticate - OTP expired!'); 
        logout();
    }
}

function logout() {
    define("DB_HOST", "mydb");
    define("USERNAME", "dummy");
    define("PASSWORD", "c3322b");
    define("DB_NAME", "db3322");
    $conn=mysqli_connect(DB_HOST, USERNAME, PASSWORD, DB_NAME) or die('Error! '. mysqli_connect_error($conn));
    
    $user = $_SESSION['email'];
    $query = "UPDATE user SET secret = NULL WHERE email='$user'";
    $query2 = "UPDATE user SET timestamp = NULL WHERE email='$user'";
    $result = mysqli_query($conn, $query);
    $result2 = mysqli_query($conn, $query2);

    error_reporting(E_ERROR | E_PARSE);
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(),'',time()-3600, '/');
    }

    session_unset();
    session_destroy();
}
?>
