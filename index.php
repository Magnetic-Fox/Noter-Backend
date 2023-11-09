<?php

include('mysql-connect.php');
include('noter-config.php');
header("Content-Type: application/json");

$server_info=array("name" => $server_name, "timezone" => $server_timezone, "version" => "1.0");
$response=array();

/*

NoterAPI v1.0a
(C)2021-2023 Bartłomiej "Magnetic-Fox" Węgrzyn!

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


 Information codes:
--------------------

-768	Service temporarily disabled

-512	Internal Server Error (DB Connection Error)

-14	Note already unlocked
-13	Note already locked
-12	Note locked
-11	User removal failure
-10	User does not exist
-9	Note does not exist
-8	No necessary information
-7	User deactivated
-6	Login incorrect
-5	Unknown action
-4	No credentials provided
-3	User exists
-2	No usable information in POST
-1	Invalid request method

0	OK

1	User successfully created
2	User successfully updated
3	User successfully removed
4	List command successful
5	Note retrieved successfully
6	Note created successfully
7	Note updated successfully
8	Note deleted successfully
9	User info retrieved successfully
10	Note locked successfully
11	Note unlocked successfully

*/



// --------------------------------------------------------------------------------------------
// Definition of functions
// --------------------------------------------------------------------------------------------

function exportDate($dateString)
{
	return date("Y-m-d H:i:s", strtotime($dateString));
}

function nowDate()
{
	global $server_timezone;
	date_default_timezone_set($server_timezone);
	$dt=DateTime::createFromFormat("U.u", microtime(true));
	$dt->setTimeZone(new DateTimeZone($server_timezone));
	return $dt->format("Y-m-d H:i:s.u");
}

function answerInfo($code, $attachment = array())
{
	return array("code" => $code, "attachment" => $attachment);
}

function userExists($username)
{
	global $conn;
	$query="SELECT COUNT(*) FROM Noter_Users WHERE UserName=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("s",$username);
	$stmt->execute();
	$stmt->bind_result($resp);
	$stmt->fetch();
	return $resp;
}

function login($username, $password)
{
	global $conn;
	if(userExists($username))
	{
		$query="SELECT ID, UserName, PasswordHash, Active FROM Noter_Users WHERE UserName=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("s",$username);
		$stmt->execute();
		$stmt->bind_result($id,$username,$passwordHash,$active);
		$stmt->fetch();
		if($active)
		{
			if(password_verify($password,$passwordHash))
			{
				return $id;
			}
		}
		else
		{
			return -1;
		}
	}
	return 0;
}

function register($username, $password)
{
	global $conn;
	if(userExists($username))
	{
		return -1;
	}
	else
	{
		if(($username!="") && ($password!=""))
		{
			$now=nowDate();
			$passwordHash=password_hash($password,PASSWORD_DEFAULT);
			$query="INSERT INTO Noter_Users(UserName, PasswordHash, DateRegistered, RemoteAddress, ForwardedFor, UserAgent, LastChanged, LastRemoteAddress, LastForwardedFor, LastUserAgent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("ssssssssss",$username,$passwordHash,$now,$_SERVER["REMOTE_ADDR"],$_SERVER["HTTP_X_FORWARDED_FOR"],$_SERVER["HTTP_USER_AGENT"],$now,$_SERVER["REMOTE_ADDR"],$_SERVER["HTTP_X_FORWARDED_FOR"],$_SERVER["HTTP_USER_AGENT"]);
			$stmt->execute();
			if($conn->affected_rows!=-1)
			{
				return 1;
			}
		}
	}
	return 0;
}

function userUpdate($username, $password, $newPassword)
{
	global $conn;
	//$newPassword=trim($newPassword);
	if(($username!="") && ($password!="") && ($newPassword!=""))
	{
		$res=login($username,$password);
		if($res>=1)
		{
			$now=nowDate();
			$passwordHash=password_hash($newPassword,PASSWORD_DEFAULT);
			$query="UPDATE Noter_Users SET PasswordHash=?, LastChanged=?, LastRemoteAddress=?, LastForwardedFor=?, LastUserAgent=? WHERE ID=?";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("sssssi",$passwordHash,$now,$_SERVER["REMOTE_ADDR"],$_SERVER["HTTP_X_FORWARDED_FOR"],$_SERVER["HTTP_USER_AGENT"],$res);
			$stmt->execute();
			if($conn->affected_rows!=-1)
			{
				return 1;
			}
		}
		else if($res==-1)
		{
			return -1;
		}
	}
	return 0;
}

