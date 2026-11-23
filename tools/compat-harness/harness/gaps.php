<?php
// imap_mime_header_decode, imap_utf7_*, imap_append flags and date, imap_status flags.
// Usage: php gaps.php native | lib <php-imap2/src>
$src = $argv[2] ?? null;
if (($argv[1] ?? '') === 'lib') {
	spl_autoload_register(function ($c) use ($src) {
		foreach (['PHPFUI\\Imap2\\' => "$src/Imap2/", 'IMAP\\' => "$src/IMAP/"] as $p => $d) {
			if (strncmp($c, $p, strlen($p)) === 0) {
				$f = $d . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
				if (is_file($f)) { require $f; return; }
			}
		}
	});
	require "$src/Imap2/IMAPStubs.php";
}
set_error_handler(fn() => true);
$r = [];
$hex = fn($v) => is_array($v) ? array_map(fn($o) => [$o->charset, bin2hex($o->text)], $v) : $v;
foreach ([
	'plain text', '', '=?UTF-8?Q?R=C3=A9union?=', '=?UTF-8?Q?R=C3=A9union?= plain =?ISO-8859-1?B?R/xu?=',
	'=?UTF-8?B?w4lsb2RpZQ==?= =?UTF-8?B?IE3DvGxsZXI=?=', "=?UTF-8?Q?a?=\r\n =?UTF-8?Q?b?=", '=?utf-8?q?under_score?=',
	'pre =?UTF-8?Q?x?= post', '=?UTF-8?Q?broken', '=?UTF-8?X?eA==?=', '=?ISO-2022-JP?B?GyRCOzNFRBsoQg==?=', 'a =?bad', '=??Q?x?=',
	'=?UTF-8?Q?=ZZ?=', '=?UTF-8?B?!!!?=',
] as $h) $r['mime ' . json_encode($h)] = $hex(imap_mime_header_decode($h));
foreach (['Ünïcödé', 'plain', 'a&b', ''] as $s) $r['utf7_encode ' . json_encode($s)] = imap_utf7_encode(mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'));
foreach (['&AOk-', 'plain', 'a&-b', '&bad', '&AOk'] as $s) { $d = imap_utf7_decode($s); $r['utf7_decode ' . json_encode($s)] = false === $d ? false : bin2hex($d); }

$s = '{127.0.0.1:10143/imap/notls/norsh}';
$m = imap_open($s . 'INBOX', 'test', 'secret');
$r['append flags'] = imap_append($m, $s . 'Drafts', "From: a@example.com\r\nSubject: Gap test\r\n\r\nx\r\n", "\\Seen \\Draft");
$r['append date'] = imap_append($m, $s . 'Drafts', "From: a@example.com\r\nSubject: Dated\r\n\r\nx\r\n", null, '02-Jan-2024 10:00:00 +0000');
imap_reopen($m, $s . 'Drafts');
foreach (imap_fetch_overview($m, '1:*') ?: [] as $o) $r['drafts ' . $o->subject] = [$o->seen, $o->draft, $o->udate];
foreach ([SA_ALL, SA_MESSAGES, SA_UNSEEN | SA_UIDNEXT] as $f) $r["status flags=$f"] = (array) imap_status($m, $s . 'INBOX', $f);
$r['reopen missing'] = imap_reopen($m, $s . 'Missing');
$r['reopen missing last_error'] = imap_last_error();
echo json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
