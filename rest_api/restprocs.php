<?php

/*

Noter REST API Helper Procedures 1.1a
(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

*/

// Include section
include_once("restconfig.php");
include_once("restconst.php");
include_once("../noter-config.php");
include_once("../noterapi.php");
include_once("../noterconst.php");

// Output JSON
function jsonReturn($inputArray) {
	echo json_encode($inputArray);
	return;
}

// Return error JSON
function errorReturn($error, $errorCode) {
	jsonReturn(array("error" => $error, "error_code" => $errorCode));
	return;
}

// Set HTTP response code and return error JSON
function fullErrorReturn($httpErrorCode, $error, $errorCode) {
	http_response_code($httpErrorCode);
	errorReturn($error,$errorCode);
	return;
}

// Set allowed methods HTTP header
function allowedMethods($methods) {
	header("Access-Control-Allow-Methods: ".$methods);
	return;
}

// Set allowed methods HTTP header and proper HTTP response code
function allowedMethodsResponse($methods) {
	http_response_code(204);
	allowedMethods($methods);
	return;
}

// Read REST request info
function prepareRequest() {
	$uri=explode("/",$_SERVER["REQUEST_URI"]);
	$pos=array_search(API_DIRECTORY,$uri);
	return array_filter(array_slice($uri,$pos+1));
}

// Get REST request info and used method
function getRequestInfo() {
	return array($_SERVER["REQUEST_METHOD"],prepareRequest());
}

// Get credentials from headers
function getCredentials(&$username, &$password) {
	if(isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
		$username=trim($_SERVER["PHP_AUTH_USER"]);
		$password=trim($_SERVER["PHP_AUTH_PW"]);
		return true;
	}
	else {
		header('WWW-Authenticate: Basic realm="'.NOTER_NAME.'"');
		http_response_code(401);
		errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
		return false;
	}
}

// Check if input is JSON
function isJson($input) {
	json_decode($input);
	return json_last_error() === JSON_ERROR_NONE;
}

// Helper function to check and read input
function checkAndReadRequest($ignoreNothing = false) {
	if(isset($_SERVER['CONTENT_TYPE'])) {
		if($_SERVER['CONTENT_TYPE']=="application/json") {
			$input=file_get_contents("php://input");
			if(isJson($input)) {
				$temp=json_decode($input,true);
				if($temp==null) {
					noUsableInformation();
					return null;
				}
				return $temp;
			}
			else {
				badRequest();
				return null;
			}
		}
		else {
			wrongMedia();
			return null;
		}
	}
	else {
		if(!$ignoreNothing) {
			noUsableInformation();
		}
		return null;
	}
}

// Helper function to return proper answer (or error)
function fullReturn($answerInfo, $rawAnswer, $httpResponseCode = 200) {
	if(isset($answerInfo) && $answerInfo["code"]>=INFO_OK) {
		http_response_code($httpResponseCode);
		jsonReturn($rawAnswer);
	}
	else {
		// Make it some kind of a default error
		resourceNotFound();
	}
	return;
}

// Helper function to "login" or return error
function tryRESTLogin($username, $password, &$userID, &$answer_info) {
	if(tryLogin($username,$password,$userID,$answer_info)) {
		if(isset($answer_info)) {
			if($answer_info["code"]==ERROR_USER_DEACTIVATED) {
				userDeactivated();
				return false;
			}
			else if($answer_info["code"]==ERROR_LOGIN_INCORRECT) {
				loginIncorrect();
				return false;
			}
			else if($answer_info["code"]==ERROR_INTERNAL_SERVER_ERROR) {
				internalServerError();
				return false;
			}
			else if($answer_info["code"]==ERROR_SERVICE_DISABLED) {
				serviceDisabled();
				return false;
			}
		}
		return true;
	}
	else {
		loginIncorrect();
		return false;
	}
}

// Prepare and return server information
function noterServerInfo() {
	http_response_code(200);
	jsonReturn(array("name" => NOTER_NAME, "timezone" => NOTER_TIMEZONE, "version" => "1.0"));
	return;
}

// Helper function to return error: unsupported method here
function unsupportedMethod() {
	fullErrorReturn(405,STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
	return;
}

// Helper function to return error: note is locked
function infoNoteLocked() {
	fullErrorReturn(403,STRING_ERROR_NOTE_LOCKED,ERROR_NOTE_LOCKED);
	return;
}

// Helper function to return error: resource not found
function resourceNotFound() {
	fullErrorReturn(404,STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
	return;
}

// Helper function to return error: login incorrect
function loginIncorrect() {
	fullErrorReturn(401,STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
	return;
}

// Helper function to return error: user deactivated
function userDeactivated() {
	fullErrorReturn(403,STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
	return;
}

// Helper function to return error: internal server error
function internalServerError() {
	fullErrorReturn(500,STRING_ERROR_INTERNAL,ERROR_INTERNAL_SERVER_ERROR);
	return;
}

// Helper function to return error: service disabled
function serviceDisabled() {
	fullErrorReturn(503,STRING_ERROR_SERVICE_DISABLED,ERROR_SERVICE_DISABLED);
	return;
}

// Helper function to return error: bad request
function badRequest() {
	fullErrorReturn(400,STRING_ERROR_BAD_REQUEST,ERROR_BAD_REQUEST);
	return;
}

// Helper function to return error: wrong media
function wrongMedia() {
	fullErrorReturn(415,STRING_ERROR_WRONG_MEDIA,ERROR_WRONG_MEDIA);
	return;
}

// Helper function to return error: no usable information
function noUsableInformation() {
	fullErrorReturn(422,STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
	return;
}

// Helper function to return error: user already exists (username chosen)
function infoUserExists() {
	fullErrorReturn(403,STRING_ERROR_USER_EXISTS,ERROR_USER_EXISTS);
	return;
}

?>
