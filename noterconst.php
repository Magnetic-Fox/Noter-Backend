<?php

/*

NoterAPI v1.0b (less ugly)
(C)2021-2023 Bartłomiej "Magnetic-Fox" Węgrzyn!

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

const ERROR_SERVICE_DISABLED=		-768;
const ERROR_INTERNAL_SERVER_ERROR=	-512;
const ERROR_NOTE_ALREADY_UNLOCKED=	-14;
const ERROR_NOTE_ALREADY_LOCKED=	-13;
const ERROR_NOTE_LOCKED=		-12;
const ERROR_USER_REMOVAL_FAILURE=	-11;
const ERROR_USER_NOT_EXISTS=		-10;
const ERROR_NOTE_NOT_EXISTS=		-9;
const ERROR_NO_NECESSARY_INFORMATION=	-8;
const ERROR_USER_DEACTIVATED=		-7;
const ERROR_LOGIN_INCORRECT=		-6;
const ERROR_UNKNOWN_ACTION=		-5;
const ERROR_NO_CREDENTIALS=		-4;
const ERROR_USER_EXISTS=		-3;
const ERROR_NO_USABLE_INFORMATION=	-2;
const ERROR_INVALID_METHOD=		-1;

const INFO_OK=				0;

const INFO_USER_CREATED=		1;
const INFO_USER_UPDATED=		2;
const INFO_USER_REMOVED=		3;
const INFO_LIST_SUCCESSFUL=		4;
const INFO_NOTE_RETRIEVED=		5;
const INFO_NOTE_CREATED=		6;
const INFO_NOTE_UPDATED=		7;
const INFO_NOTE_DELETED=		8;
const INFO_USER_INFO_RETRIEVED=		9;
const INFO_NOTE_LOCKED=			10;
const INFO_NOTE_UNLOCKED=		11;

?>