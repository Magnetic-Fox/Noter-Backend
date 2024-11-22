<?php

/*

Noter REST API 1.0a additional (comparing to original backend API) constants
(C)2023-2024 Bartłomiej "Magnetic-Fox" Węgrzyn!

*/

const API_DIRECTORY=			"rest_api";

const STRING_ERROR_INVALID_METHOD=	"Requested method not allowed here.";
const STRING_ERROR_NOT_FOUND=		"Requested object was not found.";
const STRING_ERROR_LOGIN_INCORRECT=	"Incorrect login information.";
const STRING_ERROR_BAD_REQUEST=		"This request was not properly formatted.";
const STRING_ERROR_WRONG_MEDIA=		"Media type sent not supported.";
const STRING_ERROR_NO_USABLE_INFORMATION="Request had invalid or missing data.";
const STRING_ERROR_USER_EXISTS=		"User exists. You have to choose another username.";
const STRING_ERROR_INTERNAL=		"Internal server error.";
const STRING_ERROR_USER_DEACTIVATED=	"User deactivated.";
const STRING_ERROR_NOTE_LOCKED=		"Note locked.";

const ERROR_INTERNAL=			-18;
const ERROR_WRONG_MEDIA=		-17;
const ERROR_BAD_REQUEST=		-16;
const ERROR_NOT_FOUND=			-15;

?>
