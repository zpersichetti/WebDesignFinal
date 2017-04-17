<?php error_reporting(E_ALL ^ E_NOTICE);
/**
* aFormMail script - sending mail via form
*
*     Author: Alex Scott
*      Email: support@php-form-mail.com
*        Web: http://www.php-form-mail.com/
*    Details: The installation file
*    Release: 1.5 ($Id: aformmail.php 128 2005-12-19 21:04:11Z alex $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.php-form-mail.com/forum/
*                                                                          
* aFormMail is free for both commercial and non-commercial use.
* Re-distribution of this script without prior consent is strictly prohibited.
*                                                                                 
*/

/*****************************************************************************
 *                                                                           *
 *                C  O  N  F  I  G  U  R  A  T  I  O  N                      *
 *                                                                           *
 *****************************************************************************/

// Step 1. Recepient of the e-mail from the form (e-mail)
$send_to = "Steven Tynan<steven.tynan@ymail.com>";

// Step 1a (Optional): You can set a $send_cc address if you need a copy of the e-mail to other addresses
// for example: $send_cc = array('friend1@ccc.cc', 'friend2@ccc.cc');
$send_cc = array(); 

// Step 2. Write a subject of the e-mail.
$subject = "Personal Training Form";

// Allowed Referrers. Should be empty or list of domains
$referrers = array(); 

// Step 3. Allow or disallow attachments.
// If you do not use attachments, you can change it to 0.
$attachment_enabled = 0;

// Database - write CSV file with data of submitted forms
$database_enabled = 0;
$database_file = 'email320.csv';

// Step 4. Choose from which fields you would like to collect information:
// $database_fields = '*' â†’ That means, information from all form fields will be collected
// $database_fields = array('from', 'subject') - only information from 'from' and 'subject' fields will be collected
$database_fields = '*';

// Step 5. Enter the page to which the browser will be redirected after the user have successfully submitted the form
// When you enter it, do not forget the folder structure, if the files are not in the same folder. 
$redirect_url = 'success.html';

// Step 6. Adjust the auto-responder, i.e. the e-mail that will be automatically sent to the user after submitting the form.
// You can substitute any of form fields in the auto-responder by using %field_name% in response text.

$autoresponder_enabled = 1;
$autoresponder_from = $send_to;
$autoresponder_subject = "Thank You for signing up for the newsletter!";
$autoresponder_message = <<<MSG
Dear %name_from%,

Thank you for signing up for the newsletter.

We will contact you shortly.

NFL News;

/* Do not edit anything below this line. */
/***************************************************************************/

function do_formmail(){
    global $autoresponder_enabled, $database_enabled;
    $form      = get_form_data();
    $errors    = check_form($form);
    if ($errors) {
        display_errors($errors);
        return;
    }
    send_mail($form);
    if ($autoresponder_enabled) 
        auto_respond($form);
    if ($database_enabled)
        save_form($form);
    redirect();
}

function redirect(){
    global $redirect_url;
    header("Location: $redirect_url");
    exit();
}


function save_form($vars){
    global $database_file, $database_fields;
    $f = fopen($database_file, 'a');
    if (!$f){
        die("Cannot open db file for save");
    }
    foreach ($vars as $k=>$v) {
        $vars[$k] = str_replace(array("|", "\r","\n"), array('_',' ',' '), $v);
    }
    if (is_array($database_fields)) {
        $vars_orig = $vars; 
        $vars = array();
        foreach ($database_fields as $k)
            $vars[$k] = $vars_orig[$k];
    }
    $str = join('|', $vars);
    fwrite($f, $str."\n");
    fclose($f);
}

function auto_respond($vars){
    global $autoresponder_from, $autoresponder_message, $autoresponder_subject;
    /// replace all vars in message
    $msg = $autoresponder_message;
    preg_match_all('/%(.+?)%/', $msg, $out);
    $s_vars = $out[1]; //field list to substitute
    foreach ($s_vars as $k)
        $msg = str_replace("%$k%", $vars[$k], $msg);
    /// replace all vars in subject
    $subj = $autoresponder_subject;
    preg_match_all('/%(.+?)%/', $subj, $out);
    $s_vars = $out[1]; //field list to substitute
    foreach ($s_vars as $k)
        $subj = str_replace("%$k%", $vars[$k], $subj);
    //
    $_send_to = "$vars[name_from] <".$vars[email_from].">";
    $_send_from = $autoresponder_from;
    mail($_send_to, $subj, $msg, "From: $_send_from");
}

