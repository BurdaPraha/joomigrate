<?php

// variables
$servername = "localhost";
$username   = "root";
$password   = "root";
$dbname     = "your-joomla-databse";
$prefix     = "your-joomla-database-prefix";

// Create connection
$table      = $prefix . "_sefurls";
$conn       = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fix schema
$alter = "ALTER TABLE " . $prefix . " `_sefurls` ADD `content_id` int NOT NULL;";
$result = $conn->query($alter);

// Query
$sql = "SELECT sefurl, origurl, metakey FROM " . $table . " WHERE cpt > 0 AND enabled = 1 ORDER BY id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0)
{
    // output data of each row
    $up = 0;
    while($row = $result->fetch_assoc())
    {
        $id         = 0;
        $params     = $row['origurl'];
        $alias      = $row['sefurl'];
        $metakey    = $row['metakey'];

        if (strpos($params, '&limitstart') !== false || strpos($params, '&plugin=') !== false) {
            //$sql = "UPDATE " . $table . " SET content_id=0 WHERE origurl='" . $params . "'";
            //$conn->query($sql);
            continue;
        }

        $var = explode('&id=', $params);
        if(isset($var[1])){
            $id = explode('&view=article', $var[1]);
            $id = (int) $id[0];
        }

        if($id > 0)
        {
            // add ID for relation
            $conn->query("UPDATE " . $table . " SET content_id={$id} WHERE origurl='{$params}'");

            // update content
            $conn->query("UPDATE {$prefix}_content SET alias='{$alias}', metakey='{$metakey}' WHERE id={$id}");

            ++$up;
        }
    }

    echo "\n";
    echo "UPDATED {$up} ITEMS";
    echo "\n";

} else
{
    echo "0 results";
}

$conn->close();
