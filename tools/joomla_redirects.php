<?php

// variables
$servername = "localhost";
$username   = "root";
$password   = "root";
$dbname     = "your-joomla-databse";
$prefix     = "usc7d";

// Create connection
$table      = $prefix . "_redj_redirects";
$conn       = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query
$sql = "SELECT fromurl, tourl, hits FROM " . $table . " WHERE redirect=301 AND hits > 0 ORDER BY hits DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0)
{
    // title
    echo "# redirects from Joomla redirect component: <br>";

    // output data of each row
    while($row = $result->fetch_assoc())
    {
        echo "Redirect 301 " . $row["fromurl"]. " " . $row["tourl"]. " #" . $row["hits"]. "<br>";
    }

} else
{
    echo "0 results";
}
$conn->close();
