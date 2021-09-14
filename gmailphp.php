<?php
/*
##		Title:		gMail Signup Script V00.00.01
##		Author(s):	Jack Ketch
##		Last Update:	06-14-2008
##
##			Semi-automatic script to generate free GMail accounts,
##
##		Modified:	Anand (netmktg / netmktg7)
##		Last Update:	Oct-12-2008
##		This script is an extensively modified version of the original script
##
*/

require_once('opendb.php');
require_once('mailsignup-functions.php');

error_reporting(E_ALL ^ E_NOTICE);

$tmpdir = 'tmp/';
$tmpdir_abs = dirname($_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME']) .'/refererx/'. $tmpdir;

$submitted = isset($_POST['Create_Account']);

if ($submitted) {
	unlink($_POST['captcha_tmpfile']);

	$formLocation = urldecode($_POST['formlocation']);
	$firstname = $_POST['firstname'];	
	$lastname = $_POST['lastname'];	
	$username = $_POST['username'];	
	$password = $_POST['password'];	
	$alt_email = $_POST['alt_email'];	
	$captcha = $_POST['captcha'];
	$postQuery = $_POST['params'];
	$ref = $_POST['ref'];
	
	// $write_to_file = $_POST['write_to_file'];
	// if (empty($write_to_file)) { $write_to_file = 'new-gmails.txt'; }

	// the CAPTCHA is placed in the parameters, and then all the parameters are placed //
	// in a single string, each one separated by '&' //
	$captcha_array_key = array_search('newaccountcaptcha=',$postQuery);
	$postQuery [$captcha_array_key] .= $captcha;
	$query = $postQuery[0];
	for ($x = 1; $x < count($postQuery); $x++) { $query .= "&" . $postQuery[$x]; }

	$ch = curl_init();

	$cookiefile = $tmpdir_abs . '_cookie.txt';
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiefile);

	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.14) Gecko/20080404 Firefox/2.0.0.14");
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
	curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

	$headers[] = "Cookie: X=abc; GoogleAccountsLocale_session=en; TZ=-330";
	$headers[] = "Content-Type: application/x-www-form-urlencoded";
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	curl_setopt($ch, CURLOPT_URL, $formLocation);
  	curl_setopt($ch, CURLOPT_REFERER, $ref);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
	$page = curl_exec($ch);

	if ( strpos($page, 'but the following usernames are') ) {
		print '<font color="#0000FF"><b>Gmail User NOT Available</b></font>';
		return;
	}

	if ( strpos($page, 'characters you entered didn') ) { $captcha_msg = '<font color="#FF0000"><b>WRONG Captcha</b></font><br>'; }

	if ( strpos($page, 'enter the letters as they are shown in the new image') ) { $captcha_msg = '<font color="#0000FF"><b>ADDITIONAL Captcha</b></font><br>'; }


	$chk_pos = strpos($page, '<form id="createaccount"');

	if ($chk_pos > 0)
	{
		// Gmail wants us to enter Addtional Captcha
		$page = substr($page, $chk_pos);
		
		$ref = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		$parameters = parse_gmail_fields($page, $username, $password, $firstname, $lastname, $alt_email);
		$formLocation = array_pop($parameters);
		$captchatoken = array_pop($parameters);

		$captcha_tmpfile = save_captcha($ch, $captchatoken, $tmpdir, $tmpdir_abs);
		curl_close($ch);

		// Display CAPTCHA to solve
		$currentUrl = $PHP_SELF;

		$hidden = '';
		foreach($parameters as $param){
			$hidden .= "<input type='hidden' name='params[]' value='$param'>\
";	
		}

		$form = <<<INPUT_MYTEXT
	<center> <table> <tr> <td>
	<form method='post' action='$currentUrl'>
	
		$hidden
		<input type='hidden' name='ref' value='$ref'>
		<input type='hidden' name='firstname' value='$firstname'>
		<input type='hidden' name='lastname' value='$lastname'>	
		<input type='hidden' name='username' value='$username'>	
		<input type='hidden' name='password' value='$password'>	
		<input type='hidden' name='alt_email' value='$alt_email'>	
		<input type='hidden' name='formlocation' value='$formLocation'>	
		<input type='hidden' name='captcha_tmpfile' value='$tmpdir_abs$captcha_tmpfile'>	

		<table>
		<tr>
			<td align="center" colspan="2">$captcha_msg</td>
		</tr>
		<tr> <td align="center" colspan="2">&nbsp;</td> </tr>
		<tr>
			<td>Username:</td>
			<td><b>$username</b>@gmail-com</td>
		</tr>
		<tr>
			<td>Password:</td>
			<td><b>$password</b></td>
		</tr>
		<tr>
			<td>Name:</td>
			<td><b>$firstname $lastname</b></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><img src="$tmpdir$captcha_tmpfile"></td>
		</tr>
		<tr>
			<td>Captcha:</td>
			<td><input type="text" name="captcha" id="captcha"></td>
		</tr>
		<tr>
			<td align="center" colspan="2"><input type="submit" name="Create_Account" value="Create Account"></td>
		</tr>
		</table>
	</form>
	</td> </tr> </table> </center>

	<script type="text/javascript">
		document.getElementById('captcha').focus()
	</script>
INPUT_MYTEXT;

		echo $form;
		return;
	}
		
	if ( strpos($page, '<meta http-equiv') ) {
		// Follow the Meta redirect
		$google_meta_regex = '/\\<meta http-equiv.+?refresh.+?(http:\\/\\/[^\\'^\\"^\\>]+?)('){0,1}(\\"){0,1}\\>/i';
		preg_match($google_meta_regex,$page,$m);
		$curl_url = $m[1];
		$curl_url = str_replace('&amp;', '&', $curl_url);

		$headers[] = "Cookie: X=abc; GoogleAccountsLocale_session=en; TZ=-330";
		$headers[] = "Content-Type: application/x-www-form-urlencoded";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	        curl_setopt($ch, CURLOPT_URL, $curl_url);
		curl_setopt($ch, CURLOPT_POST, 0);
	        $page = curl_exec($ch);
	}

	curl_close($ch);

	$succeeded = stripos($page, "Congratulations!");
	if ($succeeded) {
		@ unlink($cookiefile);
		$info = <<<INPUT_MYTEXT
			<center> <table>
			<tr>
				<td>Username:</td>
				<td><input type='text' id='username' name='username' value='$username@gmail-com' onClick="javascript:x = document.getElementById('username');x.focus();x.select();"></td>
			</tr>
			<tr>
				<td>Password:</td>
				<td><input type='text' id='password' name='password' value='$password' onClick="javascript:x = document.getElementById('password');x.focus();x.select();"></td>
			</tr>
			<tr>
				<td>Name:</td>
				<td><input type='text' id='name' name='name' value='$firstname $lastname' onClick="javascript:x = document.getElementById('password');x.focus();x.select();"></td>
			</tr>
			<tr>
				<td align="center" colspan="2"><font color="#00CC00"><b>••••&nbsp; GMail SUCCESS &nbsp;••••</b></font></td>
			</tr>
			<tr> <td align="center" colspan="2">&nbsp;</td> </tr>
			<tr> <td align="center" colspan="2">&nbsp;</td> </tr>
			</table>
			
			</center>
INPUT_MYTEXT;

		echo $info;

		//$sqlquery  = "INSERT IGNORE INTO emails (email,email_password,email_name) VALUES ('$username@gmail-com', '$password', '$firstname $lastname')";
		//mysql_query($sqlquery);

		
		// Write Signup data to existing file; on error use new filename
		$write_str = "$username@gmail-com,$password,$firstName $lastName\
";
		$fh = false;
		@ $fh = fopen($write_to_file, 'a');
		if (!$fh) {
			$write_to_file = 'new-gmails_' . rand(1000,100000) . '.txt';
			$fh = fopen($write_to_file, 'a');
		}
		fwrite($fh, $write_str);
		fclose($fh);
		print "</br>Written Login Data to $write_to_file</br></br>\
";
		
	}
	else {
		print '</br></br><font color="#FF0000"><b>Signup process FAILED</b></font>';
		print $page;
		return;
	}
}


