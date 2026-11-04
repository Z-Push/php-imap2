<?php
// Ports Roundcube's IMAP classes into php-imap2's PHPFUI\Imap2\Roundcube namespace.
// Usage: php port-roundcube.php <roundcube program/lib/Roundcube dir> <php-imap2 src/Imap2/Roundcube dir> <version>
[, $from, $to, $version] = $argv;

$classMap = [
	'rcube_imap_generic' => 'ImapClient',
	'rcube_message_header_sorter' => 'MessageHeaderSorter',
	'rcube_message_header' => 'MessageHeader',
	'rcube_result_index' => 'ResultIndex',
	'rcube_result_thread' => 'ResultThread',
	'rcube_charset' => 'Charset',
	'rcube_mime' => 'Mime',
	'rcube_utils' => 'Utils',
];

function fail(string $message) : never { fwrite(STDERR, "port: $message\n"); exit(1); }

/** Replaces $search with $replace exactly $count times, or stops */
function replaceExact(string $subject, string $search, string $replace, int $count, string $what) : string
	{
	$found = substr_count($subject, $search);
	if ($found !== $count) fail("$what: expected $count match(es) of " . json_encode($search) . ", found $found");
	return str_replace($search, $replace, $subject);
	}

function transform(string $code, array $classMap, string $version) : string
	{
	// class names written as strings (callbacks) must become ::class so they resolve inside the namespace
	foreach ($classMap as $old => $new)
		{
		$code = preg_replace("/(['\"])$old\\1/", "$new::class", $code);
		}
	foreach ($classMap as $old => $new)
		{
		$code = preg_replace("/\\b$old\\b/", $new, $code);
		}
	$code = preg_replace('/\brcube_message_part\b/', '\stdClass', $code);
	$code = preg_replace('/\bRCUBE_CHARSET\b/', 'IMAP2_CHARSET', $code);

	// namespace after the header comment
	if (! preg_match('#^<\?php\s*/\*.*?\*/\s*#s', $code, $m)) fail('header comment not found');
	$note = "\n// Ported from Roundcube {$version} (program/lib/Roundcube). Changes: namespace, class names,\n"
		. "// no Roundcube application object, and php-imap2's COMMAND_RAW_LASTLINE. See tools/port-roundcube.php.\n\n";
	return rtrim($m[0]) . "\n\nnamespace PHPFUI\\Imap2\\Roundcube;\n" . $note . substr($code, strlen($m[0]));
	}

