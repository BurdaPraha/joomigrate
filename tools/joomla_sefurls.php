<?php
//
// Move aliases & tags from SEF component to content table for better exporting to CSV
//

// variables
$servername = "localhost";
$username   = "root";
$password   = "root";
$dbname     = "your-joomla-databse";
$prefix     = "your-joomla-database-prefix";
$t_sef      = "{$prefix}_sefurls";
$t_content  = "{$prefix}_content";
$conn       = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// count before
$missing_sql = "SELECT title, alias, created FROM jos_content WHERE alias BETWEEN '2001-01-02-12-32-26' AND '2019-01-02-12-32-26' ORDER BY created DESC";
$check_missing = $conn->query($missing_sql);
echo "- MISSING ALIASES: {$check_missing->num_rows} \n";

// Custom pair column for easy pairs
$fix = $conn->query("ALTER TABLE `{$t_sef}` ADD `content_id` int NOT NULL;");

$result = $conn->query("SELECT sefurl, origurl, metakey, content_id FROM `{$t_sef}` WHERE cpt > 0 AND enabled = 1 ORDER BY id DESC");
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
            //$sql = "UPDATE `{$t_sef}` SET content_id=0 WHERE origurl='" . $params . "'";
            //$conn->query($sql);
            continue;
        }

        $var = explode('&id=', $params);
        if(isset($var[1])){
            $id = explode('&view=article', $var[1]);
            $id = (int) $id[0];
        }

        if($id > 0) {
            // add ID for better search
            $conn->query("UPDATE `{$t_sef}` SET content_id={$id} WHERE origurl='{$params}'");

            // update content
            $conn->query("UPDATE `{$t_content}` SET alias='{$alias}', metakey='{$metakey}' WHERE id={$id}");
            ++$up;
        }
    }

    // affected rows
    echo "- UPDATED ROWS: {$up}\n";

    // count after
    $check_missing = $conn->query($missing_sql);
    echo "- MISSING ALIASES: {$check_missing->num_rows}\n";

    echo "-- CREATED | ALIAS | TITLE\n";
    while($row = $check_missing->fetch_assoc())
    {
        echo "-- {$row['created']} | {$row['alias']} | {$row['title']}\n";
    }

}
else
{
    echo "0 results";
}

$conn->close();
