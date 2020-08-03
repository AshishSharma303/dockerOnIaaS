# Container Code Walk Through

- [Container Code Walk Through](#container-code-walk-through)
  - [Introduction](#introduction)
  - [Code Walk Through](#code-walk-through)
    - [HTML Code](#html-code)
    - [PHP Code](#php-code)
    - [Apache Site Configuration](#apache-site-configuration)
  - [Tying It All Together](#tying-it-all-together)
  - [Reference](#reference)

## Introduction

The application in the container is running html and php code and is hosted on apache.
In the backend its using mysqli to connect to a data base and insert records fetched from user input to a database called pocdb.

## Code Walk Through

### HTML Code

An html code block is used to get user inputs and pass the values to a php script.
The html file is named `index.html` and is stored `/var/www/poc`.

```
<!DOCTYPE html>
<html>
<head>
<title>Form site</title>
</head>
<body>
<form method="post" action="conn.php">
Identifier : <input type="text" name="Identifier"><br><br>
Key : <input type="text" name="Key"><br><br>
dbservername : <input type="text" name="dbservername"><br><br>
databasename : <input type="text" name="databasename"><br><br>
dbusername : <input type="text" name="dbusername"><br><br>
dbpassword : <input type="password" name="dbpassword"><br><br>
<input type="submit" value="Submit">
</form>
</body>
</html>
```
With the user inputs the html file is calling `conn.php` file located in the same directory with a POST method to insert data to a database.

### PHP Code

The PHP code is stored inside the file conn.php at `/var/www/poc` and connects to a database using mysqli depending on the user input. If ssl is enforced on azure database for MySql then mysqli needs to connect to the database server securely. The root certificate required to securely connect to the database server is stored at `/var/www/html` and is name BaltimoreCyberTrustRoot.crt.pem. This certificate can be downloaded from this [link](https://docs.microsoft.com/en-us/azure/mysql/howto-configure-ssl). conn.php doesnt do a check whether database exists or not. It tries to write data to a specific database(pocdb) once it connects to the database server. The code checks for a table named dockerpoc and if it's not there, it will create it programmatically.

```
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
```

### Apache Site Configuration

Apache site configuration is stored at location `/etc/apache2/sites-available` under the name `poc.conf`
with `a2enmod ssl` ssl mod is installed. Default apache configuration is disabled on the base image with `a2dissite 000-default.conf` and a custom conf is activated with `a2ensite poc.conf` which uses `/var/www/poc` as the root directory for the application.

```
LoadModule ssl_module modules/mod_ssl.so
<VirtualHost *:443>
    ServerName attdemo.azure.com
    SSLEngine on
    SSLCertificateFile "/var/www/html/crt.crt"
    SSLCertificateKeyFile "/var/www/html/key.key"
    DocumentRoot /var/www/poc
</VirtualHost>
```
Apache is configured to listen on port 443 and is using a self signed certificate issued to [CN=attdemo.azure.com]() and the crt and key files are placed at `/var/www/html/`.

## Tying It All Together

The content of `/var/www/poc` can be modified to change the behavior of the application as apache is using it as the root directory. Apache server listener certificates can be rotated/changed by replacing `/var/www/html/crt.crt` and `/var/www/html/key.key`. If you have intermediate certificates then SSLCertificateChainFile directive can be used in apache config file as well.
Dockerfile can be created to change the content of the website or rotate the certificates.

```
FROM maanan/external:apache2_mysql_ssl
COPY index.html /var/www/poc
COPY conn.php /var/www/poc
```

## Reference

[Apache ssl_mod](https://httpd.apache.org/docs/current/mod/mod_ssl.html)\
[Apache ssl_howto](https://httpd.apache.org/docs/2.4/ssl/ssl_howto.html)\
[PHP Mysqli-connect](https://www.php.net/manual/en/function.mysqli-connect.php)\
[PHP mysqli-ssl-set](https://www.php.net/manual/en/mysqli.ssl-set.php)\
[HTML form method](https://html.com/attributes/form-method/#ltform_method8221POST8221gt)
