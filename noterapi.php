<?php

include_once('mysql-connect.php');
include_once('noter-config.php');
include_once('noterconst.php');

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
	
?>