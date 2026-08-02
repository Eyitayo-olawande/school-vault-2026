<?php
/**
 * Production DB dump endpoint.
 * Upload sync/ to the production root alongside application/.
 * Access: GET /sync/db_dump.php?token=<value-in-.token>
 *
 * Security: token file is outside web root of this script but inside sync/.
 * The .htaccess in this folder blocks direct access to .token.
 */

// ── Auth ────────────────────────────────────────────────────────────────────
$token_file = __DIR__ . '/.token';
if (!file_exists($token_file)) {
    http_response_code(500);
    die('Token file missing. Create sync/.token with your secret.');
}
$expected = trim(file_get_contents($token_file));
$provided  = isset($_GET['token']) ? $_GET['token'] : '';
if (!hash_equals($expected, $provided)) {
    http_response_code(403);
    die('Unauthorized.');
}

// ── Load DB credentials from CI config ──────────────────────────────────────
// Bootstrap just enough for CI's database.php to parse
define('BASEPATH', 'dummy');   // stops CI from bailing on the define check
$db = [];
$active_group  = 'default';
$query_builder = true;
require_once dirname(__DIR__) . '/application/config/database.php';
$cfg = $db['default'];

// ── Connect ──────────────────────────────────────────────────────────────────
$mysqli = new mysqli(
    $cfg['hostname'],
    $cfg['username'],
    $cfg['password'],
    $cfg['database']
);
if ($mysqli->connect_error) {
    http_response_code(500);
    die('DB connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset($cfg['char_set'] ?: 'utf8mb4');
$db_name = $cfg['database'];

// ── Stream headers ────────────────────────────────────────────────────────────
$filename = 'prod_dump_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Dump-DB: ' . $db_name);

// Use output buffering + gzip if the client supports it
$use_gzip = strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false;
if ($use_gzip) {
    header('Content-Encoding: gzip');
    ob_start('ob_gzhandler');
}

// ── Helper ────────────────────────────────────────────────────────────────────
function emit(string $sql): void { echo $sql; }

function escape_value($val, mysqli $db): string {
    if ($val === null) return 'NULL';
    if (is_numeric($val) && !preg_match('/^0\d/', $val)) return $val;
    return "'" . $db->real_escape_string($val) . "'";
}

// ── Dump header ───────────────────────────────────────────────────────────────
emit("-- SchoolVault production dump\n");
emit("-- Database: {$db_name}\n");
emit("-- Generated: " . date('Y-m-d H:i:s') . " UTC\n\n");
emit("SET FOREIGN_KEY_CHECKS=0;\n");
emit("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
emit("SET NAMES utf8mb4;\n\n");

// ── Tables ────────────────────────────────────────────────────────────────────
$tables_res = $mysqli->query('SHOW TABLES');
$tables = [];
while ($row = $tables_res->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    // Structure
    $create_res = $mysqli->query("SHOW CREATE TABLE `{$table}`");
    $create_row = $create_res->fetch_assoc();
    $create_sql = $create_row['Create Table'] ?? $create_row['Create View'] ?? '';

    emit("\n-- Table: `{$table}`\n");
    emit("DROP TABLE IF EXISTS `{$table}`;\n");
    emit($create_sql . ";\n\n");

    // Count rows
    $count_res = $mysqli->query("SELECT COUNT(*) FROM `{$table}`");
    $count     = (int) $count_res->fetch_row()[0];
    if ($count === 0) continue;

    // Data in batches of 500
    $offset      = 0;
    $batch_size  = 500;
    while ($offset < $count) {
        $data_res = $mysqli->query("SELECT * FROM `{$table}` LIMIT {$batch_size} OFFSET {$offset}");
        $rows = [];
        while ($data_row = $data_res->fetch_row()) {
            $values = array_map(fn($v) => escape_value($v, $mysqli), $data_row);
            $rows[] = '(' . implode(',', $values) . ')';
        }
        if (!empty($rows)) {
            emit("INSERT INTO `{$table}` VALUES\n" . implode(",\n", $rows) . ";\n");
        }
        $offset += $batch_size;
    }
    emit("\n");

    // Flush periodically so PHP doesn't time out on huge tables
    if (function_exists('ob_flush')) { ob_flush(); }
    flush();
}

emit("\nSET FOREIGN_KEY_CHECKS=1;\n");
emit("-- End of dump\n");

if ($use_gzip && ob_get_level()) {
    ob_end_flush();
}
exit;
