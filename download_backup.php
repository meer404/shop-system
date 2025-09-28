<?php
// db_full_backup.php
// Full dump: tables (DDL + data), view stand-ins, views, triggers, routines, events.
// Streams .sql to the browser.

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/inc/config.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($conn) && ($conn instanceof PDO)) { $pdo = $conn; }
  else { http_response_code(500); die('Database connection missing: $pdo (PDO) is not defined.'); }
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Helpers */
function db_name(PDO $pdo){ $r=$pdo->query('SELECT DATABASE() AS db')->fetch(PDO::FETCH_ASSOC); return $r?$r['db']:'database'; }
function server_version(PDO $pdo){ $r=$pdo->query('SELECT VERSION() AS v')->fetch(PDO::FETCH_ASSOC); return $r?$r['v']:'unknown'; }
function now_r(){ return date('r'); }
function now_slug(){ return date('Ymd_His'); }
function out($s){ echo $s; if (ob_get_level()>0){ @ob_flush(); } flush(); }

/* File meta */
$db = db_name($pdo);
$ver = server_version($pdo);
$filename = $db . '_full_backup_' . now_slug() . '.sql';

/* Headers */
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/* Prelude (phpMyAdmin-like) */
out("-- Manual SQL Dump\n");
out("-- Host: localhost\n");
out("-- Generation Time: ".now_r()."\n");
out("-- Server version: ".$ver."\n");
out("-- PHP Version: ".PHP_VERSION."\n\n");
out("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
out("START TRANSACTION;\n");
out("SET time_zone = \"+00:00\";\n\n");
out("/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
out("/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
out("/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
out("/*!40101 SET NAMES utf8mb4 */;\n\n");
out("--\n-- Database: `{$db}`\n--\n\n");
out("SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n");
out("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n");
out("SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0;\n\n");

/* Discover objects */
$tables = [];
$views  = [];
$st = $pdo->query("SHOW FULL TABLES");
while ($r = $st->fetch(PDO::FETCH_NUM)) {
  if (strcasecmp($r[1],'BASE TABLE')===0) $tables[]=$r[0]; else $views[]=$r[0];
}

/* Triggers list (names) */
$triggers = [];
try {
  $st = $pdo->query("SHOW TRIGGERS");
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) $triggers[] = $r['Trigger'];
} catch (Throwable $e){}

/* Routines */
$procedures=[]; $functions=[];
try { $s=$pdo->prepare("SHOW PROCEDURE STATUS WHERE Db=?"); $s->execute([$db]); $procedures=array_map(fn($r)=>$r['Name'],$s->fetchAll(PDO::FETCH_ASSOC)); } catch(Throwable $e){}
try { $s=$pdo->prepare("SHOW FUNCTION STATUS WHERE Db=?");  $s->execute([$db]); $functions =array_map(fn($r)=>$r['Name'],$s->fetchAll(PDO::FETCH_ASSOC)); } catch(Throwable $e){}

/* Events */
$events=[];
try { $s=$pdo->query("SHOW EVENTS FROM `{$db}`"); while($r=$s->fetch(PDO::FETCH_ASSOC)) $events[]=$r['Name']; } catch(Throwable $e){}

/* 1) DDL for BASE TABLES */
foreach ($tables as $t) {
  out("-- --------------------------------------------------------\n\n");
  out("-- Table structure for table `{$t}`\n\n");
  $row = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
  out("DROP TABLE IF EXISTS `{$t}`;\n");
  out($row['Create Table'].";\n\n");
}

/* 2) DATA for BASE TABLES (extended inserts) */
function dump_table_data(PDO $pdo, string $t){
  $stmtCols = $pdo->query("SELECT * FROM `{$t}` LIMIT 0");
  $colCount = $stmtCols->columnCount();
  $cols = [];
  for ($i=0;$i<$colCount;$i++){ $m=$stmtCols->getColumnMeta($i); $cols[]='`'.($m['name'] ?? "col_{$i}").'`'; }
  $colList = '('.implode(',', $cols).')';

  $stmt = $pdo->query("SELECT * FROM `{$t}`", PDO::FETCH_NUM);
  $batch=200; $chunk=[]; $rowCount=0;
  while ($row = $stmt->fetch()) {
    $vals=[];
    foreach($row as $v){
      if (is_null($v)) $vals[]="NULL";
      elseif (is_int($v)||is_float($v)||is_numeric($v)) $vals[]=(string)$v;
      else { $v=str_replace(["\r\n","\n","\r"], "\n", $v); $vals[]=$pdo->quote($v); }
    }
    $chunk[]='('.implode(',', $vals).')';
    $rowCount++;
    if (count($chunk)>=$batch){ out("INSERT INTO `{$t}` {$colList} VALUES\n".implode(",\n",$chunk).";\n"); $chunk=[]; }
  }
  if (!empty($chunk)) out("INSERT INTO `{$t}` {$colList} VALUES\n".implode(",\n",$chunk).";\n");
  if ($rowCount>0) out("\n");
}
foreach ($tables as $t){ out("-- Dumping data for table `{$t}`\n\n"); dump_table_data($pdo,$t); }

/* 3) VIEW STAND-INS (phpMyAdmin style) */
if (!empty($views)) {
  out("-- --------------------------------------------------------\n\n");
  out("-- Stand-in structure for views (temporary tables)\n-- (See below for the actual views)\n\n");
  foreach ($views as $v) {
    // Derive column list + rough types from the view result metadata
    $colsDefs = [];
    try {
      $rs = $pdo->query("SELECT * FROM `{$v}` LIMIT 0");
      $colCount = $rs->columnCount();
      for ($i=0;$i<$colCount;$i++){
        $m = $rs->getColumnMeta($i);
        $name = $m['name'] ?? ("col_{$i}");
        $native = strtolower($m['native_type'] ?? '');
        // Simple mapping to look nice (fallback varchar)
        if (strpos($native,'int')!==false)        $type = 'int(11)';
        elseif (in_array($native,['newdecimal','decimal','numeric','double','float'])) $type = 'decimal(32,2)';
        elseif (strpos($native,'time')!==false)   $type = 'datetime';
        elseif ($native==='blob')                 $type = 'blob';
        else                                      $type = 'varchar(255)';
        $colsDefs[] = "`{$name}` {$type}";
      }
    } catch (Throwable $e) {
      // Fallback: generic single column if no permission
      $colsDefs = ["`col1` varchar(255)"];
    }
    out("CREATE TABLE `{$v}` (\n".implode(",\n", $colsDefs)."\n);\n\n");
  }
}

/* 4) REAL VIEWS (DROP stand-in then CREATE VIEW) */
if (!empty($views)) {
  foreach ($views as $v) {
    out("-- --------------------------------------------------------\n\n");
    out("-- Structure for view `{$v}`\n\n");
    out("DROP TABLE IF EXISTS `{$v}`;\n");   // drop stand-in
    out("DROP VIEW IF EXISTS `{$v}`;\n\n");  // match phpMyAdmin pattern
    try {
      $row = $pdo->query("SHOW CREATE VIEW `{$v}`")->fetch(PDO::FETCH_ASSOC);
      out($row['Create View'].";\n\n");
    } catch (Throwable $e) {
      out("-- Skipped view `{$v}` (SHOW CREATE VIEW failed): ".$e->getMessage()."\n\n");
    }
  }
}

/* 5) TRIGGERS */
if (!empty($triggers)) {
  out("-- --------------------------------------------------------\n");
  out("-- Triggers\n\n");
  out("DELIMITER $$\n");
  foreach ($triggers as $tr) {
    try {
      $row = $pdo->query("SHOW CREATE TRIGGER `{$tr}`")->fetch(PDO::FETCH_ASSOC);
      out("DROP TRIGGER IF EXISTS `{$tr}`$$\n");
      out($row['SQL Original Statement']."$$\n\n"); // includes CREATE TRIGGER ...
    } catch (Throwable $e) {
      out("-- Skipped trigger `{$tr}`: ".$e->getMessage()."\n\n");
    }
  }
  out("DELIMITER ;\n\n");
}

/* 6) ROUTINES (Procedures & Functions) */
if (!empty($procedures) || !empty($functions)) {
  out("-- --------------------------------------------------------\n");
  out("-- Stored routines\n\n");
  out("DELIMITER $$\n");
  foreach ($procedures as $p){
    try { $row=$pdo->query("SHOW CREATE PROCEDURE `{$p}`")->fetch(PDO::FETCH_ASSOC);
      out("DROP PROCEDURE IF EXISTS `{$p}`$$\n".$row['Create Procedure']."$$\n\n");
    } catch(Throwable $e){ out("-- Skipped procedure `{$p}`: ".$e->getMessage()."\n\n"); }
  }
  foreach ($functions as $f){
    try { $row=$pdo->query("SHOW CREATE FUNCTION `{$f}`")->fetch(PDO::FETCH_ASSOC);
      out("DROP FUNCTION IF EXISTS `{$f}`$$\n".$row['Create Function']."$$\n\n");
    } catch(Throwable $e){ out("-- Skipped function `{$f}`: ".$e->getMessage()."\n\n"); }
  }
  out("DELIMITER ;\n\n");
}

/* 7) EVENTS */
if (!empty($events)) {
  out("-- --------------------------------------------------------\n");
  out("-- Events\n\n");
  out("DELIMITER $$\n");
  foreach ($events as $ev){
    try { $row=$pdo->query("SHOW CREATE EVENT `{$ev}`")->fetch(PDO::FETCH_ASSOC);
      out("DROP EVENT IF EXISTS `{$ev}`$$\n".$row['Create Event']."$$\n\n");
    } catch(Throwable $e){ out("-- Skipped event `{$ev}`: ".$e->getMessage()."\n\n"); }
  }
  out("DELIMITER ;\n\n");
}

/* Ending */
out("SET SQL_NOTES=@OLD_SQL_NOTES;\n");
out("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;\n");
out("SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;\n\n");
out("COMMIT;\n\n");
out("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
out("/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
out("/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
exit;
