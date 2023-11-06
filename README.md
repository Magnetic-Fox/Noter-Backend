# Noter Backend

This repo contains my really simple (and ugly, tbh) PHP backend for my Noter project.

## What is included in this repo?

A PHP script implementing backend for simple notetaking (Noter, in short) application with some config PHP files.
The solution here is provided in "ready to use" form. That's because there is `index.php` file, which is a main backend file.
Files `mysql-connect.php` and `noter-config.php` are configuration files provided here with default values.
`test.php` is a really, really simple form for testing the backend.

## Database structure

For Noter to work, the database has to be prepared correctly.
There are two tables needed. One called `Noter_Users` and another one called `Noter_Entries`. The first one is for storing information about users (username, password hash, etc.) and the second one is for storing notes created by registered users.

### `Noter_Users` table
Here are the columns for `Noter_Users` table:
```
Column Name             Data Type               NULL?           Auto Increment? Key             Default (sugg.)
-----------------------+-----------------------+---------------+---------------+---------------+---------------
ID                      int(10) unsigned        NOT NULL        auto_increment  PRIMARY_KEY
UserName                varchar(255)            NOT NULL
PasswordHash            varchar(255)            NOT NULL
Active                  tinyint(1)              NOT NULL                                        1
DateRegistered          datetime(6)             NOT NULL
RemoteAddress           varchar(100)                                                            NULL
ForwardedFor            varchar(100)                                                            NULL
UserAgent               varchar(255)                                                            NULL
LastChanged             datetime(6)             NOT NULL
LastRemoteAddress       varchar(100)                                                            NULL
LastForwardedFor        varchar(100)                                                            NULL
LastUserAgent           varchar(255)                                                            NULL 
```

The only constraint needed for table above is to make ID a primary key.

### `Noter_Entries` table
Here are the columns for `Noter_Entries` table:
```
Column Name             Data Type               NULL?           Auto Increment? Key             Default (sugg.)
-----------------------+-----------------------+---------------+---------------+---------------+---------------
ID                      int(10) unsigned        NOT NULL        auto_increment  PRIMARY_KEY
UserID                  int(10) unsigned        NOT NULL                        FOREIGN KEY
Subject                 varchar(255)            NOT NULL
Entry                   longtext                NOT NULL
DateAdded               datetime(6)             NOT NULL
LastModified            datetime(6)             NOT NULL
Locked                  tinyint(1)              NOT NULL                                        0
RemoteAddress           varchar(100)                                                            NULL
ForwardedFor            varchar(100)                                                            NULL
UserAgent               varchar(255)                                                            NULL
LastRemoteAddress       varchar(100)                                                            NULL
LastForwardedFor        varchar(100)                                                            NULL
LastUserAgent           varchar(255)                                                            NULL
```

The constraints for table above are as follows:
* ID has to be primary key of the table
* UserID has to be foreign key pointing to the actual user ID in the `Noter_Users` table.

## SQL Codes for creating needed tables

For further information, there are SQL codes, generated by database editor of my choice (DBeaver), for creating the tables mentioned above.

### `Noter_Users`

```
CREATE TABLE `Noter_Users` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserName` varchar(255) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Active` tinyint(1) NOT NULL DEFAULT 1,
  `DateRegistered` datetime(6) NOT NULL,
  `RemoteAddress` varchar(100) DEFAULT NULL,
  `ForwardedFor` varchar(100) DEFAULT NULL,
  `UserAgent` varchar(255) DEFAULT NULL,
  `LastChanged` datetime(6) NOT NULL,
  `LastRemoteAddress` varchar(100) DEFAULT NULL,
  `LastForwardedFor` varchar(100) DEFAULT NULL,
  `LastUserAgent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`)
)
```

### `Noter_Entries`
```
CREATE TABLE `Noter_Entries` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `UserID` int(10) unsigned NOT NULL,
  `Subject` varchar(255) NOT NULL,
  `Entry` longtext NOT NULL,
  `DateAdded` datetime(6) NOT NULL,
  `LastModified` datetime(6) NOT NULL,
  `Locked` tinyint(1) NOT NULL DEFAULT 0,
  `RemoteAddress` varchar(100) DEFAULT NULL,
  `ForwardedFor` varchar(100) DEFAULT NULL,
  `UserAgent` varchar(255) DEFAULT NULL,
  `LastRemoteAddress` varchar(100) DEFAULT NULL,
  `LastForwardedFor` varchar(100) DEFAULT NULL,
  `LastUserAgent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `Noter_Entries_FK` (`UserID`),
  CONSTRAINT `Noter_Entries_FK` FOREIGN KEY (`UserID`) REFERENCES `Noter_Users` (`ID`)
)
``` 

## Disclaimer

I've made much effort to provide here working and checked codes with hope it will be useful.
**However, these codes are provided here "AS IS", with absolutely no warranty! I take no responsibility for using them - DO IT ON YOUR OWN RISK!**

## License

Codes provided here are free for personal use.
If you like to use any part of these codes in your software, just please give me some simple credits and it will be okay. ;)
In case you would like to make paid software and use parts of these codes - please, contact me before.

*Bartłomiej "Magnetic-Fox" Węgrzyn,
11th November, 2021,
6th November, 2023*
