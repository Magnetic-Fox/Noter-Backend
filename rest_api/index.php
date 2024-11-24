<?php

/*

Noter REST API Main Code 1.1a
(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

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

// Include section
include_once("restconfig.php");
include_once("restconst.php");
include_once("restprocs.php");
include_once("../noter-config.php");
include_once("../noterapi.php");
include_once("../noterconst.php");

// Pack everything to the try..catch block
try {
	// Turn off printing warnings and most errors (locally) as these may corrupt JSON output
	error_reporting(E_ERROR | E_PARSE);

	// Get request info
	list($method,$request)=getRequestInfo();

	// Return additional headers only if method is not OPTIONS
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
					$data=checkAndReadRequest();
					if($data!=null && isset($data["username"]) && isset($data["password"]) && count($data)==2) {
						$username=trim($data["username"]);
						$password=trim($data["password"]);
						list($answerInfo,$answer)=userRegister($username,$password);
						if($answerInfo["code"]==ERROR_USER_EXISTS) {
							infoUserExists();
						}
						else if($answerInfo["code"]==ERROR_NO_CREDENTIALS) {
							noUsableInformation();
						}
						else {
							if(tryRESTLogin($username,$password,$userID,$answerInfo2)) {
								list($answerInfo2,$answer2)=userInfo($userID);
								fullReturn($answerInfo2,$answer2==null?null:$answer2["user"],201);
							}
						}
					}
					else if($data!=null) {
						noUsableInformation();
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
							$data=checkAndReadRequest();
							if($data!=null && isset($data["password"]) && count($data)==1) {
								$password=$data["password"];
								if(tryRESTLogin($username,$password,$returnedUserID,$answerInfo)) {
									if($returnedUserID==$userID) {
										list($answerInfo,$answer)=userRemove($userID);
										if($answerInfo["code"]==ERROR_USER_NOT_EXISTS) {
											resourceNotFound();
										}
										else if($answerInfo["code"]==ERROR_USER_REMOVAL_FAILURE) {
											internalServerError();
										}
										else {
											http_response_code(204);
										}
									}
									else {
										resourceNotFound();
									}
								}
							}
							else if($data!=null) {
								noUsableInformation();
							}
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
								$data=checkAndReadRequest();
								if($data!=null && isset($data["old_password"]) && isset($data["new_password"]) && count($data)==2) {
									$password=$data["old_password"];
									$newPassword=$data["new_password"];
									if(tryRESTLogin($username,$password,$returnedUserID,$answerInfo)) {
										if($returnedUserID==$userID) {
											list($answerInfo,$answer)=userChangePassword($userID,$newPassword);
											if($answerInfo["code"]>=INFO_OK) {
												http_response_code(204);
											}
										}
										else {
											resourceNotFound();
										}
									}
								}
								else if($data!=null) {
									noUsableInformation();
								}
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
						else if($data!=null) {
							noUsableInformation();
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
									else if($answerInfo["code"]==ERROR_NOTE_LOCKED) {
										infoNoteLocked();
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
							else if($data!=null) {
								noUsableInformation();
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
										else if($answerInfo["code"]==ERROR_NOTE_LOCKED) {
											infoNoteLocked();
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
}
// If anything wrong happened, then return Internal Server Error (as backend failed)
catch(Throwable $e) {
	internalServerError();
}

?>