function _build_fields($vars){
    $skip_fields = array(
        'name_from', 
        'email_from', 
        'email_to', 
        'name_to', 
        'subject');
    // order by numeric begin, if it exists
    $is_ordered = 0;
    foreach ($vars as $k=>$v) 
        if (in_array($k, $skip_fields)) unset($vars[$k]);

    $new_vars = array();
    foreach ($vars as $k=>$v){
        // remove _num, _reqnum, _req from end of field names
        $k = preg_replace('/_(req|num|reqnum)$/', '', $k);
        // check if the fields is ordered
        if (preg_match('/^\d+[ \:_-]/', $k)) $is_ordered++;
        $new_vars[$k] = $v;
    }
    $vars = $new_vars;

    $max_length = 10; // max length of key field 
    foreach ($vars as $k=>$v) {
        $klen = strlen($k);
        if (($klen > $max_length) && ($klen < 40))
            $max_length = $klen;
    }

    if ($is_ordered){
        ksort($vars);
        $new_vars = array();
        foreach ($vars as $k=>$v){
            //remove number from begin of fields
            $k = preg_replace('/^\d+[ \:_-]/', '', $k);
            $new_vars[$k] = $v;
        }
        $vars = $new_vars;
    }

    // make output text
    $out = "";
    foreach ($vars as $k=>$v){
        $k = str_replace('_', ' ', $k);
        $k = ucfirst($k);
        $len_diff = $max_length - strlen($k);
        if ($len_diff > 0) 
            $fill = str_repeat('.', $len_diff);
        else 
            $fill = '';
        $out .= $k."$fill...: $v\n";
    }
    return $out;
}


function send_mail($vars){
    global $send_to, $send_cc;
    global $subject;
    global $attachment_enabled;

    $files = array(); //files (field names) to attach in mail
    if (count($_FILES) && $attachment_enabled){
        $files = array_keys($_FILES);
    }

    // build mail
    $date_time = date('Y-m-d H:i:s');
    $mime_delimiter = "----=_NextPart_000_0001_".md5(time());
    $fields = _build_fields($vars);
    $mail = 
"This is a multi-part message in MIME format.
    
--$mime_delimiter
Content-type: text/plain
Content-Transfer-Encoding: 8bit
Content-Disposition: inline

Information submitted via the Sample Form for the Web Design Course:

$fields 
--------------------
REMOTE IP : $_SERVER[REMOTE_ADDR]
DATE/TIME : $date_time
";

    if (count($files)){
        foreach ($files as $file){
            $file_name     = $_FILES[$file]['name'];
            $file_type     = $_FILES[$file]['type'];
            $file_tmp_name = $_FILES[$file]['tmp_name'];
            $file_cnt = "";
            $f=@fopen($file_tmp_name, "rb");
            if (!$f) 
                continue;
            while($f && !feof($f))
                $file_cnt .= fread($f, 4096);
            fclose($f);
            if (!strlen($file_type)) $file_type="applicaton/octet-stream";
            if ($file_type == 'application/x-msdownload')
                $file_type = "applicaton/octet-stream";

            $mail .= "\n--$mime_delimiter\n";
            $mail .= "Content-Type: $file_type;\n       name=\"$file_name\"\n";
            $mail .= "Content-Transfer-Encoding: base64\n";
            $mail .= "Content-Disposition: attachment;\n       filename=\"$file_name\"\n\n";
            $mail .= chunk_split(base64_encode($file_cnt));
        }
    }
    $mail .= "\n--$mime_delimiter--";


    //send to
    $_send_to = $send_to ? $send_to : "$vars[name_to] <".$vars[email_to].">";
    $_send_from = "$vars[name_from] <".$vars[email_from].">";
    $_subject = $subject ? $subject : $vars['subject'];

    mail($_send_to, $_subject, $mail, 
    "MIME-Version: 1.0\nFrom: $_send_from\nContent-Type: multipart/mixed;\n    boundary=\"$mime_delimiter\"\n");

    foreach ($send_cc as $v){
      mail($v, $_subject, $mail, 
    "MIME-Version: 1.0\nFrom: $_send_from\nContent-Type: multipart/mixed;\n    boundary=\"$mime_delimiter\"\n");
    }
}

