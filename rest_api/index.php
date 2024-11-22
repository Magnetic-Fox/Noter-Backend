<?php

/*

Noter REST API 1.0a (simply-patched version to work with 1.0d version of Noter Backend API)
(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!


 Some note
------------
Let's be sure - this REST API is very ugly, written in a big hurry and simply
needs to be rewritten from scratch. This is the patched and working (as far as I know)
version which should not crash at all. I'll make the new and better version someday.


 Endpoints:
------------
GET    /			- get server info
GET    /users			- get current user
GET    /users/<id>		- get user by ID (if allowed)
POST   /users			- create user
PUT    /users/<id>/password	- change user's password
DELETE /users/<id>		- delete user

GET    /notes			- get note list
GET    /notes/<id>		- get note
POST   /notes			- create note
PUT    /notes/<id>		- update whole note
PUT    /notes/<id>/locked	- change lock state
PATCH  /notes/<id>		- update note partially (subject or entry or both)
DELETE /notes/<id>		- delete note

*/


// INCLUDES SECTION
include_once('../noter-config.php');
include_once('../noterapi.php');
include_once('../noterconst.php');
include_once('restconst.php');
include_once('restprocs.php');


// MAIN API CODE SECTION
// Set headers
header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");

// Get request info at the beginning
$method=$_SERVER['REQUEST_METHOD'];
$request=prepareRequest();

// Prepare some global variables
$userID=0;
$response=null;
$answer_info=null;

// Set empty request if nothing arrived
if(!isset($request[0]))
{
	$request[0]="";
}

