<?php

$servername="localhost";	// localhost or something another
$username="";			// obviously, you have
$password="";			// to provide valid username,
$dbname="";			// password and database name here

$conn=new mysqli($servername, $username, $password, $dbname);

mysqli_set_charset($conn,"utf8");

?>
