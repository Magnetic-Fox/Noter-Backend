<?php

/*

NoterAPI v1.0d (even less ugly)
(C)2021-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

 Functions:
----------

userRegister		User registration
userChangePassword	Change user password
userInfo		Get user information
userRemove		User removal
noteList		Brief notes listing
getNote			Get note
addNote			Add note
updateNote		Update note
updateNoteSubject	Update note's subject
updateNoteEntry		Update note's entry
deleteNote		Delete note
lockNote		Lock note
unlockNote		Unlock note

*/

// INCLUDE SECTION
include_once('mysql-connect.php');
include_once('noter-config.php');
include_once('noterconst.php');


// HELPER FUNCTIONS SECTION
// Try login (or produce error response) function
function tryLogin($username, $password, &$outUserID, &$outAnswerInfo) {
	// Just login and set user ID variable passed as a parameter
	$outUserID=login($username,$password);
	// If login was successful
	if($outUserID>0) {
		// Then just return true and finish
		return true;
	}
	// If login wasn't successful
	else {
		// If user is deactivated
		if($outUserID<0) {
			// Set proper error information
			$outAnswerInfo=answerInfo(ERROR_USER_DEACTIVATED);
		}
		// If provided credentials are wrong
		else {
			// Set proper error information too
			$outAnswerInfo=answerInfo(ERROR_LOGIN_INCORRECT);
		}
		// And return false
		return false;
	}
}

// MySQL connection starter (preparing everything if needed)
function prepareConnection() {
	global $conn;
	if(!isset($conn)) {
		$conn=new mysqli(DB_SERVERNAME,DB_USERNAME,DB_PASSWORD,DB_NAME);
		mysqli_set_charset($conn,"utf8");
	}
	return;
}

// Proper data formatting function (this ugly backend was designed to work with kinda ugly client code)
function exportDate($dateString) {
	return date("Y-m-d H:i:s", strtotime($dateString));
}

// Function to get current date and time
function nowDate() {
	date_default_timezone_set(NOTER_TIMEZONE);
	$dt=DateTime::createFromFormat("U.u", microtime(true));
	$dt->setTimeZone(new DateTimeZone(NOTER_TIMEZONE));
	return $dt->format("Y-m-d H:i:s.u");
}

// Answer information packer
function answerInfo($code, $attachment = array()) {
	return array("code" => $code, "attachment" => $attachment);
}

// Function to test if username is not already chosen
function userExists($username) {
	global $conn;
	prepareConnection();
	$query="SELECT COUNT(*) FROM Noter_Users WHERE UserName=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("s",$username);
	$stmt->execute();
	$stmt->bind_result($resp);
	$stmt->fetch();
	return $resp;
}

// Function to "login" (check password and retrieve the user ID)
function login($username, $password) {
	global $conn;
	prepareConnection();
	if(userExists($username)) {
		$query="SELECT ID, UserName, PasswordHash, Active FROM Noter_Users WHERE UserName=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("s",$username);
		$stmt->execute();
		$stmt->bind_result($id,$username,$passwordHash,$active);
		$stmt->fetch();
		if($active) {
			if(password_verify($password,$passwordHash)) {
				return $id;
			}
		}
		else {
			return -1;
		}
	}
	return 0;
}

// Function to register new user
function register($username, $password) {
	global $conn;
	prepareConnection();
	if(userExists($username)) {
		return -1;
	}
	else {
		if(($username!="") && ($password!="")) {
			$now=nowDate();
			$passwordHash=password_hash($password,PASSWORD_DEFAULT);
			$query="INSERT INTO Noter_Users(UserName, PasswordHash, DateRegistered, RemoteAddress, ForwardedFor, UserAgent, LastChanged, LastRemoteAddress, LastForwardedFor, LastUserAgent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("ssssssssss",
					$username,
					$passwordHash,
					$now,
					$_SERVER["REMOTE_ADDR"],
					$_SERVER["HTTP_X_FORWARDED_FOR"],
					$_SERVER["HTTP_USER_AGENT"],
					$now,
					$_SERVER["REMOTE_ADDR"],
					$_SERVER["HTTP_X_FORWARDED_FOR"],
					$_SERVER["HTTP_USER_AGENT"]);
			$stmt->execute();
			if($conn->affected_rows!=-1) {
				return 1;
			}
		}
	}
	return 0;
}

