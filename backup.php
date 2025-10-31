<?php
require_once 'config.php';

if (!is_admin()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied. Admins only.';
    exit;
}

// Tables managed by the application
$tables = [
    'users',
    'vehicles',
    'items',
    'mileage_logs',
    'maintenance_logs',
    'fuel_logs',
    'electricity_meters',
    'electricity_logs',
    'cooking_gas_logs'
];

function escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function generate_sql_dump(mysqli $link, array $tables): string {
    $dump = "-- My Data Tracker SQL Backup\n";
    $dump .= "-- Generated: " . date('c') . "\n";
    $dump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $t = escape_identifier($table);
        // Schema
        $res = mysqli_query($link, "SHOW CREATE TABLE {$t}");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $create = $row['Create Table'] ?? '';
            mysqli_free_result($res);
            if ($create) {
                $dump .= "DROP TABLE IF EXISTS {$t};\n{$create};\n\n";
            }
        }

        // Data
        $res = mysqli_query($link, "SELECT * FROM {$t}");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $cols = array_map(fn($c) => escape_identifier($c), array_keys($row));
                $vals = array_map(function ($v) use ($link) {
                    if ($v === null) return 'NULL';
                    return "'" . mysqli_real_escape_string($link, (string)$v) . "'";
                }, array_values($row));
                $dump .= "INSERT INTO {$t} (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
            mysqli_free_result($res);
            $dump .= "\n";
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

$sql = generate_sql_dump($link, $tables);
$fname = 'datatracker_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $fname);
header('Content-Length: ' . strlen($sql));
echo $sql;
exit;
?>