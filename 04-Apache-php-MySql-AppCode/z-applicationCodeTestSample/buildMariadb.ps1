# A server name maps to a DNS name and must be globally unique in Azure. Substitute <server_admin_password> with your own server admin password.

$rg="kube-rg01"
$admin="myadmin"
$maripassword="Password@123" 
$mariadbname="myfirstmariadb"
$mariadbserver=$mariadbname + ".mariadb.database.azure.com"
$adminserver=$admin + "@" + "$mariadbname"

az mariadb server create --resource-group $rg --name $mariadbname --location eastus2 --admin-user $admin --admin-password $maripassword --sku-name GP_Gen5_2 --version 10.2
az mariadb server show --resource-group $rg --name $mariadbname

# Keep the firewall open for all public EP's as this only the POC pourpose, delete the DB once POC is done:
​az mariadb server update --resource-group $rg --name $mariadbname --ssl-enforcement Disabled
# Open ports for the Azure servies:
az mariadb server firewall-rule create --resource-group $rg --server $mariadbname --name AllowMyIP --start-ip-address 0.0.0.0 --end-ip-address 0.0.0.0

# Connect to the server:
mysql -h $mariadbserver -u $adminserver -p

# View the server status at the mysql> prompt and build databases:
mysql> status
mysql> CREATE DATABASE sampledb;
mysql> USE sampledb;
mysql> CREATE TABLE inventory (id serial PRIMARY KEY, name VARCHAR(50), quantity INTEGER);
INSERT INTO inventory (id, name, quantity) VALUES (1, 'banana', 150); 
INSERT INTO inventory (id, name, quantity) VALUES (2, 'orange', 154);
SELECT * FROM inventory;