// Function to change user's password
function userUpdate($userID, $newPassword) {
	global $conn;
	prepareConnection();
	if(($userID>0) && ($newPassword!="")) {
		$now=nowDate();
		$passwordHash=password_hash($newPassword,PASSWORD_DEFAULT);
		$query="UPDATE Noter_Users SET PasswordHash=?, LastChanged=?, LastRemoteAddress=?, LastForwardedFor=?, LastUserAgent=? WHERE ID=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("sssssi",
				$passwordHash,
				$now,
				$_SERVER["REMOTE_ADDR"],
				$_SERVER["HTTP_X_FORWARDED_FOR"],
				$_SERVER["HTTP_USER_AGENT"],
				$userID);
		$stmt->execute();
		if($conn->affected_rows!=-1) {
			return 1;
		}
	}
	return 0;
}

// Function to test if note is locked
function noteLocked($noteID) {
	global $conn;
	prepareConnection();
	$query="SELECT Locked FROM Noter_Entries WHERE ID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("i",$noteID);
	$stmt->execute();
	$stmt->bind_result($locked);
	$stmt->fetch();
	return $locked;
}

// Function to change note's lock state (lock or unlock)
function noteLockState($noteID, $userID, $lockState) {
	global $conn;
	prepareConnection();
	$query="UPDATE Noter_Entries SET Locked=? WHERE ID=? AND UserID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("iii",
			$lockState,
			$noteID,
			$userID);
	$stmt->execute();
	return ($conn->affected_rows>0);
}


// MAIN API FUNCTIONS SECTION
// Function for registering new user
function userRegister($username, $password) {
	$answer_info=null;
	$res=register($username,$password);
	if($res==-1) {
		$answer_info=answerInfo(ERROR_USER_EXISTS);
	}
	else if($res==1) {
		$answer_info=answerInfo(INFO_USER_CREATED);
	}
	else {
		$answer_info=answerInfo(ERROR_NO_CREDENTIALS);
	}
	return array($answer_info,null);
}

// Function for changing user's password
function userChangePassword($userID, $newPassword) {
	$answer_info=null;
	$res=userUpdate($userID,$newPassword);
	if($res==1) {
		$answer_info=answerInfo(INFO_USER_UPDATED);
	}
	else if($res==-1) {
		$answer_info=answerInfo(ERROR_USER_DEACTIVATED);
	}
	else {
		$answer_info=answerInfo(ERROR_LOGIN_INCORRECT);
	}
	return array($answer_info,null);
}

// Function for gathering user's info
function userInfo($userID) {
	global $conn;
	prepareConnection();
	$answer=null;
	$answer_info=null;
	$query="SELECT ID, UserName, DateRegistered, UserAgent, LastChanged, LastUserAgent FROM Noter_Users WHERE ID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("i",$userID);
	$stmt->execute();
	$stmt->bind_result($id,
			$username,
			$dateRegistered,
			$userAgent,
			$lastChanged,
			$lastUserAgent);
	$stmt->fetch();
	$answer=array("user" => array(	"id" => $id,
					"username" => $username,
					"date_registered" => exportDate($dateRegistered),
					"user_agent" => $userAgent,
					"last_changed" => exportDate($lastChanged),
					"last_user_agent" => $lastUserAgent));
	$answer_info=answerInfo(INFO_USER_INFO_RETRIEVED,array("user"));
	return array($answer_info,$answer);
}

// Function for removing user account
function userRemove($userID) {
	global $conn;
	prepareConnection();
	$answer_info=null;
	$query="DELETE FROM Noter_Entries WHERE UserID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("i",$userID);
	$stmt->execute();
	if($conn->affected_rows!=-1) {
		$query="DELETE FROM Noter_Users WHERE ID=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("i",$userID);
		$stmt->execute();
		if($conn->affected_rows>0) {
			$answer_info=answerInfo(INFO_USER_REMOVED);
		}
		else {
			$answer_info=answerInfo(ERROR_USER_NOT_EXISTS);
		}
	}
	else {
		$answer_info=answerInfo(ERROR_USER_REMOVAL_FAILURE);
	}
	return array($answer_info,null);
}

