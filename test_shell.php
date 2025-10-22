<?php
echo "Test de shell_exec:\n";
$output = shell_exec('echo test');
echo "Output: '" . $output . "'\n";
echo "Is null: " . ($output === null ? 'yes' : 'no') . "\n";
