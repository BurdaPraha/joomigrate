<?php

// variables
$newdomain  = "http://domain.com";
$servername = "localhost";
$username   = "root";
$password   = "root";
$dbname     = "your-joomla-databse";
$prefix     = "your-joomla-database-prefix";

// Create connection
$table      = $prefix . "_redj_redirects";
$conn       = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query
$sql = "SELECT fromurl, tourl, hits FROM " . $table . " WHERE redirect=301 AND hits > 10 ORDER BY hits DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0)
{
    echo "<h1>Please copy these rules and paste to your .htaccess</h1>";
    echo "<p>It's in good format - don't worry</p>";
    echo "<textarea style='width: 100%; height: 65%; padding: 10px;'>";

    // title
    echo "# redirects from Joomla redirect component:\n";

    // output data of each row
    while($row = $result->fetch_assoc())
    {
        echo formatRow("Redirect 301 " . $row["fromurl"], $newdomain . $row["tourl"]);
    }
    echo '</textarea>';

} else
{
    echo "0 results";
}
$conn->close();

function formatRow($firstColumn, $secondColumn, $spaceBetweenColumns = 5)
{
    $values = [];
    $values[] = $firstColumn;
    $values[] = $secondColumn;

    return vsprintf("%-120s\t %s\t", $values)."\n";
}
