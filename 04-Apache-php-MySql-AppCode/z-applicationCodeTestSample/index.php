<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<body>
<?php

$hostname = "myfirstmariadb.mariadb.database.azure.com";
$username = "myadmin@myfirstmariadb";
$password = "Password@123";
$db = "sampledb";

$dbconnect=mysqli_connect($hostname,$username,$password,$db);

if ($dbconnect->connect_error) {
  die("Database connection failed: " . $dbconnect->connect_error);
}

?>

<table border="1" align="center">
<tr>
  <td>id</td>
  <td>name</td>
  <td>quantity</td>
</tr>

<?php

$query = mysqli_query($dbconnect, "SELECT * FROM inventory")
   or die (mysqli_error($dbconnect));

while ($row = mysqli_fetch_array($query)) {
  echo
   "<tr>
    <td>{$row['id']}</td>
    <td>{$row['name']}</td>
    <td>{$row['quantity']}</td>
   </tr>\n";

}

?>
</table>
</body>
</html>