function noteLocked($noteID)
{
	global $conn;
	$query="SELECT Locked FROM Noter_Entries WHERE ID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("i",$noteID);
	$stmt->execute();
	$stmt->bind_result($locked);
	$stmt->fetch();
	return $locked;
}

function noteLockState($noteID, $userID, $lockState)
{
	global $conn;
	$query="UPDATE Noter_Entries SET Locked=? WHERE ID=? AND UserID=?";
	$stmt=$conn->prepare($query);
	$stmt->bind_param("iii",$lockState,$noteID,$userID);
	$stmt->execute();
	return ($conn->affected_rows>0);
}

function userRegister($username, $password)
{
	global $conn;
	$answer_info=null;
	$res=register($username,$password);
	if($res==-1)
	{
		$answer_info=answerInfo(-3);
	}
	else if($res==1)
	{
		$answer_info=answerInfo(1);
	}
	else
	{
		$answer_info=answerInfo(-4);
	}
	return array($answer_info,null);
}

function userChangePassword($username, $password, $newPassword)
{
	global $conn;
	$answer_info=null;
	$res=userUpdate($username,$password,$newPassword);
	if($res==1)
	{
		$answer_info=answerInfo(2);
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,null);
}

function userInfo($username, $password)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		$query="SELECT ID, UserName, DateRegistered, UserAgent, LastChanged, LastUserAgent FROM Noter_Users WHERE ID=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("i",$res);
		$stmt->execute();
		$stmt->bind_result($id, $username, $dateRegistered, $userAgent, $lastChanged, $lastUserAgent);
		$stmt->fetch();
		$answer=array("user" => array("id" => $id, "username" => $username, "date_registered" => exportDate($dateRegistered), "user_agent" => $userAgent, "last_changed" => exportDate($lastChanged), "last_user_agent" => $lastUserAgent));
		$answer_info=answerInfo(9,array("user"));
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function userRemove($username, $password)
{
	global $conn;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		$query="DELETE FROM Noter_Entries WHERE UserID=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("i",$res);
		$stmt->execute();
		if($conn->affected_rows!=-1)
		{
			$query="DELETE FROM Noter_Users WHERE ID=?";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("i",$res);
			$stmt->execute();
			if($conn->affected_rows>0)
			{
				$answer_info=answerInfo(3);
			}
			else
			{
				$answer_info=answerInfo(-10);
			}
		}
		else
		{
			$answer_info=answerInfo(-11);
		}
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,null);
}