$ch = curl_init();

$cookiefile = $tmpdir_abs . '_cookie.txt';
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiefile);
curl_setopt($ch, CURLOPT_COOKIESESSION, 1);

curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.14) Gecko/20080404 Firefox/2.0.0.14");
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$url = 'hxxp : / / mail-google-com / mail / signup';
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 0);
$page = curl_exec($ch);

// Get the Last effective Url to set Referer in subsequent Curl operations
$ref = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

// Generates random user profile for email signup
$gProfile = new profile();
$gProfile->__construct();
$firstname = $gProfile->getName("f");
$lastname = $gProfile->getName("l");
$username = $gProfile->getUsername();
$password = $gProfile->getPassword();
$alt_email = $gProfile->getAlternateEmail();

$parameters = parse_gmail_fields($page, $username, $password, $firstname, $lastname, $alt_email);
$formLocation = array_pop($parameters);
$captchatoken = array_pop($parameters);

$captcha_tmpfile = save_captcha($ch, $captchatoken, $tmpdir, $tmpdir_abs);
curl_close($ch);

  // Display CAPTCHA to solve
  $currentUrl = $PHP_SELF;

  $hidden = '';
  foreach($parameters as $param){
	$hidden .= "<input type='hidden' name='params[]' value='$param'>\
";	
  }

  $form = <<<INPUT_MYTEXT
	<center> <table> <tr> <td>
	<form method='post' action='$currentUrl'>

		$hidden
		<input type='hidden' name='ref' value='$ref'>
		<input type='hidden' name='firstname' value='$firstname'>
		<input type='hidden' name='lastname' value='$lastname'>	
		<input type='hidden' name='username' value='$username'>	
		<input type='hidden' name='password' value='$password'>	
		<input type='hidden' name='alt_email' value='$alt_email'>	
		<input type='hidden' name='formlocation' value='$formLocation'>	
		<input type='hidden' name='captcha_tmpfile' value='$tmpdir_abs$captcha_tmpfile'>	

		<table>
		<tr>
			<td>Username:</td>
			<td><b>$username</b>@gmail-com</td>
		</tr>
		<tr>
			<td>Password:</td>
			<td><b>$password</b></td>
		</tr>
		<tr>
			<td>Name:</td>
			<td><b>$firstname $lastname</b></td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td><img src="$tmpdir$captcha_tmpfile"></td>
		</tr>
		<tr>
			<td>Captcha:</td>
			<td><input type="text" name="captcha" id="captcha"></td>
		</tr>
		<tr>
			<td align="center" colspan="2"><input type="submit" name="Create_Account" value="Create Account"></td>
		</tr>
		</table>
	</form>
	</td> </tr> </table> </center>

	<script type="text/javascript">
		document.getElementById('captcha').focus()
	</script>