function get_form_data(){
    $vars = ($_SERVER['REQUEST_METHOD'] == 'GET') ? $_GET : $_POST;
    //strip spaces from all fields
    foreach ($vars as $k=>$v) $vars[$k] = trim($v);
    if (get_magic_quotes_gpc())
        foreach ($vars as $k=>$v) $vars[$k] = stripslashes($v);
        
    if (isset($vars['name_from']))
        $vars['name_from'] = preg_replace("/[^\w\d\t\., _-]/", "", $vars['name_from']);
    if (isset($vars['email_from']))
        $vars['email_from'] = preg_replace("/[^@\w\.\d_-]/", "", $vars['email_from']);
    if (isset($vars['subject']))
        $vars['subject'] = preg_replace("/[^\w\d\t \".,;:#\$%^&\*()+=`~\|_-]/", "", $vars['subject']);

    return $vars;
}

function check_form($vars){
    global $referrers;
    global $send_to;
    global $subject;

    $errors = array();

    // check from email set
    if (!strlen($vars['email_from'])){
        $errors[] = "Please provide your e-mail address.";
    } else if (!check_email($vars['email_from'])){
        $errors[] = "Please provide your e-mail address.";        
    }                 
    if (!strlen($send_to) && !strlen($vars['email_to'])){
        $errors[] = "<b>To Email</b> address empty (possible configuration error)";
    } else if (!strlen($send_to) && !check_email($vars['email_to'])){
        //if to email specified in form, check it and display error
        $errors[] = "<b>To Email address</b> incorrect";        
    }
    if (!strlen($vars['subject']) && !strlen($subject)){
        $errors[] = "<b>Subject</b> empty (possible configuration error)";
    }
    foreach ($vars as $k=>$v){
        // check for required fields (end with _req)
        if (preg_match('/^(.+?)_req$/i', $k, $m) && !strlen($v)){
            $field_name = ucfirst($m[1]);
            $errors[] = "Required field <b>$field_name</b> empty";
        }
        // check for number fields (end with _num)
        if (preg_match('/^(.+?)_num$/i', $k, $m) && strlen($v) && !is_numeric($v)){
            $field_name = ucfirst($m[1]);
            $errors[] = "Field <b>$field_name</b> must contain only digits or be empty";
        }
        // check for number & required fields (end with _reqnum)
        if (preg_match('/^(.+?)_reqnum$/i', $k, $m) && !is_numeric($v)){
            $field_name = ucfirst($m[1]);
            $errors[] = "Field <b>$field_name</b> must contain digits and only digits";
        }
    }

    //check referrer
    if (is_array($referrers) && count($referrers)){
        $ref = parse_url($_SERVER['HTTP_REFERER']);
        $host = $ref['host'];
        $host_found = 0;
        foreach ($referrers as $r){
            if (strstr($host, $r)) 
                $host_found++;
        }
        if (!$host_found){
            $errors[] = "Unknown Referrer: <b>$host</b>";
        }
    }
    return $errors;
}

function display_errors($errors){
$errors = '<li>' . join('<li>', $errors);
print <<<EOF
<html>
    <head>
		<title>550 Sample Form - Error</title>
		<link rel="stylesheet" type="text/css" href="styles.css" />
    </head>
	<body>
    	<h1 style="margin:25px; color:red;">An Error Occured</h1>
		$errors
		<a href="javascript: history.back(-1)">Return</a> and fix these errors 
    </body>
</html>
EOF;
}


/**
* Check email using regexes
* @param string email
* @return bool true if email valid, false if not
*/
function check_email($email) {
    #characters allowed on name: 0-9a-Z-._ on host: 0-9a-Z-. on between: @
    if (!preg_match('/^[0-9a-zA-Z\.\-\_]+\@[0-9a-zA-Z\.\-]+$/', $email))
        return false;

    #must start or end with alpha or num
    if ( preg_match('/^[^0-9a-zA-Z]|[^0-9a-zA-Z]$/', $email))
        return false;

    #name must end with alpha or num
    if (!preg_match('/([0-9a-zA-Z_]{1})\@./',$email) )                    
        return false;

    #host must start with alpha or num
    if (!preg_match('/.\@([0-9a-zA-Z_]{1})/',$email) )                    
        return false;

    #pair .- or -. or -- or .. not allowed
    if ( preg_match('/.\.\-.|.\-\..|.\.\..|.\-\-./',$email) )
        return false;

    #pair ._ or -_ or _. or _- or __ not allowed
    if ( preg_match('/.\.\_.|.\-\_.|.\_\..|.\_\-.|.\_\_./',$email) )
        return false;

    #host must end with '.' plus 2-5 alpha for TopLevelDomain
    if (!preg_match('/\.([a-zA-Z]{2,5})$/',$email) )
        return false;

    return true;
}

do_formmail();
?>