function noteList($username, $password)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		$query="SELECT ID, Subject, LastModified FROM Noter_Entries WHERE UserID = ? ORDER BY LastModified DESC";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("i",$res);
		$stmt->execute();
		$stmt->bind_result($id, $subject, $lastModified);
		$count=0;
		$notesSummary=array();
		while($stmt->fetch())
		{
			$count++;
			array_push($notesSummary,array("id" => $id, "subject" => $subject, "last_modified" => exportDate($lastModified)));
		}
		$answer_info=answerInfo(4,array("count","notes_summary"));
		$answer=array("count" => $count, "notes_summary" => $notesSummary);
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function getNote($username, $password, $noteID)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		$query="SELECT ID, Subject, Entry, DateAdded, LastModified, Locked, UserAgent, LastUserAgent FROM Noter_Entries WHERE ID=? AND UserID=?";
		$stmt=$conn->prepare($query);
		$stmt->bind_param("ii",$noteID,$res);
		$stmt->execute();
		$stmt->bind_result($id,$subject,$entry,$dateAdded,$lastModified,$locked,$userAgent,$lastUserAgent);
		if($stmt->fetch())
		{
			$answer_info=answerInfo(5,array("note"));
			$answer=array("note" => array("id" => $id, "subject" => $subject, "entry" => $entry, "date_added" => exportDate($dateAdded), "last_modified" => exportDate($lastModified), "locked" => $locked, "user_agent" => $userAgent, "last_user_agent" => $lastUserAgent));
		}
		else
		{
			$answer_info=answerInfo(-9);
		}
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function addNote($username, $password, $subject, $entry)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		if(($subject!="") && ($entry!=""))
		{
			$now=nowDate();
			$query="INSERT INTO Noter_Entries(UserID, Subject, Entry, DateAdded, LastModified, RemoteAddress, ForwardedFor, UserAgent, LastRemoteAddress, LastForwardedFor, LastUserAgent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("issssssssss",$res,$subject,$entry,$now,$now,$_SERVER["REMOTE_ADDR"],$_SERVER["HTTP_X_FORWARDED_FOR"],$_SERVER["HTTP_USER_AGENT"],$_SERVER["REMOTE_ADDR"],$_SERVER["HTTP_X_FORWARDED_FOR"],$_SERVER["HTTP_USER_AGENT"]);
			$stmt->execute();
			if($conn->affected_rows!=-1)
			{
				$answer_info=answerInfo(6,array("new_id"));
				$query="SELECT MAX(ID) FROM Noter_Entries WHERE UserID=?";
				$stmt=$conn->prepare($query);
				$stmt->bind_param("i",$res);
				$stmt->execute();
				$stmt->bind_result($newID);
				$stmt->fetch();
				$answer=array("new_id" => $newID);
			}
			else
			{
				$answer_info=answerInfo(-512);
			}
		}
		else
		{
			$answer_info=answerInfo(-8);
		}
		
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function updateNote($username, $password, $subject, $entry, $noteID)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		if(($subject!="") && ($entry!=""))
		{
			if(noteLocked($noteID))
			{
				$answer_info=answerInfo(-12);
			}
			else
			{
				$now=nowDate();
				$query="UPDATE Noter_Entries SET Subject=?, Entry=?, LastModified=?, LastRemoteAddress=?, LastForwardedFor=?, LastUserAgent=? WHERE ID=? AND UserID=?";
				$stmt=$conn->prepare($query);
				$stmt->bind_param("ssssssii",$subject,$entry,$now,$_SERVER["REMOTE_ADDR"],$_SERVER["HTTP_X_FORWARDED_FOR"],$_SERVER["HTTP_USER_AGENT"],$noteID,$res);
				$stmt->execute();
				if($conn->affected_rows>0)
				{
					$answer_info=answerInfo(7);
				}
				else
				{
					$answer_info=answerInfo(-9);
				}
			}
		}
		else
		{
			$answer_info=answerInfo(-8);
		}
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function deleteNote($username, $password, $noteID)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		if(noteLocked($noteID))
		{
			$answer_info=answerInfo(-12);
		}
		else
		{
			$query="DELETE FROM Noter_Entries WHERE ID=? AND UserID=?";
			$stmt=$conn->prepare($query);
			$stmt->bind_param("ii",$noteID,$res);
			$stmt->execute();
			if($conn->affected_rows>0)
			{
				$answer_info=answerInfo(8);
			}
			else
			{
				$answer_info=answerInfo(-9);
			}
		}
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function lockNote($username, $password, $noteID)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		if(noteLockState($noteID,$res,true))
		{
			$answer_info=answerInfo(10);
		}
		else
		{
			if(noteLocked($noteID))
			{
				$answer_info=answerInfo(-13);
			}
			else
			{
				$answer_info=answerInfo(-9);
			}
		}
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

function unlockNote($username, $password, $noteID)
{
	global $conn;
	$answer=null;
	$answer_info=null;
	$res=login($username,$password);
	if($res>=1)
	{
		if(noteLockState($noteID,$res,false))
		{
			$answer_info=answerInfo(11);
		}
		else
		{
			if(noteLocked($noteID))
			{
				$answer_info=answerInfo(-9);
			}
			else
			{
				$answer_info=answerInfo(-14);
			}
		}
	}
	else if($res==-1)
	{
		$answer_info=answerInfo(-7);
	}
	else
	{
		$answer_info=answerInfo(-6);
	}
	return array($answer_info,$answer);
}