INPUT_MYTEXT;

echo $form;


//  ###  End Main Routine  ###


function parse_gmail_fields($page, $username, $password, $firstname, $lastname, $alt_email)
{
  // removes new lines, and multiple spaces to for proper Regex matching
  $page = clean_whitespace($page);

  preg_match("/<form id=\\"createaccount\\" name=\\"createaccount\\" action='(.+?)'/", $page, $m);
  $formLocation = urlencode($m[1]);

  preg_match("/<input type=\\"hidden\\" name=\\"type\\" id=\\"type\\" value=\\"(.+?)\\"/", $page, $m);
  $parameters[] = "type=" . $m[1];

  $parameters[] = "loc=US";

  preg_match("/<input type=\\"hidden\\" name=\\"dsh\\" id=\\"dsh\\" value=\\"(.+?)\\"/", $page, $m);
  $parameters[] = "dsh=" . $m[1];

  $parameters[] = "ktl=";
  $parameters[] = "ktf=";

  $parameters[] = "FirstName=" . $firstname;
  $parameters[] = "LastName=" . $lastname;
  $parameters[] = "UsernameSelector=header";
  $parameters[] = "Email=" . $username;

  preg_match("/<input type=\\"hidden\\" id='edk' name='edk' value='(.+?)'/", $page, $m);
  $parameters[] = "edk=" . $m[1];

  $parameters[] = "Passwd=" . $password;
  $parameters[] = "PasswdAgain=" . $password;

  $parameters[] = "rmShown=1";
  $parameters[] = "nshk=1";

  $parameters[] = "selection=" . urlencode("What was your first teacher's name");
  $parameters[] = "ownquestion=";
  $parameters[] = "IdentityAnswer=" . $firstname;

  $parameters[] = "SecondaryEmail=" . urlencode($alt_email);

  $parameters[] = "loc=US";

  // get CAPTCHA token
  preg_match("/<input type=\\"hidden\\" name=\\"newaccounttoken\\" id=\\"newaccounttoken\\" value=\\"(.+?)\\"/", $page, $m);
  $captchatoken = $m[1];
  $parameters[] = "newaccounttoken=" . urlencode($captchatoken);

  // get CAPTCHA url
  preg_match("/<input type=\\"hidden\\" name=\\"newaccounturl\\" id=\\"newaccounturl\\" value=\\"(.+?)\\"/", $page, $m);
  $parameters[] = "newaccounturl=" . urlencode($m[1]);

  // get Audio CAPTCHA token
  preg_match("/<input type=\\"hidden\\" name=\\"newaccounttoken_audio\\" id=\\"newaccounttoken_audio\\" value=\\"(.+?)\\"/", $page, $m);
  $parameters[] = "newaccounttoken_audio=" . urlencode($m[1]);

  // get Audio CAPTCHA url
  preg_match("/<input type=\\"hidden\\" name=\\"newaccounturl_audio\\" id=\\"newaccounturl_audio\\" value=\\"(.+?)\\"/", $page, $m);
  $parameters[] = "newaccounturl_audio=" . urlencode($m[1]);

  $parameters[] = "newaccountcaptcha=";
  $parameters[] = "program_policy_url=http%3A%2F%2Fmail-google-com%2Fmail%2Fhelp%2Fprogram_policies.html";
  $parameters[] = "privacy_policy_url=http%3A%2F%2Fwww-google-com%2Fintl%2Fen%2Fprivacy.html";
  $parameters[] = "requested_tos_location=US";
  $parameters[] = "requested_tos_language=en";

  preg_match('/<input type=\\"hidden\\" id=\\'served_tos_location\\' name=\\'served_tos_location\\' value=\\'(.+?)\\'/', $page, $m);
  $parameters[] = "served_tos_location=" . $m[1];

  $parameters[] = "served_tos_language=en";
  $parameters[] = "submitbutton=" . urlencode('I accept. Create my account.');

  $parameters[] = $captchatoken;
  $parameters[] = $formLocation;

  return $parameters;
}


function save_captcha($ch, $captchatoken, $tmpdir, $tmpdir_abs)
{
  // Save Captcha image
  $url = "hxxps : / / www-google-com / accounts / Captcha?ctoken=$captchatoken";
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, 0);
  $page = curl_exec($ch);

  $captcha_tmpfile = 'captcha-' . rand(1000,10000) . '.jpg';
  $fp = fopen($tmpdir_abs . $captcha_tmpfile,'w');
  fwrite($fp, $page);
  fclose($fp);

  return $captcha_tmpfile;
}


?>