// Function for gathering note list
function noteList($userID) {
	global $conn;
	prepareConnection();
	$answer=null;
	$answer_info=null;
	$query="SELECT ID, Subject, LastModified FROM Noter_Entries WHERE UserID = ? ORDER BY LastModified DESC";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("i",$userID);
	$stmt->execute();
	$stmt->bind_result($id,
			$subject,
			$lastModified);
	$count=0;
	$notesSummary=array();
	while($stmt->fetch()) {
		$count++;
		array_push($notesSummary,array(	"id" => $id,
						"subject" => $subject,
						"last_modified" => exportDate($lastModified)));
	}
	$answer_info=answerInfo(INFO_LIST_SUCCESSFUL,array("count","notes_summary"));
	$answer=array(	"count" => $count,
			"notes_summary" => $notesSummary);
	return array($answer_info,$answer);
}

// Function for gathering note contents
function getNote($userID, $noteID) {
	global $conn;
	prepareConnection();
	$answer=null;
	$answer_info=null;
	$query="SELECT ID, Subject, Entry, DateAdded, LastModified, Locked, UserAgent, LastUserAgent FROM Noter_Entries WHERE ID=? AND UserID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("ii",$noteID,$userID);
	$stmt->execute();
	$stmt->bind_result($id,
			$subject,
			$entry,
			$dateAdded,
			$lastModified,
			$locked,
			$userAgent,
			$lastUserAgent);
	if($stmt->fetch()) {
		$answer_info=answerInfo(INFO_NOTE_RETRIEVED,array("note"));
		$answer=array("note" => array(	"id" => $id,
						"subject" => $subject,
						"entry" => $entry,
						"date_added" => exportDate($dateAdded),
						"last_modified" => exportDate($lastModified),
						"locked" => $locked,
						"user_agent" => $userAgent,
						"last_user_agent" => $lastUserAgent));
	}
	else {
		$answer_info=answerInfo(ERROR_NOTE_NOT_EXISTS);
	}
	return array($answer_info,$answer);
}

// Function for adding new note
function addNote($userID, $subject, $entry) {
	global $conn;
	prepareConnection();
	$answer=null;
	$answer_info=null;
	if(($subject!="") && ($entry!="")) {
		$now=nowDate();
		$query="INSERT INTO Noter_Entries(UserID, Subject, Entry, DateAdded, LastModified, RemoteAddress, ForwardedFor, UserAgent, LastRemoteAddress, LastForwardedFor, LastUserAgent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("issssssssss",
				$userID,
				$subject,
				$entry,
				$now,
				$now,
				$_SERVER["REMOTE_ADDR"],
				$_SERVER["HTTP_X_FORWARDED_FOR"],
				$_SERVER["HTTP_USER_AGENT"],
				$_SERVER["REMOTE_ADDR"],
				$_SERVER["HTTP_X_FORWARDED_FOR"],
				$_SERVER["HTTP_USER_AGENT"]);
		$stmt->execute();
		if($conn->affected_rows!=-1) {
			$answer_info=answerInfo(INFO_NOTE_CREATED,array("new_id"));
			$query="SELECT MAX(ID) FROM Noter_Entries WHERE UserID=?";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("i",$userID);
			$stmt->execute();
			$stmt->bind_result($newID);
			$stmt->fetch();
			$answer=array("new_id" => $newID);
		}
		else {
			$answer_info=answerInfo(ERROR_INTERNAL_SERVER_ERROR);
		}
	}
	else {
		$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
	}
	return array($answer_info,$answer);
}

