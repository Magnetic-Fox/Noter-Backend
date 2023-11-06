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
