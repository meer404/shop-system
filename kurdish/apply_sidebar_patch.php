<?php
/**
 * apply_sidebar_patch.php
 * 
 * Drop this file in the SAME FOLDER as your app, then open it in the browser.
 * It will:
 *  - Back up all files to _backup_sidebar_YYYYmmdd_His/
 *  - Replace styles.css, header.php, footer.php with sidebar versions (if present here)
 *  - Wrap every page (except printable receipts) with header/footer if not already wrapped
 *  - Strip old <html>/<head>/<body>/<header>/<main> wrappers from pages it wraps
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = __DIR__;
$date = date('Ymd_His');
$backup = $root . '/_backup_sidebar_' . $date;
@mkdir($backup);

function rr($p){ return realpath($p) ?: $p; }

function backup_all($root, $backup){
  $dh = opendir($root);
  while(($f = readdir($dh)) !== false){
    if($f[0]==='.') continue;
    $src = $root . '/' . $f;
    $dst = $backup . '/' . $f;
    if(is_dir($src)) continue; // only top-level files
    copy($src, $dst);
  }
  closedir($dh);
}

function readf($p){ return file_exists($p) ? file_get_contents($p) : ''; }
function writef($p, $c){ file_put_contents($p, $c); }

function strip_wrappers($s){
  $s = preg_replace('~(?is)<!DOCTYPE.*?>~', '', $s);
  $s = preg_replace('~(?is)</?html[^>]*>~', '', $s);
  $s = preg_replace('~(?is)<head[^>]*>.*?</head>~', '', $s);
  $s = preg_replace('~(?is)</?body[^>]*>~', '', $s);
  // remove ONLY a top header nav block if present
  $s = preg_replace('~(?is)^\s*<header[^>]*>.*?</header>~', '', $s);
  $s = preg_replace('~(?is)</?main[^>]*>~', '', $s);
  return trim($s);
}

// Step 1: backup
backup_all($root, $backup);

// Step 2: replace styles/header/footer if patch versions exist (you uploaded them with this script)
$patch_styles = __DIR__ . '/styles.css';
$patch_header = __DIR__ . '/header.php';
$patch_footer = __DIR__ . '/footer.php';

if(file_exists($patch_styles)) copy($patch_styles, $root . '/styles.css');
if(file_exists($patch_header)) copy($patch_header, $root . '/header.php');
if(file_exists($patch_footer)) copy($patch_footer, $root . '/footer.php');

// Step 3: wrap pages
$standalone = ['sale_receipt.php','purchase_receipt.php'];
$wrapped = [];
$skipped = [];

$dh = opendir($root);
while(($f = readdir($dh)) !== false){
  if($f[0]==='.') continue;
  if(strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'php') continue;
  if(in_array($f, ['header.php','footer.php','apply_sidebar_patch.php'])){ $skipped[] = "$f (framework)"; continue; }
  if(in_array($f, $standalone)){ $skipped[] = "$f (printable standalone)"; continue; }

  $path = $root . '/' . $f;
  $code = readf($path);
  if(!$code){ $skipped[] = "$f (empty)"; continue; }

  $l = strtolower($code);
  if(strpos($l, 'header.php') !== false && strpos($l, 'require_once') !== false){
    $skipped[] = "$f (already includes header.php)";
    continue;
  }

  // Strip old wrappers and add our header/footer
  $inner = strip_wrappers($code);
  $new = "<?php $". "page = '{$f}'; require_once __DIR__ . '/header.php'; ?>\n{$inner}\n<?php require_once __DIR__ . '/footer.php'; ?>\n";
  writef($path, $new);
  $wrapped[] = $f;
}
closedir($dh);

// Output report
header('Content-Type: text/plain; charset=utf-8');
echo "Backup folder: " . basename($backup) . "\\n";
echo "Wrapped files: " . (count($wrapped) ? implode(', ', $wrapped) : 'None') . "\\n";
echo "Skipped files: " . (count($skipped) ? implode('; ', $skipped) : 'None') . "\\n";
echo "\\nDone. Refresh your app (Ctrl+F5).\\n";
