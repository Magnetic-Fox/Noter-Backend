<?php

/*

Noter REST API 1.0a helper procedures
(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

*/

// INCLUDES SECTION
include_once('../noter-config.php');
include_once('../noterapi.php');
include_once('../noterconst.php');
include_once('restconst.php');

// HELPER PROCEDURES SECTION
// Some global variable
$shouldStop=false;

function prepareRequest()
{
	$uri=explode("/",$_SERVER['REQUEST_URI']);
	$pos=array_search(API_DIRECTORY,$uri);
	return array_filter(array_slice($uri,$pos+1));
}

function jsonReturn($inputArray)
{
	echo json_encode($inputArray);
	return;
}

function errorReturn($error,$errorCode)
{
	jsonReturn(array("error" => $error, "error_code" => $errorCode));
	return;
}

function allowedMethods($methods)
{
	header("Access-Control-Allow-Methods: ".$methods);
	return;
}

function authorize()
{
	global $server_name;
	if(!isset($_SERVER['PHP_AUTH_USER']))
	{
		header('WWW-Authenticate: Basic realm="'.$server_name.'"');
		http_response_code(401);
		return 0;
	}
	else
	{
		return login(trim($_SERVER['PHP_AUTH_USER']),trim($_SERVER['PHP_AUTH_PW']));
	}
}

function isJson($input)
{
	json_decode($input);
	return json_last_error() === JSON_ERROR_NONE;
}

function checkAndReadRequest($ignoreNothing = false)
{
	global $shouldStop;
	if(isset($_SERVER['CONTENT_TYPE']))
	{
		if($_SERVER['CONTENT_TYPE']=="application/json")
		{
			$input=file_get_contents("php://input");
			if(isJson($input))
			{
				return json_decode($input,true);
			}
			else
			{
				http_response_code(400);
				errorReturn(STRING_ERROR_BAD_REQUEST,ERROR_BAD_REQUEST);
				$shouldStop=true;
				return null;
			}
		}
		else
		{
			http_response_code(415);
			errorReturn(STRING_ERROR_WRONG_MEDIA,ERROR_WRONG_MEDIA);
			$shouldStop=true;
			return null;
		}
	}
	else
	{
		if(!$ignoreNothing)
		{
			http_response_code(422);
			errorReturn(STRING_ERROR_NO_USABLE_INFORMATION,ERROR_NO_USABLE_INFORMATION);
		}
		return null;
	}
}

function getCredentials()
{
	$username="";
	$password="";
	if(isset($_SERVER['PHP_AUTH_USER']))
	{
		$username=trim($_SERVER['PHP_AUTH_USER']);
	}
	if(isset($_SERVER['PHP_AUTH_PW']))
	{
		$password=trim($_SERVER['PHP_AUTH_PW']);
	}
	return array($username,$password);
}

?>