// Main part (big and ugly machinery goes here...)
switch($request[0])
{
	// Root of the API
	case "":
	{
		switch($method)
		{
			// Server information
			case "GET":
			{
				http_response_code(200);
				jsonReturn(array("name" => NOTER_NAME, "timezone" => NOTER_TIMEZONE, "version" => "1.0"));
				break;
			}
			// Options here
			case "OPTIONS":
			{
				http_response_code(204);
				allowedMethods("GET");
				break;
			}
			// Invalid method
			default:
			{
				http_response_code(405);
				errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
				break;
			}
		}
		break;
	}
	// User operations/objects
	case "users":
	{
		// ID provided
		if(isset($request[1]))
		{
			// is it ID already?
			if(is_numeric($request[1]))
			{
				// requesting some part of object
				if(isset($request[2]))
				{
					// the only part of object allowed here
					if($request[2]=="password")
					{
						switch($method)
						{
							// Change user password
							case "PUT":
							{
								// Authorize user
								$result=authorize();
								// Is user authorized?
								if($result>0)
								{
									// Is password change request for authorized user?
									if($result==$request[1])
									{
										$data=checkAndReadRequest();
										if(isset($data))
										{
											if(isset($data["old_password"]) && isset($data["new_password"]) && count($data)==2)
											{
												// Get necessary data;
												$username=trim($_SERVER['PHP_AUTH_USER']);
												$password=trim($data["old_password"]);
												$newPassword=trim($data["new_password"]);
												// Try to change password
												if(tryLogin($username,$password,$userID,$response)) {
													$response=userChangePassword($userID,$newPassword)[0]["code"];
												}
												// If user is deactivated
												if($response==ERROR_USER_DEACTIVATED)
												{
													http_response_code(403);
													errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
												}
												// Or maybe old password was incorrect?
												else if($response==ERROR_LOGIN_INCORRECT)
												{
													http_response_code(401);
													errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
												}
												// If user password changed
												else
												{
													http_response_code(204);
												}
											}
											else
											{
												http_response_code(422);
												errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
											}
										}
									}
									// Probably not
									else
									{
										http_response_code(404);
										errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
									}
								}
								// Probably not
								else
								{
									http_response_code(401);
									errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
								}
								break;
							}
							// Options here
							case "OPTIONS":
							{
								http_response_code(204);
								allowedMethods("PUT");
								break;
							}
							// Invalid method
							default:
							{
								http_response_code(405);
								errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
								break;
							}
						}
					}
					else
					{
						http_response_code(404);
						errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
					}
				}
				// requesting all
				else
				{
					switch($method)
					{
						// Get user info
						case "GET":
						{
							// Authorize user
							$result=authorize();
							// Is user authorized?
							if($result>0)
							{
								// Is info request for authorized user?
								if($result==$request[1])
								{
									// Get credentials
									list($username,$password)=getCredentials();
									// Get user information
									if(tryLogin($username,$password,$userID,$answer_info)) {
										list($answer_info,$answer)=userInfo($userID);
									}
									// If possible
									if($answer_info["code"]==INFO_USER_INFO_RETRIEVED)
									{
										http_response_code(200);
										jsonReturn($answer["user"]);
									}
									// But maybe user account is disabled?
									else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
									{
										http_response_code(403);
										errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
									}
									// Or credentials incorrect
									else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
									{
										http_response_code(401);
										errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
									}
								}
								// Probably not
								else
								{
									http_response_code(404);
									errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
								}
							}
							// Probably not
							else
							{
								http_response_code(401);
								errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
							}
							break;
						}
						// Remove user
						case "DELETE":
						{
							// Authorize user
							$result=authorize();
							// Is user authorized?
							if($result>0)
							{
								// Is user removal request for authorized user?
								if($result==$request[1])
								{
									$data=checkAndReadRequest();
									if(isset($data))
									{
										if(isset($data["password"]) && count($data)==1)
										{
											//echo "deleteuser";
											$username=trim($_SERVER['PHP_AUTH_USER']);
											$password=trim($data["password"]);
											if(tryLogin($username,$password,$userID,$response)) {
												$response=userRemove($userID)[0]["code"];
											}
											if($response==ERROR_USER_NOT_EXISTS)
											{
												http_response_code(404);
												errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
											}
											else if($response==ERROR_USER_REMOVAL_FAILURE)
											{
												http_response_code(500);
												errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
											}
											else if($response==ERROR_USER_DEACTIVATED)
											{
												http_response_code(403);
												errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
											}
											else if($response==ERROR_LOGIN_INCORRECT)
											{
												http_response_code(401);
												errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
											}
											else
											{
												http_response_code(204);
											}
										}
										else
										{
											http_response_code(422);
											errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
										}
									}
								}
								// Probably not
								else
								{
									http_response_code(404);
									errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
								}
							}
							// Probably not
							else
							{
								http_response_code(401);
								errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
							}
							break;
						}
						// Options here
						case "OPTIONS":
						{
							http_response_code(204);
							allowedMethods("GET,DELETE");
							break;
						}
						// Invalid method
						default:
						{
							http_response_code(405);
							errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
							break;
						}
					}
				}
			}
			// it is not ID
			else
			{
				http_response_code(404);
				errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
			}
		}
		// ID not provided
		else
		{
			switch($method)
			{
				// Get current user info
				case "GET":
				{
					// Get credentials
					list($username,$password)=getCredentials();
					// Get user information
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=userInfo($userID);
					}
					// If possible
					if($answer_info["code"]==INFO_USER_INFO_RETRIEVED)
					{
						http_response_code(200);
						jsonReturn($answer["user"]);
					}
					// But maybe user account is disabled?
					else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
					{
						http_response_code(403);
						errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
					}
					// Or credentials incorrect
					else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
					{
						http_response_code(401);
						errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
					}
					break;
				}
				// Register user
				case "POST":
				{
					$data=checkAndReadRequest();
					if(isset($data))
					{
						if(isset($data["username"]) && isset($data["password"]) && count($data)==2)
						{
							// Get username and password (traditionally)
							$username=trim($data["username"]);
							$password=trim($data["password"]);
							// Try to register
							$response=userRegister($username,$password)[0]["code"];
							// If user exists
							if($response==ERROR_USER_EXISTS)
							{
								http_response_code(403);
								errorReturn(STRING_ERROR_USER_EXISTS,ERROR_USER_EXISTS);
							}
							// If there aren't any credentials
							else if($response==ERROR_NO_CREDENTIALS)
							{
								http_response_code(422);
								errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
							}
							// If user successfully registered
							else
							{
								// Get user information to send it back
								if(tryLogin($username,$password,$userID,$answer_info)) {
									list($answer_info,$answer)=userInfo($userID);
								}
								// This should be possible
								if($answer_info["code"]==INFO_USER_INFO_RETRIEVED)
								{
									http_response_code(201);
									jsonReturn($answer["user"]);
								}
								// But may fail
								else
								{
									http_response_code(500);
									errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
								}
							}
						}
						else
						{
							http_response_code(422);
							errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
						}
					}
					break;
				}
				// Options here
				case "OPTIONS":
				{
					http_response_code(204);
					allowedMethods("GET,POST");
					break;
				}
				// Invalid method
				default:
				{
					http_response_code(405);
					errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
					break;
				}
			}
		}
		break;
	}
	// Note operations/objects
	case "notes":
	{
		if(isset($request[1]))
		{
			if(is_numeric($request[1]))
			{
				if(isset($request[2]))
				{
					if($request[2]=="locked")
					{
						switch($method)
						{
							// Get lock state
							case "GET":
							{
								// Get credentials
								list($username,$password)=getCredentials();
								// Get note
								if(tryLogin($username,$password,$userID,$answer_info)) {
									list($answer_info,$answer)=getNote($userID,$request[1]);
								}
								// Note does not exists?
								if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
								{
									http_response_code(404);
									errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
								}
								// User account disabled?
								else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
								{
									http_response_code(403);
									errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
								}
								// Or credentials incorrect?
								else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
								{
									http_response_code(401);
									errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
								}
								// Or maybe note retrieved successfully?
								else
								{
									http_response_code(200);
									jsonReturn(array("locked" => $answer["note"]["locked"]));
								}
								break;
							}
							// Change lock state
							case "PUT":
							{
								// Get credentials
								list($username,$password)=getCredentials();
								// Read request
								$data=checkAndReadRequest();
								if(isset($data))
								{
									if(isset($data["locked"]) && count($data)==1)
									{
										$locked=$data["locked"];
										// Is data correct?
										if(is_numeric($locked))
										{
											// Unlock note
											if($locked==0)
											{
												// Try to unlock
												if(tryLogin($username,$password,$userID,$answer_info)) {
													list($answer_info,$answer)=unlockNote($userID,$request[1]);
												}
												// Note does not exists?
												if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
												{
													http_response_code(404);
													errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
												}
												// User account disabled?
												else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
												{
													http_response_code(403);
													errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
												}
												// Login incorrect?
												else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
												{
													http_response_code(401);
													errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
												}
												// Or note unlocked
												else
												{
													http_response_code(200);
													jsonReturn(array("locked" => 0));
												}
											}
											// Lock note
											else if($locked==1)
											{
												// Try to lock
												if(tryLogin($username,$password,$userID,$answer_info)) {
													list($answer_info,$answer)=lockNote($userID,$request[1]);
												}
												// Note does not exists?
												if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
												{
													http_response_code(404);
													errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
												}
												// User account disabled?
												else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
												{
													http_response_code(403);
													errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
												}
												// Login incorrect?
												else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
												{
													http_response_code(401);
													errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
												}
												// Or note locked
												else
												{
													http_response_code(200);
													jsonReturn(array("locked" => 1));
												}
											}
											// Wrong state
											else
											{
												http_response_code(422);
												errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
											}
										}
										// Or maybe not?
										else
										{
											http_response_code(422);
											errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
										}
									}
									else
									{
										http_response_code(422);
										errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
									}
								}
								break;
							}
							// Options here
							case "OPTIONS":
							{
								http_response_code(204);
								allowedMethods("GET,PUT");
								break;
							}
							// Invalid method
							default:
							{
								http_response_code(405);
								errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
								break;
							}
						}
					}
				}
				else
				{
					switch($method)
					{
						case "GET":
						{
							// Get credentials
							list($username,$password)=getCredentials();
							// Get note
							if(tryLogin($username,$password,$userID,$answer_info)) {
								list($answer_info,$answer)=getNote($userID,$request[1]);
							}
							// Note does not exists?
							if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
							{
								http_response_code(404);
								errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
							}
							// User account disabled?
							else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
							{
								http_response_code(403);
								errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
							}
							// Or credentials incorrect?
							else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
							{
								http_response_code(401);
								errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
							}
							// Or maybe note arrived successfully?
							else
							{
								http_response_code(200);
								jsonReturn($answer["note"]);
							}
							break;
						}
						case "PUT":
						{
							// Get credentials
							list($username,$password)=getCredentials();
							// Read request
							$data=checkAndReadRequest();
							if(isset($data))
							{
								if(isset($data["subject"]) && isset($data["entry"]) && count($data)==2)
								{
									// Get new note data
									$subject=trim($data["subject"]);
									$entry=trim($data["entry"]);
									// Update note
									if(tryLogin($username,$password,$userID,$answer_info)) {
										list($answer_info,$answer)=updateNote($userID,$subject,$entry,$request[1]);
									}
									// Note locked?
									if($answer_info["code"]==ERROR_NOTE_LOCKED)
									{
										http_response_code(405);
										errorReturn(STRING_ERROR_NOTE_LOCKED,ERROR_NOTE_LOCKED);
									}
									// Note does not exists?
									else if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
									{
										http_response_code(404);
										errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
									}
									// No necessary information?
									else if($answer_info["code"]==ERROR_NO_NECESSARY_INFORMATION)
									{
										http_response_code(422);
										errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
									}
									// User account disabled?
									else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
									{
										http_response_code(403);
										errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
									}
									// Or credentials incorrect?
									else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
									{
										http_response_code(401);
										errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
									}
									// Or note updated successfully?
									else
									{
										// Get updated note
										if(tryLogin($username,$password,$userID,$answer_info_2)) {
											list($answer_info_2,$answer_2)=getNote($userID,$request[1]);
										}
										// Any errors?
										if(($answer_info_2["code"]==ERROR_NOTE_NOT_EXISTS) || ($answer_info_2["code"]==ERROR_USER_DEACTIVATED) || ($answer_info_2["code"]==ERROR_LOGIN_INCORRECT))
										{
											// Respond
											http_response_code(500);
											errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
										}
										else
										{
											// Respond
											http_response_code(200);
											jsonReturn($answer_2["note"]);
										}
									}
								}
								else
								{
									http_response_code(422);
									errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
								}
							}
							break;
						}
						case "PATCH":
						{
							// Get credentials
							list($username,$password)=getCredentials();
							// Read request with ignoring empty request
							$data=checkAndReadRequest(true);
							// Variable for counting entries in request
							$entrycount=0;
							if(isset($data))
							{
								// Is subject change in request?
								if(isset($data["subject"]))
								{
									$subject=trim($data["subject"]);
									$entrycount=$entrycount+1;
								}
								// Is entry change in request?
								if(isset($data["entry"]))
								{
									$entry=trim($data["entry"]);
									$entrycount=$entrycount+1;
								}
								// Is entry count equal to entries in request?
								if($entrycount==count($data))
								{
									// If there are subject and entry in request, then just update note traditionally
									if($entrycount==2)
									{
										if(tryLogin($username,$password,$userID,$answer_info)) {
											list($answer_info,$answer)=updateNote($userID,$subject,$entry,$request[1]);
										}
									}
									// If not
									else
									{
										if(tryLogin($username,$password,$userID,$answer_info)) {
											// If there was subject change request
											if(isset($subject))
											{
												list($answer_info,$answer)=updateNoteSubject($userID,$subject,$request[1]);
											}
											// If there was entry change request
											if(isset($entry))
											{
												list($answer_info,$answer)=updateNoteEntry($userID,$entry,$request[1]);
											}
										}
									}
									// Anything changed at all?
									if(isset($answer_info))
									{
										// Note locked?
										if($answer_info["code"]==ERROR_NOTE_LOCKED)
										{
											http_response_code(405);
											errorReturn(STRING_ERROR_NOTE_LOCKED,ERROR_NOTE_LOCKED);
											break;
										}
										// Note does not exists?
										else if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
										{
											http_response_code(404);
											errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
											break;
										}
										// No necessary information?
										else if($answer_info["code"]==ERROR_NO_NECESSARY_INFORMATION)
										{
											http_response_code(422);
											errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
											break;
										}
										// User account disabled?
										else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
										{
											http_response_code(403);
											errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
											break;
										}
										// Or credentials incorrect?
										else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
										{
											http_response_code(401);
											errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
											break;
										}
									}
									// Get updated (or not) note
									if(tryLogin($username,$password,$userID,$answer_info_2)) {
										list($answer_info_2,$answer_2)=getNote($userID,$request[1]);
									}
									// Any errors?
									if(($answer_info_2["code"]==ERROR_NOTE_NOT_EXISTS) || ($answer_info_2["code"]==ERROR_USER_DEACTIVATED) || ($answer_info_2["code"]==ERROR_LOGIN_INCORRECT))
									{
										// Respond
										http_response_code(500);
										errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
									}
									else
									{
										// Respond
										http_response_code(200);
										jsonReturn($answer_2["note"]);
									}
								}
								// Or not?
								else
								{
									http_response_code(422);
									errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
								}
							}
							else
							{
								if(!$shouldStop)
								{
									// Get not updated note
									if(tryLogin($username,$password,$userID,$answer_info_2)) {
										list($answer_info_2,$answer_2)=getNote($userID,$request[1]);
									}
									// Any errors?
									if(($answer_info_2["code"]==ERROR_NOTE_NOT_EXISTS) || ($answer_info_2["code"]==ERROR_USER_DEACTIVATED) || ($answer_info_2["code"]==ERROR_LOGIN_INCORRECT))
									{
										// Respond
										http_response_code(500);
										errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
									}
									else
									{
										// Respond
										http_response_code(200);
										jsonReturn($answer_2["note"]);
									}
								}
							}
							break;
						}
						case "DELETE":
						{
							// Get credentials
							list($username,$password)=getCredentials();
							// Delete note
							if(tryLogin($username,$password,$userID,$answer_info)) {
								list($answer_info,$answer)=deleteNote($userID,$request[1]);
							}
							// Note locked?
							if($answer_info["code"]==ERROR_NOTE_LOCKED)
							{
								http_response_code(405);
								errorReturn(STRING_ERROR_NOTE_LOCKED,ERROR_NOTE_LOCKED);
							}
							// Note does not exists?
							else if($answer_info["code"]==ERROR_NOTE_NOT_EXISTS)
							{
								http_response_code(404);
								errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
							}
							// User account disabled?
							else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
							{
								http_response_code(403);
								errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
							}
							// Or credentials incorrect?
							else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
							{
								http_response_code(401);
								errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
							}
							// Or maybe note deleted successfully?
							else
							{
								http_response_code(204);
							}
							break;
						}
						// Options here
						case "OPTIONS":
						{
							http_response_code(204);
							allowedMethods("GET,PUT,PATCH,DELETE");
							break;
						}
						// Invalid method
						default:
						{
							http_response_code(405);
							errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
							break;
						}
					}
				}
			}
			else
			{
				http_response_code(404);
				errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
			}
		}
		else
		{
			switch($method)
			{
				// Get note list
				case "GET":
				{
					// Get credentials
					list($username,$password)=getCredentials();
					// Get note list
					if(tryLogin($username,$password,$userID,$answer_info)) {
						list($answer_info,$answer)=noteList($userID);
					}
					// User account disabled?
					if($answer_info["code"]==ERROR_USER_DEACTIVATED)
					{
						http_response_code(403);
						errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
					}
					// Or credentials incorrect?
					else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
					{
						http_response_code(401);
						errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
					}
					// Or maybe list arrived successfully?
					else
					{
						http_response_code(200);
						jsonReturn($answer["notes_summary"]);
					}
					break;
				}
				// Add note
				case "POST":
				{
					// Get credentials
					list($username,$password)=getCredentials();
					// Read request
					$data=checkAndReadRequest();
					if(isset($data))
					{
						if(isset($data["subject"]) && isset($data["entry"]) && count($data)==2)
						{
							// Get new note data
							$subject=trim($data["subject"]);
							$entry=trim($data["entry"]);
							// Add note
							if(tryLogin($username,$password,$userID,$answer_info)) {
								list($answer_info,$answer)=addNote($userID,$subject,$entry);
							}
							// Internal server error?
							if($answer_info["code"]==ERROR_INTERNAL_SERVER_ERROR)
							{
								http_response_code(500);
								errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
							}
							// No necessary information?
							else if($answer_info["code"]==ERROR_NO_NECESSARY_INFORMATION)
							{
								http_response_code(422);
								errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
							}
							// User account disabled?
							else if($answer_info["code"]==ERROR_USER_DEACTIVATED)
							{
								http_response_code(403);
								errorReturn(STRING_ERROR_USER_DEACTIVATED,ERROR_USER_DEACTIVATED);
							}
							// Or credentials incorrect?
							else if($answer_info["code"]==ERROR_LOGIN_INCORRECT)
							{
								http_response_code(401);
								errorReturn(STRING_ERROR_LOGIN_INCORRECT,ERROR_LOGIN_INCORRECT);
							}
							// Or note added successfully?
							else
							{
								// Get new ID
								$newID=$answer["new_id"];
								// Get created note
								if(tryLogin($username,$password,$userID,$answer_info_2)) {
									list($answer_info_2,$answer_2)=getNote($userID,$newID);
								}
								// Any errors?
								if(($answer_info_2["code"]==ERROR_NOTE_NOT_EXISTS) || ($answer_info_2["code"]==ERROR_USER_DEACTIVATED) || ($answer_info_2["code"]==ERROR_LOGIN_INCORRECT))
								{
									// Respond
									http_response_code(500);
									errorReturn(STRING_ERROR_INTERNAL,ERROR_INTERNAL);
								}
								else
								{
									// Respond
									http_response_code(201);
									jsonReturn($answer_2["note"]);
								}
							}
						}
						else
						{
							http_response_code(422);
							errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
						}
					}
					break;
				}
				// Options here
				case "OPTIONS":
				{
					http_response_code(204);
					allowedMethods("GET,POST");
					break;
				}
				// Invalid method
				default:
				{
					http_response_code(405);
					errorReturn(STRING_ERROR_INVALID_METHOD,ERROR_INVALID_METHOD);
					break;
				}
			}
		}
		break;
	}
	// Unknown objects
	default:
	{
		http_response_code(404);
		errorReturn(STRING_ERROR_NOT_FOUND,ERROR_NOT_FOUND);
		break;
	}
}

?>
