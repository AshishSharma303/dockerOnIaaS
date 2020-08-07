<?php

$hostname = "myfirstmariadb.mariadb.database.azure.com";
$username = "myadmin@myfirstmariadb";
$password = "Password@123";
$db = "sampledb";

$postid = filter_input(INPUT_POST, 'id');
$postname = filter_input(INPUT_POST, 'Name');
$postquantity = filter_input(INPUT_POST, 'quantity');

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, "/var/www/html/BaltimoreCyberTrustRoot.crt.pem", NULL, NULL);
mysqli_real_connect($conn, $hostname, $username, $password, $db, 3306, MYSQLI_CLIENT_SSL);

if (mysqli_connect_errno($conn)) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}

echo "Connected successfully";

$sql = "INSERT INTO inventory (id, name, quantity) VALUES ('$postid', '$postname', '$postquantity')";
    if (mysqli_query($conn, $sql)) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }

// Close the connection
mysqli_close($conn);
?>
