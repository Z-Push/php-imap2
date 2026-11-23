<?php
// Compares how addresses come out of imap_fetch_overview() and imap_rfc822_write_address().
// Usage: php addresses.php native | lib <php-imap2/src>
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
$s = '{127.0.0.1:10143/imap/notls/norsh}';
$m = imap_open($s . 'INBOX', 'test', 'secret');
$r = [];

// 1. imap_rfc822_write_address() on its own
$w = [
	['john', 'example.com', 'John Doe'],
	['john', 'example.com', 'J. Doe'],
	['john', 'example.com', 'Doe, John'],
	['john', 'example.com', 'Say "hi"'],
	['john', 'example.com', 'back\\slash'],
	['john', 'example.com', 'Jörg Müller'],
	['john', 'example.com', '=?UTF-8?B?w4lsb2RpZSBNw7xsbGVy?='],
	['john', 'example.com', '(paren)'],
	['john', 'example.com', 'a@b'],
	['john', 'example.com', ''],
	['john doe', 'example.com', ''],
	['john.doe', 'example.com', ''],
	['john"q', 'example.com', ''],
	['john', '', ''],
	['undisclosed-recipients', '', ''],
	['', '', ''],
	['', 'example.com', ''],
	['john@example.com', 'other.com', ''],
];
foreach ($w as $i => $args) {
	try { $r["write $i " . json_encode($args, JSON_UNESCAPED_UNICODE)] = imap_rfc822_write_address(...$args); }
	catch (\Throwable $e) { $r["write $i " . json_encode($args, JSON_UNESCAPED_UNICODE)] = 'EXCEPTION ' . get_class($e); }
}

// 2. imap_fetch_overview() from/to for crafted headers
$box = 'AddrTest';
@imap_createmailbox($m, $s . $box);
$headers = [
	"From: John Doe <john@example.com>\r\nTo: a@example.com, B <b@example.com>",
	"From: \"Doe, John\" <john@example.com>\r\nTo: \"Say \\\"hi\\\"\" <q@example.com>",
	"From: =?UTF-8?B?w4lsb2RpZSBNw7xsbGVy?= <elodie@example.com>\r\nTo: =?ISO-8859-1?Q?J=F6rg?= <joerg@example.com>",
	"From: plain@example.com\r\nTo: undisclosed-recipients:;",
	"From: J. Doe <jd@example.com>\r\nTo: Team: x@example.com, y@example.com;",
	"From: nohost\r\nTo: (comment) c@example.com",
	"From: <angle@example.com>\r\nCc: only-cc@example.com",
];
foreach ($headers as $h) imap_append($m, $s . $box, $h . "\r\nSubject: addr\r\n\r\nx\r\n");
imap_reopen($m, $s . $box);
try {
	foreach (imap_fetch_overview($m, '1:*') ?: [] as $o) {
		$r["overview {$o->msgno}"] = ['from' => $o->from ?? '<unset>', 'to' => $o->to ?? '<unset>'];
	}
} catch (\Throwable $e) { $r['overview'] = 'EXCEPTION ' . get_class($e) . ': ' . $e->getMessage(); }
imap_reopen($m, $s . 'INBOX');
@imap_deletemailbox($m, $s . $box);
echo json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