// --------------------------------------------------------------------------------------------
// Main part of the script.
// --------------------------------------------------------------------------------------------

if(!$noter_enabled)
{
	$answer_info=answerInfo(-768);
	$response=array("server" => $server_info, "answer_info" => $answer_info);
	die(json_encode($response));
}

if($conn->connect_error)
{
	$answer_info=answerInfo(-512);
	$response=array("server" => $server_info, "answer_info" => $answer_info);
	die(json_encode($response));
}

if($_SERVER["REQUEST_METHOD"]=="GET")
{
	$answer_info=answerInfo(0);
}
else if($_SERVER["REQUEST_METHOD"]=="POST")
{
	if(array_key_exists("action",$_POST) && (array_key_exists("username",$_POST)) && array_key_exists("password",$_POST))
	{
		$ut=trim($_POST["username"]);
		$pt=trim($_POST["password"]);
		if($_POST["action"]=="register")
		{
			if(($ut!="") && ($pt!=""))
			{
				list($answer_info,$answer)=userRegister($ut,$pt);
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="change")
		{
			if(($ut!="") && ($pt!=""))
			{
				if(array_key_exists("newPassword",$_POST))
				{
					$newPassword=trim($_POST["newPassword"]);
					list($answer_info,$answer)=userChangePassword($ut,$pt,$newPassword);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="info")
		{
			if(($ut!="") && ($pt!=""))
			{
				list($answer_info,$answer)=userInfo($ut,$pt);
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="remove")
		{
			if(($ut!="") && ($pt!=""))
			{
				list($answer_info,$answer)=userRemove($ut,$pt);
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="list")
		{
			if(($ut!="") && ($pt!=""))
			{
				list($answer_info,$answer)=noteList($ut,$pt);
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="retrieve")
		{
			if(($ut!="") && ($pt!=""))
			{
				if(array_key_exists("noteID",$_POST))
				{
					$noteID=$_POST["noteID"];
					list($answer_info,$answer)=getNote($ut,$pt,$noteID);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="add")
		{
			if(($ut!="") && ($pt!=""))
			{
				if(array_key_exists("subject",$_POST) && array_key_exists("entry",$_POST))
				{
					$subt=trim($_POST["subject"]);
					$entt=trim($_POST["entry"]);
					list($answer_info,$answer)=addNote($ut,$pt,$subt,$entt);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="update")
		{
			if(($ut!="") && ($pt!=""))
			{
				if((array_key_exists("noteID",$_POST)) && (array_key_exists("subject",$_POST)) && (array_key_exists("entry",$_POST)))
				{
					$subt=trim($_POST["subject"]);
					$entt=trim($_POST["entry"]);
					$noteID=$_POST["noteID"];
					list($answer_info,$answer)=updateNote($ut,$pt,$subt,$entt,$noteID);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="delete")
		{
			if(($ut!="") && ($pt!=""))
			{
				if(array_key_exists("noteID",$_POST))
				{
					$noteID=$_POST["noteID"];
					list($answer_info,$answer)=deleteNote($ut,$pt,$noteID);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="lock")
		{
			if(($ut!="") && ($pt!=""))
			{
				if(array_key_exists("noteID",$_POST))
				{
					$noteID=$_POST["noteID"];
					list($answer_info,$answer)=lockNote($ut,$pt,$noteID);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else if($_POST["action"]=="unlock")
		{
			if(($ut!="") && ($pt!=""))
			{
				if(array_key_exists("noteID",$_POST))
				{
					$noteID=$_POST["noteID"];
					list($answer_info,$answer)=unlockNote($ut,$pt,$noteID);
				}
				else
				{
					$answer_info=answerInfo(-8);
				}
			}
			else
			{
				$answer_info=answerInfo(-4);
			}
		}
		else
		{
			$answer_info=answerInfo(-5);
		}
	}
	else
	{
		$answer_info=answerInfo(-2);
	}
}
else
{
	$answer_info=answerInfo(-1);
}

$response=array("server" => $server_info, "answer_info" => $answer_info);
if(isset($answer))
{
	$response["answer"]=$answer;
}

echo json_encode($response);

?>
