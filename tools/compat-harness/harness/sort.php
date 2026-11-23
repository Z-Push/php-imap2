<?php
$src = $argv[2] ?? null;
if (($argv[1] ?? '') === 'lib') { spl_autoload_register(function ($c) use ($src) { foreach (['PHPFUI\\Imap2\\' => "$src/Imap2/", 'IMAP\\' => "$src/IMAP/"] as $p => $d) if (strncmp($c, $p, strlen($p)) === 0) { $f = $d . str_replace('\\', '/', substr($c, strlen($p))) . '.php'; if (is_file($f)) { require $f; return; } } }); require "$src/Imap2/IMAPStubs.php"; }
set_error_handler(fn() => true);
$s = '{127.0.0.1:10143/imap/notls/norsh}'; $m = imap_open($s . 'INBOX', 'test', 'secret'); $r = [];
foreach ([[SORTDATE, false, null], [SORTDATE, true, null], [SORTARRIVAL, false, 'UNSEEN'], [SORTSUBJECT, false, null], [SORTFROM, true, null], [SORTSIZE, false, 'SINCE "2026-10-05"'], [SORTDATE, false, 'DRAFT']] as [$c, $rev, $q])
  foreach ([0, SE_UID] as $fl) $r["sort $c rev=" . (int)$rev . " flags=$fl q=" . json_encode($q)] = imap_sort($m, $c, $rev, $fl, $q, 'UTF-8');
echo json_encode($r), "\n";
