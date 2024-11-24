<?php

/*

Noter REST API 1.1a (not-finished yet)
(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

TODO: Finish user part of backend and move functions to its own include files

 Endpoints:
------------
GET    /                        - get server info

GET    /users                   - get current user
POST   /users                   - create user
GET    /users/<id>              - get user by ID (if allowed)
DELETE /users/<id>              - delete user
PUT    /users/<id>/password     - change user's password

GET    /notes                   - get note list
POST   /notes                   - create note
GET    /notes/<id>              - get note
PUT    /notes/<id>              - update whole note
PATCH  /notes/<id>              - update note partially (subject or entry or both)
DELETE /notes/<id>              - delete note
GET    /notes/<id>/locked       - get note lock state
PUT    /notes/<id>/locked       - change note lock state

*/

include_once("../noter-config.php");
include_once("../noterapi.php");
include_once("../noterconst.php");
include_once("restconst.php");

function jsonReturn($inputArray) {
	echo json_encode($inputArray);
	return;
}

function errorReturn($error, $errorCode) {
	jsonReturn(array("error" => $error, "error_code" => $errorCode));
	return;
}

function fullErrorReturn($httpErrorCode, $error, $errorCode) {
	http_response_code($httpErrorCode);
	errorReturn($error,$errorCode);
	return;
}

function allowedMethods($methods) {
	header("Access-Control-Allow-Methods: ".$methods);
	return;
}

function allowedMethodsResponse($methods) {
	http_response_code(204);
	allowedMethods($methods);
	return;
}

function prepareRequest() {
	$uri=explode("/",$_SERVER["REQUEST_URI"]);
	$pos=array_search(API_DIRECTORY,$uri);
	return array_filter(array_slice($uri,$pos+1));
}

function getRequestInfo() {
	return array($_SERVER["REQUEST_METHOD"],prepareRequest());
}

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

function noterServerInfo() {
	http_response_code(200);
	jsonReturn(array("name" => NOTER_NAME, "timezone" => NOTER_TIMEZONE, "version" => "1.0"));
	return;
}

