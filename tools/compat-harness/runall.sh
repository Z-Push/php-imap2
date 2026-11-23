#!/bin/bash
# Run every check against ext-imap and against a php-imap2 src/ directory, and compare them.
# Usage: sudo ./runall.sh /path/to/php-imap2/src [label]
# Needs a PHP with ext-imap (PHP 8.3 or older, or PECL imap), Dovecot, and the setup in README.md.
# Set PHP to choose the PHP binary (default: php). ext-imap results are cached in results/native/.
set -u
HERE=$(cd "$(dirname "$0")" && pwd)
SRC=${1:?path to php-imap2 src/}
LABEL=${2:-$(date +%Y-%m-%d)}
PHP=${PHP:-php}
OUT="$HERE/results/$LABEL"
NAT="$HERE/results/native"
MAIL=/srv/zpmail
mkdir -p "$OUT" "$NAT"

# PHP ini directory without ext-imap, for the library runs
NOIMAP=$(mktemp -d)
INIDIR=$($PHP -r 'echo PHP_CONFIG_FILE_SCAN_DIR;')
cp "$INIDIR"/*.ini "$NOIMAP"/
rm -f "$NOIMAP"/*imap*
trap 'rm -rf "$NOIMAP"' EXIT

pgrep -x dovecot >/dev/null || dovecot -c "$HERE/dovecot/dovecot.conf" </dev/null >/dev/null 2>&1

reset_mailbox() {
	rm -rf "$MAIL/test"
	tar -C "$MAIL" -xf "$HERE/fixture/mailbox.tar"
	chown -R zptest:zptest "$MAIL"
}

run() { # script mode outfile
	reset_mailbox
	if [ "$2" = native ]; then
		$PHP -d error_reporting=0 "$HERE/harness/$1.php" native > "$3" 2>/dev/null
	else
		PHP_INI_SCAN_DIR=$NOIMAP $PHP -d error_reporting=0 "$HERE/harness/$1.php" lib "$SRC" > "$3" 2>/dev/null
	fi
}

for s in harness zpush-extra search folders addresses criteria sort gaps moveflags; do
	[ -s "$NAT/$s.out" ] || run $s native "$NAT/$s.out"
	run $s lib "$OUT/$s.out"
done
reset_mailbox

echo "== $LABEL"
for s in harness zpush-extra search folders addresses criteria sort gaps; do
	$PHP "$HERE/harness/diff.php" "$NAT/$s.out" "$OUT/$s.out" > "$OUT/$s.diff" 2>&1
	printf '%-12s %s\n' "$s" "$(tail -1 "$OUT/$s.diff")"
done
printf '%-12s %s of %s rows WRONG\n' moveflags "$(grep -c WRONG "$OUT/moveflags.out")" "$(grep -c 'want' "$OUT/moveflags.out")"
