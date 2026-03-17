<?php
require_once __DIR__ . '/db.php';

$dbPath = __DIR__ . '/../Data/electro_waste.sqlite';
echo "Expected path: " . $dbPath . "<br>";
echo "Expected realpath: " . (realpath($dbPath) ?: "NOT FOUND") . "<br>";

$db = db();
echo "DB connected OK<br>";

$res = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
if (!$res) {
  echo "Query failed: " . $db->lastErrorMsg();
  exit;
}
echo "Tables:<br>";
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
  echo "- " . $row["name"] . "<br>";
}