// Function for updating note contents
function updateNote($userID, $subject, $entry, $noteID) {
	global $conn;
	prepareConnection();
	$answer=null;
	$answer_info=null;
	if(($subject!="") || ($entry!="")) {
		if(noteLocked($noteID)) {
			$answer_info=answerInfo(ERROR_NOTE_LOCKED);
		}
		else {
			$now=nowDate();
			if(($subject!="") && ($entry!="")) {
				$query="UPDATE Noter_Entries SET Subject=?, Entry=?, LastModified=?, LastRemoteAddress=?, LastForwardedFor=?, LastUserAgent=? WHERE ID=? AND UserID=?";
			}
			else {
				if($subject!="") {
					$query="UPDATE Noter_Entries SET Subject=?, LastModified=?, LastRemoteAddress=?, LastForwardedFor=?, LastUserAgent=? WHERE ID=? AND UserID=?";
				}
				else {
					$query="UPDATE Noter_Entries SET Entry=?, LastModified=?, LastRemoteAddress=?, LastForwardedFor=?, LastUserAgent=? WHERE ID=? AND UserID=?";
				}
			}
			$stmt=$conn->prepare($query);
			if(($subject!="") && ($entry!="")) {
				$stmt->bind_param("ssssssii",
						$subject,
						$entry,
						$now,
						$_SERVER["REMOTE_ADDR"],
						$_SERVER["HTTP_X_FORWARDED_FOR"],
						$_SERVER["HTTP_USER_AGENT"],
						$noteID,
						$userID);
			}
			else {
				if($subject!="") {
					$stmt->bind_param("sssssii",
							$subject,
							$now,
							$_SERVER["REMOTE_ADDR"],
							$_SERVER["HTTP_X_FORWARDED_FOR"],
							$_SERVER["HTTP_USER_AGENT"],
							$noteID,
							$userID);
				}
				else {
					$stmt->bind_param("sssssii",
							$entry,
							$now,
							$_SERVER["REMOTE_ADDR"],
							$_SERVER["HTTP_X_FORWARDED_FOR"],
							$_SERVER["HTTP_USER_AGENT"],
							$noteID,
							$userID);
				}
			}
			$stmt->execute();
			if($conn->affected_rows>0) {
				$answer_info=answerInfo(INFO_NOTE_UPDATED);
			}
			else {
				$answer_info=answerInfo(ERROR_NOTE_NOT_EXISTS);
			}
		}
	}
	else {
		$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
	}
	return array($answer_info,$answer);
}

// Function for updating note subject only (left for compatibility purposes only)
function updateNoteSubject($userID, $subject, $noteID) {
	return updateNote($userID,$subject,"",$noteID);
}

// Function for updating note entry only (left for compatibility purposes only)
function updateNoteEntry($userID, $entry, $noteID) {
	return updateNote($userID,"",$entry,$noteID);
}

// Function for deleting note
function deleteNote($userID, $noteID) {
	global $conn;
	prepareConnection();
	$answer=null;
	$answer_info=null;
	if(noteLocked($noteID)) {
		$answer_info=answerInfo(ERROR_NOTE_LOCKED);
	}
	else {
		$query="DELETE FROM Noter_Entries WHERE ID=? AND UserID=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("ii",$noteID,$userID);
		$stmt->execute();
		if($conn->affected_rows>0) {
			$answer_info=answerInfo(INFO_NOTE_DELETED);
		}
		else {
			$answer_info=answerInfo(ERROR_NOTE_NOT_EXISTS);
		}
	}
	return array($answer_info,$answer);
}

// Function for locking note
function lockNote($userID, $noteID) {
	$answer=null;
	$answer_info=null;
	if(noteLockState($noteID,$userID,true)) {
		$answer_info=answerInfo(INFO_NOTE_LOCKED);
	}
	else {
		if(noteLocked($noteID)) {
			$answer_info=answerInfo(ERROR_NOTE_ALREADY_LOCKED);
		}
		else {
			$answer_info=answerInfo(ERROR_NOTE_NOT_EXISTS);
		}
	}
	return array($answer_info,$answer);
}

// Function for unlocking note
function unlockNote($userID, $noteID) {
	$answer=null;
	$answer_info=null;
	if(noteLockState($noteID,$userID,false)) {
		$answer_info=answerInfo(INFO_NOTE_UNLOCKED);
	}
	else {
		if(noteLocked($noteID)) {
			$answer_info=answerInfo(ERROR_NOTE_NOT_EXISTS);
		}
		else {
			$answer_info=answerInfo(ERROR_NOTE_ALREADY_UNLOCKED);
		}
	}
	return array($answer_info,$answer);
}

?>
