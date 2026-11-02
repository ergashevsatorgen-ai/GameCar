<?php
// RenterCode Drive serveri. Shu faylni hostingga yuklang: /Barchabotlar/rentercode/rc_server.php
// MUHIM: ADMIN_KEY ni o'zingizning maxfiy kalitingizga almashtiring!
const ADMIN_KEY = 'ozgartiring-maxfiy-kalit-123';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
$dir = __DIR__ . '/rc_data';
if (!is_dir($dir)) { mkdir($dir, 0755, true); mkdir($dir . '/up', 0755, true); }
function jread($f, $d) { return is_file($f) ? (json_decode(file_get_contents($f), true) ?: $d) : $d; }
function jwrite($f, $v) { file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX); }
function out($v) { echo json_encode($v, JSON_UNESCAPED_UNICODE); exit; }
function isadmin() { $k = $_SERVER['HTTP_X_ADMIN_KEY'] ?? ''; return $k !== '' && hash_equals(ADMIN_KEY, $k); }
function needadmin() { if (!isadmin()) out(['err' => 'auth']); }
$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$adsf = "$dir/ads.json"; $cfgf = "$dir/cfg.json"; $stf = "$dir/stats.json"; $grf = "$dir/grants.json";

if ($a === 'ads') {
  $ads = jread($adsf, []);
  foreach ($ads as &$x) $x['url'] = $base . '/rc_data/up/' . $x['file'];
  out(['ads' => array_values($ads), 'cfg' => jread($cfgf, ['int' => 90, 'onMap' => true, 'on' => true])]);
}
if ($a === 'ad_add') {
  needadmin();
  $f = $_FILES['file'] ?? null; $dur = (int)($_POST['dur'] ?? 5);
  if (!$f || $f['error'] !== 0) out(['err' => 'file']);
  if ($dur < 3 || $dur > 60) out(['err' => 'dur']);
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp','gif','mp4','webm'])) out(['err' => 'ext']);
  if ($f['size'] > 60 * 1024 * 1024) out(['err' => 'size']);
  $id = 'a' . time() . rand(100, 999); $file = "$id.$ext";
  move_uploaded_file($f['tmp_name'], "$dir/up/$file");
  $ads = jread($adsf, []);
  $ads[] = ['id' => $id, 'file' => $file, 'type' => in_array($ext, ['mp4','webm']) ? 'video' : 'image', 'dur' => $dur, 'name' => substr($_POST['name'] ?? '', 0, 40)];
  jwrite($adsf, $ads); out(['ok' => true]);
}
if ($a === 'ad_del') {
  needadmin(); $ads = jread($adsf, []); $keep = [];
  foreach ($ads as $x) { if ($x['id'] === ($in['id'] ?? '')) @unlink("$dir/up/" . $x['file']); else $keep[] = $x; }
  jwrite($adsf, $keep); out(['ok' => true]);
}
if ($a === 'cfg') {
  needadmin();
  jwrite($cfgf, ['int' => max(20, (int)($in['int'] ?? 90)), 'onMap' => !empty($in['onMap']), 'on' => !empty($in['on'])]);
  out(['ok' => true]);
}
if ($a === 'grant') {
  needadmin(); $n = strtolower(trim($in['name'] ?? '')); $amt = (int)($in['amount'] ?? 0);
  if ($n === '' || $amt === 0) out(['err' => 'bad']);
  $g = jread($grf, []); $g[$n] = ($g[$n] ?? 0) + $amt; jwrite($grf, $g); out(['ok' => true]);
}
if ($a === 'claim') {
  $n = strtolower(trim($in['name'] ?? '')); $g = jread($grf, []); $amt = $g[$n] ?? 0;
  if ($amt) { unset($g[$n]); jwrite($grf, $g); }
  out(['amount' => $amt]);
}
if ($a === 'event') {
  $s = jread($stf, ['plays' => 0, 'ad_views' => 0, 'players' => []]);
  $t = $in['type'] ?? ''; $n = substr(trim($in['name'] ?? ''), 0, 16);
  if ($t === 'play') $s['plays']++; if ($t === 'ad_view') $s['ad_views']++;
  if ($n !== '') $s['players'][strtolower($n)] = time();
  jwrite($stf, $s); out(['ok' => true]);
}
if ($a === 'stats') {
  needadmin(); $s = jread($stf, ['plays' => 0, 'ad_views' => 0, 'players' => []]);
  out(['plays' => $s['plays'], 'ad_views' => $s['ad_views'], 'players' => count($s['players'])]);
}
if ($a === 'room') {
  $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($in['code'] ?? ''));
  if (strlen($code) < 3) out(['err' => 'code']);
  $id = substr(preg_replace('/[^a-zA-Z0-9]/', '', $in['id'] ?? ''), 0, 16);
  if ($id === '') out(['err' => 'id']);
  $f = "$dir/room_$code.json"; $now = time();
  $r = jread($f, ['players' => [], 'host' => '', 'started' => false, 'map' => 0]);
  foreach ($r['players'] as $pid => $p) if ($now - $p['t'] > 8) unset($r['players'][$pid]);
  if (!$r['players']) { $r['started'] = false; $r['host'] = ''; }
  if (!isset($r['players'][$id]) && count($r['players']) >= 5) out(['err' => 'full']);
  if ($r['host'] === '' || !isset($r['players'][$r['host']])) $r['host'] = $id;
  $r['players'][$id] = ['name' => substr($in['name'] ?? 'Oyinchi', 0, 16), 'x' => (float)($in['x'] ?? 0), 'z' => (float)($in['z'] ?? 0), 'd' => (float)($in['d'] ?? 0), 'c' => preg_match('/^#[0-9a-fA-F]{6}$/', $in['c'] ?? '') ? $in['c'] : '#e53935', 't' => $now];
  if ($id === $r['host']) { if (isset($in['map'])) $r['map'] = (int)$in['map']; if (!empty($in['start'])) $r['started'] = true; }
  jwrite($f, $r);
  $pl = []; foreach ($r['players'] as $pid => $p) $pl[] = ['id' => $pid] + $p;
  out(['players' => $pl, 'host' => $r['host'], 'started' => $r['started'], 'map' => $r['map']]);
}
out(['err' => 'unknown']);
