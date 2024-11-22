<?php

/*

Noter Backend v1.0d (much better than old version)
(C)2021-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

 Actions:
----------
register	User registration
change		Change user password
info		Get user information
remove		User removal
list		Brief notes listing
retrieve	Get note
add		Add note
update		Update note
delete		Delete note
lock		Lock note
unlock		Unlock note

 Modifiers:
------------
compress	Compress output data using BZip2 (output compressed data only if it makes any sense)

*/

// INCLUDES SECTION
include_once('noter-config.php');
include_once('noterapi.php');
include_once('noterconst.php');


// HELPER FUNCTIONS SECTION
// Add necessary headers to the HTTP response
function addHeaders($dataCompressed = false) {
	header("Content-Type: application/json");
	if($dataCompressed) {
		header("X-BZ-Compressed: yes");
	}
}


// MAIN PART OF THE BACKEND
// Turn off printing warnings and most errors (locally) as these may corrupt JSON output
error_reporting(E_ERROR | E_PARSE);

// Check if client requested compression (only BZip2 available in this version of backend)
if(array_key_exists("compress",$_GET)) {
	$compress=($_GET["compress"]=="yes");
} else if(array_key_exists("compress",$_POST)) {
	$compress=($_POST["compress"]=="yes");
} else {
	$compress=false;
}

// Prepare server information part of response
$server_info=array(	"name"		=> NOTER_NAME,
			"timezone"	=> NOTER_TIMEZONE,
			"version"	=> "1.0");	// Yup, backend version is hardcoded

// Initialize response array
$response=array();

// Check if service is enabled
if(!NOTER_ENABLED) {
	// If not, just set the proper error code
	$answer_info=answerInfo(ERROR_SERVICE_DISABLED);
}
// If service is enabled, then run main part of the backend (a bit ugly to be sure)
else if($_SERVER["REQUEST_METHOD"]=="GET") {
	// Answer default information only
	$answer_info=answerInfo(INFO_OK);
}
// And here goes the very main part of the backend (yes, it was a bit stupid to just use POST for everything...)
else if($_SERVER["REQUEST_METHOD"]=="POST") {
	// Check if necessary information provided
	if(array_key_exists("action",$_POST) && (array_key_exists("username",$_POST)) && array_key_exists("password",$_POST)) {
		// Trim everything
		$action=trim($_POST["action"]);
		$username=trim($_POST["username"]);
		$password=trim($_POST["password"]);
		// Initialize some variables
		$userID=0;
		$answer=null;
		try {
			// If credentials are empty after trimming
			if(($username=="") || ($password=="")) {
				// Then there is no credentials provided
				$answer_info=answerInfo(ERROR_NO_CREDENTIALS);
			}
			// User register action
			else if($action=="register") {
				list($answer_info,$answer)=userRegister($username,$password);
			}
			// User password change action
			else if($action=="change") {
				if(array_key_exists("newPassword",$_POST)) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=userChangePassword($userID,trim($_POST["newPassword"]));
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// User info action
			else if($action=="info") {
				if(tryLogin($username,$password,$userID,$answer_info)) {
					list($answer_info,$answer)=userInfo($userID);
				}
			}
			// User remove action
			else if($action=="remove") {
				if(tryLogin($username,$password,$userID,$answer_info)) {
					list($answer_info,$answer)=userRemove($userID);
				}
			}
			// Notes list action
			else if($action=="list") {
				if(tryLogin($username,$password,$userID,$answer_info)) {
					list($answer_info,$answer)=noteList($userID);
				}
			}
			// Note retrieve action
			else if($action=="retrieve") {
				if(array_key_exists("noteID",$_POST)) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=getNote($userID,$_POST["noteID"]);
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// Note add action
			else if($action=="add") {
				if(array_key_exists("subject",$_POST) && array_key_exists("entry",$_POST)) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=addNote($userID,trim($_POST["subject"]),trim($_POST["entry"]));
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// Note update action
			else if($action=="update") {
				if((array_key_exists("noteID",$_POST)) && (array_key_exists("subject",$_POST)) && (array_key_exists("entry",$_POST))) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=updateNote($userID,trim($_POST["subject"]),trim($_POST["entry"]),$_POST["noteID"]);
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// Note delete action
			else if($action=="delete") {
				if(array_key_exists("noteID",$_POST)) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=deleteNote($userID,$_POST["noteID"]);
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// Note lock action
			else if($action=="lock") {
				if(array_key_exists("noteID",$_POST)) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=lockNote($userID,$_POST["noteID"]);
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// Note unlock action
			else if($action=="unlock") {
				if(array_key_exists("noteID",$_POST)) {
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=unlockNote($userID,$_POST["noteID"]);
					}
				}
				else {
					$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
				}
			}
			// If unknown action used
			else {
				// Then answer with error
				$answer_info=answerInfo(ERROR_UNKNOWN_ACTION);
			}
		}
		// If any error occurred
		catch(Throwable $e) {
			// Then it's internal server (backend) error probably
			$answer_info=answerInfo(ERROR_INTERNAL_SERVER_ERROR);
		}
	}
	// If no usable information
	else {
		$answer_info=answerInfo(ERROR_NO_USABLE_INFORMATION);
	}
}
// Invalid HTTP method maybe?
else {
	$answer_info=answerInfo(ERROR_INVALID_METHOD);
}

// Construct backend response
$response=array("server" => $server_info,
		"answer_info" => $answer_info);
if(isset($answer) && $answer!=null) {
	$response["answer"]=$answer;
}

// Convert response to the JSON format
$outputData=json_encode($response);

// Apply compression filter (if needed)
$compressData = false;
if($compress) {
	$outputData2=bzcompress($outputData,9);
	$compressData=strlen($outputData2)<strlen($outputData);
	if($compressData) {
		$outputData=$outputData2;
	}
}

// Add all necessary headers
addHeaders($compressData);

// Output JSON
echo $outputData;

?>
