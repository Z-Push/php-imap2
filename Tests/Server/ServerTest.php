<?php

namespace Tests\Server;

/**
 * Regression tests that need an IMAP server. They are skipped unless these are set:
 *
 *   IMAP2_TEST_SERVER    for example {127.0.0.1:143/imap/notls}
 *   IMAP2_TEST_USER
 *   IMAP2_TEST_PASSWORD
 *
 * Each test works in its own new folder, which is deleted afterwards. The tests only call imap_* functions, so they
 * can also be run with ext-imap loaded, to check the expected values against it.
 */
class ServerTest extends \PHPUnit\Framework\TestCase
	{
	private string $folder = '';

	private \IMAP\Connection | false $imap = false;

	private string $server = '';

	protected function setUp() : void
		{
		$this->server = (string)\getenv('IMAP2_TEST_SERVER');

		if ('' === $this->server)
			{
			$this->markTestSkipped('IMAP2_TEST_SERVER is not set');
			}

		$this->imap = \imap_open($this->server . 'INBOX', (string)\getenv('IMAP2_TEST_USER'), (string)\getenv('IMAP2_TEST_PASSWORD'));
		$this->assertNotFalse($this->imap, 'imap_open: ' . \imap_last_error());

		$this->folder = 'imap2test' . \bin2hex(\random_bytes(4));
		$this->assertTrue(\imap_createmailbox($this->imap, $this->server . $this->folder));
		$this->assertTrue(\imap_reopen($this->imap, $this->server . $this->folder));
		}

	protected function tearDown() : void
		{
		if ($this->imap)
			{
			\imap_reopen($this->imap, $this->server . 'INBOX');

			foreach (\imap_list($this->imap, $this->server, $this->folder . '*') ?: [] as $name)
				{
				\imap_unsubscribe($this->imap, $name);
				\imap_deletemailbox($this->imap, $name);
				}
			\imap_close($this->imap);
			}
		// expected failures leave errors and alerts behind, which would otherwise be reported as notices
		\imap_errors();
		\imap_alerts();
		}

	private function append(string $message, string $flags = '', ?string $date = null) : int
		{
		$this->assertTrue(\imap_append($this->imap, $this->server . $this->folder, \str_replace("\n", "\r\n", $message), $flags, $date));

		return \imap_num_msg($this->imap);
		}

	private function names(array $list) : array
		{
		$names = \array_map(fn($name) => \substr($name, \strlen($this->server)), $list);
		\sort($names);

		return $names;
		}

	/**
	 * Before, a message without a To header made imap_fetch_overview() fail, and To and From held every address
	 */
	public function testOverviewWithoutTo() : void
		{
		$this->append("From: Sender <sender@example.com>\nSubject: No To\n\nbody\n");
		$this->append("From: A <a@example.com>, B <b@example.com>\nTo: X <x@example.com>, y@example.com\nSubject: Two\n\nbody\n");

		$overview = \imap_fetch_overview($this->imap, '1:2');
		$this->assertCount(2, $overview);
		$this->assertEquals('No To', $overview[0]->subject);
		$this->assertEquals('Sender <sender@example.com>', $overview[0]->from);
		$this->assertObjectNotHasProperty('to', $overview[0]);
		$this->assertEquals('A <a@example.com>', $overview[1]->from);
		$this->assertEquals('X <x@example.com>', $overview[1]->to);
		$this->assertEquals(2, $overview[1]->msgno);
		}

	/**
	 * Before, a UID that does not exist gave an entry full of empty values
	 */
	public function testOverviewOfMissingUid() : void
		{
		$this->append("From: a@example.com\nSubject: x\n\nbody\n");
		$this->assertEquals([], \imap_fetch_overview($this->imap, '999', FT_UID));
		}

	/**
	 * Before, an attached message (whose parameter list is NIL) threw a TypeError
	 */
	public function testFetchStructureWithAttachedMessage() : void
		{
		$this->append(<<<'EOT'
			From: a@example.com
			Subject: Forward
			MIME-Version: 1.0
			Content-Type: multipart/mixed; boundary="b1"

			--b1
			Content-Type: text/plain; charset=UTF-8

			see attached
			--b1
			Content-Type: message/rfc822

			From: b@example.com
			Subject: Inner

			inner body
			--b1--

			EOT);

		$structure = \imap_fetchstructure($this->imap, 1);
		$this->assertEquals(TYPEMULTIPART, $structure->type);
		$this->assertCount(2, $structure->parts);
		$this->assertEquals(TYPEMESSAGE, $structure->parts[1]->type);
		$this->assertEquals('RFC822', $structure->parts[1]->subtype);
		}

	/**
	 * Before, only the first flag was set or cleared
	 */
	public function testSetAndClearSeveralFlags() : void
		{
		$this->append("From: a@example.com\nSubject: flags\n\nbody\n");

		$flags = fn() : array => \array_map(fn($o) => [$o->seen, $o->flagged, $o->answered], \imap_fetch_overview($this->imap, '1'));

		$this->assertTrue(\imap_setflag_full($this->imap, '1', '\\Seen \\Flagged \\Answered'));
		$this->assertEquals([[1, 1, 1]], $flags());

		$this->assertTrue(\imap_clearflag_full($this->imap, '1', '\\Seen \\Flagged'));
		$this->assertEquals([[0, 0, 1]], $flags());
		}

	/**
	 * Before, imap_append() ignored its flags and internal date
	 */
	public function testAppendFlagsAndDate() : void
		{
		$this->append("From: a@example.com\nSubject: dated\n\nbody\n", '\\Seen \\Draft', '02-Jan-2024 10:00:00 +0000');

		$overview = \imap_fetch_overview($this->imap, '1')[0];
		$this->assertEquals([1, 1, 1704189600], [$overview->seen, $overview->draft, $overview->udate]);
		}

	/**
	 * Before, every field was returned, and with different values
	 */
	public function testStatusReturnsRequestedFields() : void
		{
		$this->append("From: a@example.com\nSubject: one\n\nbody\n");
		$this->append("From: a@example.com\nSubject: two\n\nbody\n", '\\Seen');

		$status = \imap_status($this->imap, $this->server . $this->folder, SA_MESSAGES);
		$this->assertEquals(['flags' => SA_MESSAGES, 'messages' => 2], (array)$status);

		$status = \imap_status($this->imap, $this->server . $this->folder, SA_UNSEEN | SA_UIDNEXT);
		$this->assertEquals(['flags', 'unseen', 'uidnext'], \array_keys((array)$status));
		$this->assertEquals(1, $status->unseen);
		$this->assertEquals(3, $status->uidnext);
		}

	/**
	 * Before, subscribe and unsubscribe sent the name with the server prefix, and the subscribed list was every folder
	 */
	public function testSubscribe() : void
		{
		$name = $this->server . $this->folder;
		$this->assertTrue(\imap_subscribe($this->imap, $name));
		$this->assertEquals([$this->folder], $this->names(\imap_lsub($this->imap, $this->server, $this->folder . '*')));

		$subscribed = \imap_getsubscribed($this->imap, $this->server, $this->folder . '*');
		$this->assertCount(1, $subscribed);
		$this->assertEquals($name, $subscribed[0]->name);

		$this->assertTrue(\imap_unsubscribe($this->imap, $name));
		$this->assertFalse(\imap_lsub($this->imap, $this->server, $this->folder . '*'));
		}

	/**
	 * Before, create, rename and delete kept the server prefix in the folder name
	 */
	public function testCreateRenameDelete() : void
		{
		$this->assertTrue(\imap_createmailbox($this->imap, $this->server . $this->folder . '/Child'));
		$this->assertTrue(\imap_renamemailbox($this->imap, $this->server . $this->folder . '/Child', $this->server . $this->folder . '/Renamed'));
		$this->assertEquals([$this->folder, $this->folder . '/Renamed'], $this->names(\imap_list($this->imap, $this->server, $this->folder . '*')));

		$this->assertTrue(\imap_deletemailbox($this->imap, $this->server . $this->folder . '/Renamed'));
		$this->assertEquals([$this->folder], $this->names(\imap_list($this->imap, $this->server, $this->folder . '*')));
		}

	/**
	 * Before, ext-imap's date formats failed, criteria with a line break were not rejected, and a search with no result
	 * gave an empty array
	 */
	public function testSearch() : void
		{
		$this->append("From: a@example.com\nSubject: Old one\n\nbody\n", '', '02-Jan-2024 10:00:00 +0000');
		$this->append("From: b@example.com\nSubject: New one\n\nMotion body\n", '\\Seen', '06-Oct-2026 10:00:00 +0000');

		$this->assertEquals([2], \imap_search($this->imap, 'SINCE 06-Oct-2026'));
		$this->assertEquals([2], \imap_search($this->imap, 'SINCE "2026-10-06"'));
		$this->assertEquals([2], \imap_search($this->imap, 'SINCE "6 Oct 2026"'));
		$this->assertEquals([1], \imap_search($this->imap, 'BEFORE "2024-01-03" UNSEEN'));
		$this->assertEquals([2], \imap_search($this->imap, 'TEXT "Motion"'));
		$this->assertEquals([1, 2], \imap_search($this->imap, 'SUBJECT one'));
		$this->assertFalse(\imap_search($this->imap, 'SUBJECT missing'));
		$this->assertFalse(\imap_search($this->imap, 'KEYWORD $NotSet'));

		$this->assertFalse(\imap_search($this->imap, "SEEN\r\nUNSEEN"));
		$this->assertEquals([1], \imap_search($this->imap, 'UNSEEN'));
		}

	/**
	 * Before, imap_sort() ignored its sort key and search criteria
	 */
	public function testSort() : void
		{
		$this->append("From: a@example.com\nSubject: Bravo\nDate: Tue, 2 Jan 2024 10:00:00 +0000\n\nbody\n");
		$this->append("From: b@example.com\nSubject: Alpha\nDate: Mon, 1 Jan 2024 10:00:00 +0000\n\nbody\n");
		$this->append("From: c@example.com\nSubject: Charlie\nDate: Wed, 3 Jan 2024 10:00:00 +0000\n\nbody\n", '\\Seen');

		$this->assertEquals([2, 1, 3], \imap_sort($this->imap, SORTSUBJECT, false));
		$this->assertEquals([3, 1, 2], \imap_sort($this->imap, SORTSUBJECT, true));
		$this->assertEquals([2, 1, 3], \imap_sort($this->imap, SORTDATE, false));
		$this->assertEquals([2, 1], \imap_sort($this->imap, SORTDATE, false, 0, 'UNSEEN'));
		$this->assertEquals([\imap_uid($this->imap, 3)], \imap_sort($this->imap, SORTARRIVAL, false, SE_UID, 'SEEN'));
		}

	/**
	 * Before, a message number that does not exist gave a UID
	 */
	public function testUidOfMissingMessage() : void
		{
		$this->append("From: a@example.com\nSubject: x\n\nbody\n");
		$this->assertIsInt(\imap_uid($this->imap, 1));
		$this->assertEquals(1, \imap_msgno($this->imap, \imap_uid($this->imap, 1)));

		try
			{
			$this->assertFalse(@\imap_uid($this->imap, 5));
			}
		catch (\ValueError)
			{
			// ext-imap throws for a message number out of range
			$this->addToAssertionCount(1);
			}
		}

	/**
	 * Before, reopening a folder that does not exist kept no error. ext-imap still returns true, so only the error is checked
	 */
	public function testReopenMissingFolder() : void
		{
		@\imap_reopen($this->imap, $this->server . $this->folder . '/Missing');
		$this->assertStringStartsWith("Mailbox doesn't exist: ", (string)\imap_last_error());
		}
	}
