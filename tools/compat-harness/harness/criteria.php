<?php
// How imap_search() reads its criteria string: dates, quoting, unknown keys, odd input.
// Usage: php criteria.php native | lib <php-imap2/src>
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
imap_append($m, $s . 'INBOX', "From: q@example.com\r\nTo: test@example.com\r\nSubject: Quote test\r\nDate: Mon, 12 Oct 2026 10:00:00 +0000\r\n\r\nHe said \"hello world\" and left C:\\temp\\file behind.\r\n");
imap_close($m);
$m = imap_open($s . 'INBOX', 'test', 'secret');
$r = [];
$q = [
	'ALL', 'all', '  SEEN   ', 'UNSEEN FLAGGED', 'ANSWERED', 'UNANSWERED', 'DELETED', 'UNDELETED', 'FLAGGED', 'UNFLAGGED',
	'RECENT', 'NEW', 'OLD', 'KEYWORD $Forwarded', 'UNKEYWORD $Forwarded', 'KEYWORD Nope',
	'DRAFT', 'UNDRAFT', 'OR SEEN FLAGGED', 'NOT SEEN', 'LARGER 100', 'UID 1:3', 'HEADER X-Foo bar', '',
	'SINCE 06-Oct-2026', 'SINCE 6-Oct-2026', 'SINCE "06-Oct-2026"', 'SINCE "2026-10-06"', 'SINCE 2026-10-06', 'SINCE "6 Oct 2026"',
	'BEFORE "2026-10-04"', 'ON 03-Oct-2026', 'SINCE 32-Oct-2026', 'SINCE tomorrow', 'SINCE "2026-13-01"', 'SINCE',
	'SUBJECT plain', 'SUBJECT "Plain ASCII"', 'SUBJECT "café"', 'FROM alice', 'TO test@example.com', 'CC carol', 'BCC nobody', 'BODY hello', 'TEXT "Motion"',
	'TEXT "hello world"', 'TEXT "C:\\temp"', 'TEXT "say \\"hello"', 'TEXT ""', 'TEXT "unterminated', 'TEXT', 'SUBJECT plain extra',
	"SEEN\r\nFLAGGED",
	"TEXT \"a\r\nb\"",
];
foreach ($q as $c) {
	$r['uid ' . json_encode($c, JSON_UNESCAPED_UNICODE)] = imap_search($m, $c, SE_UID, 'UTF-8');
}
$r['seq UNSEEN'] = imap_search($m, 'UNSEEN');
$r['nocharset SUBJECT "café"'] = imap_search($m, 'SUBJECT "café"', SE_UID);
echo json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
