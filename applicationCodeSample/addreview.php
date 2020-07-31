<?php

$hostname = "myfirstmariadb.mariadb.database.azure.com";
$username = "myadmin@myfirstmariadb";
$password = "Password@123";
$db = "sampledb";

$dbconnect=mysqli_connect($hostname,$username,$password,$db);

if ($dbconnect->connect_error) {
  die("Database connection failed: " . $dbconnect->connect_error);
}

if(isset($_POST['submit'])) {
  $id=$_POST['id'];
  $name=$_POST['name'];
  $quantity=$_POST['quantity'];

  $query = "INSERT INTO inventory (id, name, quantity)
  VALUES ('$id', '$name', '$quantity')";

    if (!mysqli_query($dbconnect, $query)) {
        die('An error occurred. Your record has not been submitted.');
    } else {
      echo "Thanks for submiting the record.";
    }

}
?>