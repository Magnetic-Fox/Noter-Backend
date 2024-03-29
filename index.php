<?php

/*

Noter Backend v1.0c
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

include_once('noter-config.php');
include_once('noterapi.php');
include_once('noterconst.php');

// Main part of the backend

if(array_key_exists("compress",$_GET)) {
	$compress=($_GET["compress"]=="yes");
} else if(array_key_exists("compress",$_POST)) {
	$compress=($_POST["compress"]=="yes");
} else {
	$compress=false;
}

function addHeaders($dataCompressed = false) {
	header("Content-Type: application/json");
	if($dataCompressed) {
		header("X-BZ-Compressed: yes");
	}
}

$server_info=array("name" => $server_name, "timezone" => $server_timezone, "version" => "1.0");
$response=array();

if(!$noter_enabled) {
	$compressData=false;
	$answer_info=answerInfo(ERROR_SERVICE_DISABLED);
	$response=array("server" => $server_info, "answer_info" => $answer_info);
	if($compress) {
		$response2=bzcompress($response,9);
		$compressData=strlen($response2)<strlen($response);
		if($compressData) {
			$response=$response2;
		}
	}
	addHeaders($compressData);
	die(json_encode($response));
}

if($conn->connect_error) {
	$compressData=false;
	$answer_info=answerInfo(ERROR_INTERNAL_SERVER_ERROR);
	$response=array("server" => $server_info, "answer_info" => $answer_info);
	if($compress) {
		$response2=bzcompress($response,9);
		$compressData=strlen($response2)<strlen($response);
		if($compressData) {
			$response=$response2;
		}
	}
	addHeaders($compressData);
	die(json_encode($response));
}

if($_SERVER["REQUEST_METHOD"]=="GET") {
	$answer_info=answerInfo(INFO_OK);
}
else if($_SERVER["REQUEST_METHOD"]=="POST") {
	if(array_key_exists("action",$_POST) && (array_key_exists("username",$_POST)) && array_key_exists("password",$_POST)) {
		$action=trim($_POST["action"]);
		$username=trim($_POST["username"]);
		$password=trim($_POST["password"]);
		if(($username=="") || ($password=="")) {
			$answer_info=answerInfo(ERROR_NO_CREDENTIALS);
		}
		else if($action=="register") {
			list($answer_info,$answer)=userRegister($username,$password);
		}
		else if($action=="change") {
			if(array_key_exists("newPassword",$_POST)) {
				list($answer_info,$answer)=userChangePassword($username,$password,trim($_POST["newPassword"]));
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else if($action=="info") {
			list($answer_info,$answer)=userInfo($username,$password);
		}
		else if($action=="remove") {
			list($answer_info,$answer)=userRemove($username,$password);
		}
		else if($action=="list") {
			list($answer_info,$answer)=noteList($username,$password);
		}
		else if($action=="retrieve") {
			if(array_key_exists("noteID",$_POST)) {
				list($answer_info,$answer)=getNote($username,$password,$_POST["noteID"]);
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else if($action=="add") {
			if(array_key_exists("subject",$_POST) && array_key_exists("entry",$_POST)) {
				list($answer_info,$answer)=addNote($username,$password,trim($_POST["subject"]),trim($_POST["entry"]));
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else if($action=="update") {
			if((array_key_exists("noteID",$_POST)) && (array_key_exists("subject",$_POST)) && (array_key_exists("entry",$_POST))) {
				list($answer_info,$answer)=updateNote($username,$password,trim($_POST["subject"]),trim($_POST["entry"]),$_POST["noteID"]);
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else if($action=="delete") {
			if(array_key_exists("noteID",$_POST)) {
				list($answer_info,$answer)=deleteNote($username,$password,$_POST["noteID"]);
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else if($action=="lock") {
			if(array_key_exists("noteID",$_POST)) {
				list($answer_info,$answer)=lockNote($username,$password,$_POST["noteID"]);
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else if($action=="unlock") {
			if(array_key_exists("noteID",$_POST)) {
				list($answer_info,$answer)=unlockNote($username,$password,$_POST["noteID"]);
			}
			else {
				$answer_info=answerInfo(ERROR_NO_NECESSARY_INFORMATION);
			}
		}
		else {
			$answer_info=answerInfo(ERROR_UNKNOWN_ACTION);
		}
	}
	else {
		$answer_info=answerInfo(ERROR_NO_USABLE_INFORMATION);
	}
}
else {
	$answer_info=answerInfo(ERROR_INVALID_METHOD);
}

$response=array("server" => $server_info, "answer_info" => $answer_info);
if(isset($answer)) {
	$response["answer"]=$answer;
}

$outputData=json_encode($response);

// compression filter
$compressData = false;
if($compress) {
	$outputData2=bzcompress($outputData,9);
	$compressData=strlen($outputData2)<strlen($outputData);
	if($compressData) {
		$outputData=$outputData2;
	}
}
addHeaders($compressData);

echo $outputData;

?>