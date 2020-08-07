<?php
$username = filter_input(INPUT_POST, 'Identifier');
$password = filter_input(INPUT_POST, 'Key');
$dbservername = filter_input(INPUT_POST, 'dbservername');
$databasename = filter_input(INPUT_POST, 'databasename');
$dbusername = filter_input(INPUT_POST, 'dbusername');
$dbpassword = filter_input(INPUT_POST, 'dbpassword');

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, "/var/www/html/BaltimoreCyberTrustRoot.crt.pem", NULL, NULL);
mysqli_real_connect($conn, $dbservername, $dbusername, $dbpassword, $databasename, 3306, MYSQLI_CLIENT_SSL);
if (mysqli_connect_errno($conn)) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}

echo "Connected successfully";
echo "</br>";
$result = $conn->query("SHOW TABLES LIKE 'dockerpoc'");
if ($result->num_rows == 1) {
    $sql = "INSERT INTO dockerpoc (username, password) VALUES ('$username', '$password')";
    if (mysqli_query($conn, $sql)) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
} else {
echo "Creating Table";
echo "</br>";
$table = "CREATE TABLE dockerpoc(username VARCHAR(255), password VARCHAR(400), PRIMARY KEY(username))";
$result = mysqli_query($conn, $table) or die($table);
$sql = "INSERT INTO dockerpoc (username, password) VALUES ('$username', '$password')";
if (mysqli_query($conn, $sql)) {
echo "New record created successfully";
} else {
echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}
}
mysqli_close($conn);
?>