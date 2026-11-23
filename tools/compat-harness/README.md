# ext-imap compatibility harness

Checks how closely php-imap2 matches PHP's `imap` extension (ext-imap), using the calls Z-Push's IMAP backend makes. Each check runs once with ext-imap and once with the library's `imap_*` functions, against the same Dovecot mailbox restored from a snapshot each time, and the results are compared call by call.

The unit tests in `Tests/Unit` and the server tests in `Tests/Server` cover the fixes on their own. This harness is the wider comparison with ext-imap.

## Setup (Ubuntu/Debian, as root)

ext-imap is needed for the reference runs, so use PHP 8.3 or older (or install imap from PECL).

```sh
apt install dovecot-imapd php8.3-cli php8.3-imap php8.3-mbstring
useradd --system zptest
install -d -o zptest -g zptest /srv/zpmail
mkdir -p /run/zpdc
```

Dovecot runs on 127.0.0.1:10143 with no TLS, user `test`, password `secret` (`dovecot/dovecot.conf`). The mail store is `/srv/zpmail/test`.

## Run

```sh
sudo PHP=php8.3 ./runall.sh /path/to/php-imap2/src my-branch
```

`runall.sh` runs every check below with ext-imap and with the library (ext-imap turned off for the library runs), and prints one line per check with the count of identical results. ext-imap's results are cached in `results/native/`; delete that directory to run them again. Each check's `results/my-branch/<check>.diff` lists the calls whose result differs, ext-imap's result first.

`run.sh` runs only `harness.php`, the main comparison.

To load the library the way Z-Push does, through `src/backend/imap/php-imap2/php-imap2.php`, use wrapper mode with any of the scripts, with ext-imap disabled (for example `PHP_INI_SCAN_DIR` pointing at a copy of the ini directory without the imap ini):

```sh
php harness/harness.php wrapper /path/to/z-push/src/backend/imap/php-imap2/php-imap2.php
```

Restore `fixture/mailbox.tar` into `/srv/zpmail` before each run of a single script.

## Files

| Path | What it is |
|---|---|
| `runall.sh` | Runs every check and compares the results. |
| `run.sh` | Runs `harness.php` only. |
| `harness/load.php` | Shared loader for the modes `native`, `lib <src>` and `wrapper <php-imap2.php>`. |
| `harness/harness.php` | The main comparison: opening and reopening, folder listing and changes, status, overview, fetching, flags, search, append, move and expunge. |
| `harness/zpush-extra.php` | The issues that matter most to Z-Push: overview, multi-flag clear and set, search date formats, subscribe and unsubscribe, UTF-7 folder names. |
| `harness/search.php` | The search date formats Z-Push sends, and an overview of a message with no To header. |
| `harness/criteria.php` | How `imap_search()` reads its criteria: keys, dates, quoting, unknown keys and malformed input. |
| `harness/sort.php` | `imap_sort()` keys, reverse order and search criteria. |
| `harness/folders.php` | Subscribe, rename and delete, including bare folder names. |
| `harness/addresses.php` | `imap_rfc822_write_address()` and the From/To values in overviews and headers. |
| `harness/gaps.php` | `imap_mime_header_decode()`, `imap_utf7_*`, append flags and dates, `imap_status()` fields and reopen errors. |
| `harness/moveflags.php` | Replays MoveMessage's clear-then-set flag handling for every combination of source flags. |
| `harness/diff.php` | Compares two result files. |
| `harness/seed.php` | Builds the test mailbox with ext-imap. Only needed to rebuild `fixture/mailbox.tar`. |
| `fixture/mailbox.tar` | Maildir snapshot of `/srv/zpmail/test`. |
| `dovecot/dovecot.conf` | Test server config. |

## Test mailbox

The INBOX holds 8 messages with fixed internal dates (1 to 8 Oct 2026):

1. Plain ASCII.
2. UTF-8 Q-encoded subject and B-encoded From.
3. multipart/alternative with a folded subject and several To/Cc.
4. A mixed message with an inline image and a PDF attachment with an RFC 2231 filename.
5. No Date header.
6. latin1 8bit.
7. A forward with a message/rfc822 part and Reply-To.
8. ISO-2022-JP.

Flags include `\Seen`, `\Flagged`, `\Answered` and `$Forwarded`. Other folders: Drafts (one `\Draft` message), Sent, Trash, Archive, Archive/2024 (one message), "Folder with space", and two non-ASCII folders (Ünïcödé, Документы).

## Known differences that remain

- `imap_mail_move()` uses a real MOVE, so the moved message's flags can differ from ext-imap's copy-and-delete.
- `\Recent` is per session, so it does not match between the two runs.
- `SORTFROM` sorts by display name.
- Some error messages are worded differently from ext-imap's.
- `imap_check()` leaves `/notls` out of the mailbox name.

Notes: ext-imap prints "SECURITY PROBLEM: insecure server advertised AUTH=PLAIN" because the test server has no TLS. Searching for `DRAFT` is not supported by ext-imap; the "Unknown search criterion" errors in the ext-imap run come from the checks, not the library.