/** Source of the named methods (with docblocks), by line ranges from the tokenizer */
function methods(string $file, array $names) : string
	{
	$lines = file($file);
	$t = token_get_all(implode('', $lines));
	$found = [];
	for ($i = 0, $n = count($t); $i < $n; $i++)
		{
		if (! is_array($t[$i]) || T_FUNCTION !== $t[$i][0]) continue;
		$j = $i + 1;
		while (is_array($t[$j]) && T_WHITESPACE === $t[$j][0]) $j++;
		if (! is_array($t[$j]) || T_STRING !== $t[$j][0] || ! in_array($t[$j][1], $names, true)) continue;
		$k = $i - 1;
		$start = $t[$i][2];
		while ($k >= 0 && is_array($t[$k]) && in_array($t[$k][0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_DOC_COMMENT], true))
			{
			if (T_WHITESPACE !== $t[$k][0]) $start = $t[$k][2];
			$k--;
			}
		$depth = 0;
		for ($m = $j; $m < $n; $m++)
			{
			$s = is_array($t[$m]) ? $t[$m][1] : $t[$m];
			if ('{' === $s || (is_array($t[$m]) && in_array($t[$m][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $depth++;
			elseif ('}' === $s && 0 === --$depth) break;
			}
		$end = is_array($t[$m]) ? $t[$m][2] : null;
		if (null === $end) { $end = 1; for ($q = 0; $q <= $m; $q++) $end += substr_count(is_array($t[$q]) ? $t[$q][1] : $t[$q], "\n"); }
		$found[$t[$j][1]] = implode('', array_slice($lines, $start - 1, $end - $start + 1));
		}
	foreach ($names as $name) if (! isset($found[$name])) fail("method $name not found in $file");
	return "\n" . implode("\n", array_map(fn($name) => $found[$name], $names));
	}

/** A class holding only some of a Roundcube class's methods */
function subsetClass(string $file, string $class, array $names, string $members = '') : string
	{
	$code = file_get_contents($file);
	// "use" lines (e.g. IPLib in rcube_utils) only serve methods that are not copied
	if (! preg_match('#^<\?php\s*(?:use [^;]+;\s*)*(/\*.*?\*/\s*)(?:/\*\*.*?\*/\s*)?class \w+\s*\{#s', $code, $m)) fail("class header not found in $file");
	return "<?php\n\n" . $m[1] . "class $class\n{\n" . $members . methods($file, $names) . "}\n";
	}

$out = [];

// --- ImapClient: the whole client, plus php-imap2's raw last line support
$c = file_get_contents("$from/rcube_imap_generic.php");
$c = replaceExact($c, "    public const COMMAND_ANONYMIZED = 8;\n",
	"    public const COMMAND_ANONYMIZED = 8;\n    // php-imap2: last line with its response code kept, e.g. \"[ALREADYEXISTS] Mailbox exists\"\n    public const COMMAND_RAW_LASTLINE = 16;\n", 1, 'COMMAND_ANONYMIZED');
$c = replaceExact($c, "    protected \$debug_handler = false;\n",
	"    protected \$debug_handler = false;\n    protected \$rawLastLine = '';\n", 1, 'debug_handler');
$c = replaceExact($c,
	"        \$result = \$this->execute('CREATE', \$args, self::COMMAND_NORESPONSE);\n\n        return \$result == self::ERROR_OK;\n",
	"        \$result = \$this->execute('CREATE', \$args, self::COMMAND_RAW_LASTLINE);\n\n        \$this->rawLastLine = (string) \$result[1];\n\n        return \$result[0] == self::ERROR_OK;\n", 1, 'createFolder');
$c = replaceExact($c,
	"        return \$noresp ? \$code : [\$code, \$response];\n    }\n",
	"        // return raw last line only (without command tag and result, response code kept)\n        if (\$line && (\$options & self::COMMAND_RAW_LASTLINE)) {\n            \$response = preg_replace(\"/^{\$tag} (OK|NO|BAD|BYE|PREAUTH)?\\\\s*/i\", '', trim(\$line));\n        }\n\n        return \$noresp ? \$code : [\$code, \$response];\n    }\n\n    /**\n     * Returns the last line of the most recent CREATE, with its response code (php-imap2)\n     *\n     * @return string\n     */\n    public function getRawLastLine()\n    {\n        return \$this->rawLastLine;\n    }\n", 1, 'execute return');
// imap_gc() clears the client's cache
$c = replaceExact($c, "    protected function clear_mailbox_cache()\n", "    public function clear_mailbox_cache()\n", 1, 'clear_mailbox_cache');
$out['ImapClient.php'] = transform($c, $classMap, $version);

// --- whole classes
foreach (['rcube_message_header' => 'MessageHeader', 'rcube_result_thread' => 'ResultThread'] as $file => $class)
	{
	$out["$class.php"] = transform(file_get_contents("$from/$file.php"), $classMap, $version);
	}

// --- ResultIndex: php-imap2 fix. array_filter() dropped an empty SEARCH response, so the next unilateral
// response (the "* FLAGS (...)" Dovecot sends for a new keyword) was returned as the search result.
$c = file_get_contents("$from/rcube_result_index.php");
$c = replaceExact($c, "        \$data = array_filter(\$data);\n\n        if (empty(\$data)) {\n",
	"        // php-imap2: keep an empty SEARCH response, so a later unilateral response is not taken as the result\n        if (empty(\$data)) {\n", 1, 'ResultIndex array_filter');
$out['ResultIndex.php'] = transform($c, $classMap, $version);

// --- Charset: whole class, without Roundcube's configuration and user language
$c = file_get_contents("$from/rcube_charset.php");
$c = replaceExact($c, "        \$charsets[] = (string) rcube::get_instance()->config->get('default_charset');\n", '', 1, 'detect default_charset');
$c = replaceExact($c, "        if (empty(\$language)) {\n            \$rcube = rcube::get_instance();\n            \$language = \$rcube->get_user_language();\n        }\n", '', 1, 'detect language');
$out['Charset.php'] = transform($c, $classMap, $version);

// --- Mime: the header decoding part of rcube_mime
$c = subsetClass("$from/rcube_mime.php", 'rcube_mime', ['__construct', 'get_charset', 'decode_header', 'decode_mime_string', 'decode', 'explode_header_string', 'fix_email'], "    private static \$default_charset;\n");
$c = replaceExact($c, "        if (\$charset = rcube::get_instance()->config->get('default_charset')) {\n            return \$charset;\n        }\n\n", '', 1, 'get_charset');
$out['Mime.php'] = transform($c, $classMap, $version);

// --- Utils: the date, string and connection helpers rcube_imap_generic uses
$c = subsetClass("$from/rcube_utils.php", 'rcube_utils', ['explode_quoted_string', 'strtotime', 'clean_datestr', 'remove_subject_prefix', 'proxy_protocol_header']);
// remove_subject_prefix() reads the reply and forward prefixes from Roundcube's configuration: use its defaults
$c = replaceExact($c, "        \$config = rcmail::get_instance()->config;\n",
	"        // php-imap2: no Roundcube configuration, so every setting takes Roundcube's default\n        \$config = new class {\n            public function get(\$name, \$default = null)\n            {\n                return \$default;\n            }\n        };\n", 1, 'remove_subject_prefix config');
$c = preg_replace('/\}\s*$/', "\n    /**\n     * Returns the client IP address (php-imap2: without Roundcube's proxy_whitelist)\n     *\n     * @return ?string\n     */\n    public static function remote_addr()\n    {\n        return \$_SERVER['REMOTE_ADDR'] ?? null;\n    }\n}\n", $c);
$out['Utils.php'] = transform($c, $classMap, $version);

// anything left that only Roundcube's application provides
foreach ($out as $name => $code)
	{
	if (preg_match_all('/\b(rcube(::|_[a-z_]+)|rcmail\b)/', $code, $m)) fail("$name still references " . implode(', ', array_unique($m[0])));
	file_put_contents("$to/$name", $code);
	echo "wrote $name (" . substr_count($code, "\n") . " lines)\n";
	}