function unsupportedMethod() {
	fullErrorReturn(405,STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
	return;
}

function infoNoteLocked() {
	fullErrorReturn(405,STRING_ERROR_NOTE_LOCKED,ERROR_NOTE_LOCKED);
	return;
}

function resourceNotFound() {
	fullErrorReturn(404,STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
	return;
}

function loginIncorrect() {
	fullErrorReturn(401,STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
	return;
}

function userDeactivated() {
	fullErrorReturn(403,STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
	return;
}

function internalServerError() {
	fullErrorReturn(500,STRING_ERROR_INTERNAL,ERROR_INTERNAL_SERVER_ERROR);
	return;
}

function serviceDisabled() {
	fullErrorReturn(503,STRING_ERROR_SERVICE_DISABLED,ERROR_SERVICE_DISABLED);
	return;
}

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

function isJson($input) {
	json_decode($input);
	return json_last_error() === JSON_ERROR_NONE;
}

function badRequest() {
	fullErrorReturn(400,STRING_ERROR_BAD_REQUEST,ERROR_BAD_REQUEST);
	return;
}

function wrongMedia() {
	fullErrorReturn(415,STRING_ERROR_WRONG_MEDIA,ERROR_WRONG_MEDIA);
	return;
}

function noUsableInformation() {
	fullErrorReturn(422,STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
	return;
}

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


// -----------------------------------------------------------------------------

// MAIN PART OF THE REST BACKEND
// Turn off printing warnings and most errors (locally) as these may corrupt JSON output
// error_reporting(E_ERROR | E_PARSE);

list($method,$request)=getRequestInfo();

if($method!="OPTIONS") {
	header("Content-Type: application/json");
	header("Access-Control-Allow-Credentials: true");
}

// OBJECT specified
if(isset($request[0])) {
	// USERS object specified
	if($request[0]=="users") {
		// NO user ID nor sub-object specified
		if(count($request)==1) {
			// Return current user information
			if($method=="GET") {
				if(getCredentials($username,$password)) {
					if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
						list($answerInfo,$answer)=userInfo($userID);
						fullReturn($answerInfo,$answer==null?null:$answer["user"]);
					}
				}
			}
			// Create new user
			else if($method=="POST") {
				if(getCredentials($username,$password)) {
					// TODO: Create new user
				}
			}
			// Return allowed methods
			else if($method=="OPTIONS") {
				allowedMethodsResponse("GET,POST");
			}
			// Unsupported method here
			else {
				unsupportedMethod();
			}
		}
		// USER ID specified
		else if(count($request)==2) {
			$userID=$request[1];
			// Is user ID correct?
			if(is_numeric($userID)) {
				// Return user information (chosen by ID)
				if($method=="GET") {
					if(getCredentials($username,$password)) {
						if(tryRESTLogin($username,$password,$returnedUserID,$answerInfo)) {
							// At this time, only the user's ID, whose credentials were used, may be used
							if($returnedUserID==$userID) {
								list($answerInfo,$answer)=userInfo($userID);
								fullReturn($answerInfo,$answer==null?null:$answer["user"]);
							}
							// Otherwise, return error (404)
							else {
								resourceNotFound();
							}
						}
					}
				}
				// Delete user
				else if($method=="DELETE") {
					if(getCredentials($username,$password)) {
						// TODO: Delete user
					}
				}
				// Return allowed methods
				else if($method=="OPTIONS") {
					allowedMethodsResponse("GET,DELETE");
				}
				// Unsupported method here
				else {
					unsupportedMethod();
				}
			}
			// User ID is wrong
			else {
				resourceNotFound();
			}
		}
		// USER ID and SUB-OBJECT specified
		else if(count($request)==3) {
			$userID=$request[1];
			// Is user ID correct?
			if(is_numeric($userID)) {
				// PASSWORD sub-object specified
				if($request[2]=="password") {
					// Change chosen user's password
					if($method=="PUT") {
						if(getCredentials($username,$password)) {
							// TODO: Change user password
						}
					}
					// Return allowed methods
					else if($method=="OPTIONS") {
						allowedMethodsResponse("PUT");
					}
					// Unsupported method here
					else {
						unsupportedMethod();
					}
				}
				// UNKNOWN sub-object specified
				else {
					resourceNotFound();
				}
			}
			// User ID is wrong
			else {
				resourceNotFound();
			}
		}
		// UNKNOWN PARAMETERS specified
		else {
			resourceNotFound();
		}
	}
	// NOTES object specified
	else if($request[0]=="notes") {
		// NO note ID nor sub-object specified
		if(count($request)==1) {
			// Return note list
			if($method=="GET") {
				if(getCredentials($username,$password)) {
					if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
						list($answerInfo,$answer)=noteList($userID);
						fullReturn($answerInfo,$answer==null?null:$answer["notes_summary"]);
					}
				}
			}
			// Create new note
			else if($method=="POST") {
				if(getCredentials($username,$password)) {
					$data=checkAndReadRequest();
					if($data!=null && isset($data["subject"]) && isset($data["entry"]) && count($data)==2) {
						$subject=trim($data["subject"]);
						$entry=trim($data["entry"]);
						if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
							list($answerInfo,$answer)=addNote($userID,$subject,$entry);
							if($answerInfo["code"]==ERROR_INTERNAL_SERVER_ERROR) {
								internalServerError();
							}
							else if($answerInfo["code"]==ERROR_NO_NECESSARY_INFORMATION) {
								noUsableInformation();
							}
							else {
								$newID=$answer["new_id"];
								list($answerInfo2,$answer2)=getNote($userID,$newID);
								if($answerInfo2["code"]>=INFO_OK) {
									fullReturn($answerInfo2,$answer2==null?null:$answer2["note"],201);
								}
								else {
									internalServerError();
								}
							}
						}
					}
				}
			}
			// Return allowed methods
			else if($method=="OPTIONS") {
				allowedMethodsResponse("GET,POST");
			}
			// Unsupported method here
			else {
				unsupportedMethod();
			}
		}
		// NOTE ID specified
		else if(count($request)==2) {
			$noteID=$request[1];
			// Is note ID correct?
			if(is_numeric($noteID)) {
				// Return note
				if($method=="GET") {
					if(getCredentials($username,$password)) {
						if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
							list($answerInfo,$answer)=getNote($userID,$noteID);
							fullReturn($answerInfo,$answer==null?null:$answer["note"]);
						}
					}
				}
				// Update whole note
				else if($method=="PUT") {
					if(getCredentials($username,$password)) {
						$data=checkAndReadRequest();
						if($data!=null && isset($data["subject"]) && isset($data["entry"]) && count($data)==2) {
							$subject=trim($data["subject"]);
							$entry=trim($data["entry"]);
							if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
								list($answerInfo,$answer)=updateNote($userID,$subject,$entry,$noteID);
								if($answerInfo["code"]==ERROR_INTERNAL_SERVER_ERROR) {
									internalServerError();
								}
								else if($answerInfo["code"]==ERROR_NO_NECESSARY_INFORMATION) {
									noUsableInformation();
								}
								else {
									list($answerInfo2,$answer2)=getNote($userID,$noteID);
									if($answerInfo2["code"]>=INFO_OK) {
										fullReturn($answerInfo2,$answer2==null?null:$answer2["note"]);
									}
									else {
										internalServerError();
									}
								}
							}
						}
					}
				}
				// Update part of a note (subject and/or entry)
				else if($method=="PATCH") {
					if(getCredentials($username,$password)) {
						$data=checkAndReadRequest();
						if($data!=null && (isset($data["subject"]) || isset($data["entry"])) && count($data)>0 && count($data)<=2) {
							$entryCount=0;
							if(isset($data["subject"])) {
								$subject=trim($data["subject"]);
								$entryCount=$entryCount+1;
							}
							else {
								$subject="";
							}
							if(isset($data["entry"])) {
								$entry=trim($data["entry"]);
								$entryCount=$entryCount+1;
							}
							else {
								$entry="";
							}
							if($entryCount==count($data)) {
								if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
									list($answerInfo,$answer)=updateNote($userID,$subject,$entry,$noteID);
									if($answerInfo["code"]==ERROR_INTERNAL_SERVER_ERROR) {
										internalServerError();
									}
									else if($answerInfo["code"]==ERROR_NO_NECESSARY_INFORMATION) {
										noUsableInformation();
									}
									else {
										list($answerInfo2,$answer2)=getNote($userID,$noteID);
										if($answerInfo2["code"]>=INFO_OK) {
											fullReturn($answerInfo2,$answer2==null?null:$answer2["note"]);
										}
										else {
											internalServerError();
										}
									}
								}
							}
							else {
								noUsableInformation();
							}
						}
						else if($data!=null) {
							noUsableInformation();
						}
					}
				}
				// Delete note
				else if($method=="DELETE") {
					if(getCredentials($username,$password)) {
						if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
							list($answerInfo,$answer)=deleteNote($userID,$noteID);
							if($answerInfo["code"]==ERROR_NOTE_LOCKED) {
								infoNoteLocked();
							}
							else if($answerInfo["code"]==ERROR_NOTE_NOT_EXISTS) {
								resourceNotFound();
							}
							else {
								http_response_code(204);
							}
						}
					}
				}
				// Return allowed methods
				else if($method=="OPTIONS") {
					allowedMethodsResponse("GET,PUT,PATCH,DELETE");
				}
				// Unsupported method here
				else {
					unsupportedMethod();
				}
			}
			// Note ID is wrong
			else {
				resourceNotFound();
			}
		}
		// NOTE ID and SUB-OBJECT specified
		else if(count($request)==3) {
			$noteID=$request[1];
			// Is note ID correct?
			if(is_numeric($noteID)) {
				// LOCKED sub-object specified
				if($request[2]=="locked") {
					// Return note's lock state
					if($method=="GET") {
						if(getCredentials($username,$password)) {
							if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
								list($answerInfo,$answer)=getNote($userID,$noteID);
								fullReturn($answerInfo,$answer==null?null:array("locked" => $answer["note"]["locked"]));
							}
						}
					}
					// Change note's lock state
					else if($method=="PUT") {
						if(getCredentials($username,$password)) {
							$data=checkAndReadRequest();
							if($data!=null && isset($data["locked"]) && count($data)==1) {
								$locked=$data["locked"];
								if(is_numeric($locked)) {
									if(tryRESTLogin($username,$password,$userID,$answerInfo)) {
										if($locked==0) {
											list($answerInfo,$answer)=unlockNote($userID,$noteID);
										}
										else {
											list($answerInfo,$answer)=lockNote($userID,$noteID);
										}
										if($answerInfo["code"]==ERROR_NOTE_ALREADY_LOCKED || $answerInfo["code"]==ERROR_NOTE_ALREADY_UNLOCKED) {
											// Set answer code to OK to override original API's thrown error on locking/unlocking locked/unlocked note
											$answerInfo["code"]=INFO_OK;
										}
										fullReturn($answerInfo,array("locked" => $locked));
									}
								}
								else {
									noUsableInformation();
								}
							}
							else if($data!=null) {
								noUsableInformation();
							}
						}
					}
					// Return allowed methods
					else if($method=="OPTIONS") {
						allowedMethodsResponse("GET,PUT");
					}
					// Unsupported method here
					else {
						unsupportedMethod();
					}
				}
				// UNKNOWN sub-object specified
				else {
					resourceNotFound();
				}
			}
			// Note ID is wrong
			else {
				resourceNotFound();
			}
		}
		// UNKNOWN PARAMETERS specified
		else {
			resourceNotFound();
		}
	}
	// UNKNOWN OBJECT specified
	else {
		resourceNotFound();
	}
}
// NO OBJECT specified
else {
	// Return server information
	if($method=="GET") {
		noterServerInfo();
	}
	// Return allowed methods
	else if($method=="OPTIONS") {
		allowedMethodsResponse("GET");
	}
	// Unsupported method here
	else {
		unsupportedMethod();
	}
}

?>
