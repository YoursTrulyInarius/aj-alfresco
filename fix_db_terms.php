<?php
$c = new mysqli('localhost', 'root', '', 'aj_alfresco_rms');
if ($c->connect_error) die($c->connect_error);
$c->query('ALTER TABLE contracts ADD COLUMN IF NOT EXISTS terms TEXT');
echo "Done: " . $c->error;
?>
