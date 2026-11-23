<?php

namespace Tests\Unit;

/**
 * ImapClient::search() refuses criteria that contain CR, LF or NUL, and sends nothing to the server for them
 */
class ImapClientSearchTest extends \PHPUnit\Framework\TestCase
	{
	protected function tearDown() : void
		{
		FakeImapStream::$written = '';
		}

	private function client() : \PHPFUI\Imap2\Roundcube\ImapClient
		{
		if (! \in_array('fakeimap', \stream_get_wrappers()))
			{
			\stream_wrapper_register('fakeimap', FakeImapStream::class);
			}

		$client = new \PHPFUI\Imap2\Roundcube\ImapClient();
		$set = static fn(string $name, mixed $value) => (new \ReflectionProperty($client, $name))->setValue($client, $value);
		$set('fp', \fopen('fakeimap://server', 'r+'));
		// a server without ESEARCH, whose capabilities are already known
		$set('capability', ['IMAP4REV1']);
		$set('capability_read', true);
		$set('prefs', ['timeout' => 0]);
		// already selected, so search() goes straight to its SEARCH command
		$client->selected = 'INBOX';
		$client->data = ['EXISTS' => 2];

		return $client;
		}

	/**
	 * @return array<string, array{string}>
	 */
	public static function lineBreaks() : array
		{
		return [
			'CRLF' => ["SEEN\r\nFLAGGED"],
			'LF' => ["SEEN\nFLAGGED"],
			'CR' => ["SEEN\rFLAGGED"],
			'NUL' => ["SEEN\0FLAGGED"],
		];
		}

	/**
	 * @dataProvider lineBreaks
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('lineBreaks')]
	public function testLineBreakIsRejected(string $criteria) : void
		{
		$client = $this->client();
		$result = $client->search('INBOX', $criteria);

		$this->assertTrue($result->is_error());
		$this->assertSame(\PHPFUI\Imap2\Roundcube\ImapClient::ERROR_COMMAND, $client->errornum);
		$this->assertSame('', FakeImapStream::$written);
		}

	public function testCriteriaIsSent() : void
		{
		$result = $this->client()->search('INBOX', 'SEEN FLAGGED');

		$this->assertStringContainsString(' SEARCH SEEN FLAGGED', FakeImapStream::$written);
		$this->assertEquals(['2', '4'], $result->get());
		}
	}

/**
 * Stream that records what the client writes and answers each command with a SEARCH result
 */
class FakeImapStream
	{
	public static string $written = '';

	/** @var resource|null */
	public $context;

	private string $reply = '';

	public function stream_open(string $path, string $mode, int $options, ?string &$opened) : bool
		{
		return true;
		}

	public function stream_write(string $data) : int
		{
		self::$written .= $data;

		if (\preg_match('/^(\S+) /', $data, $match))
			{
			$this->reply .= "* SEARCH 2 4\r\n{$match[1]} OK SEARCH completed\r\n";
			}

		return \strlen($data);
		}

	public function stream_read(int $count) : string
		{
		$line = \substr($this->reply, 0, $count);
		$this->reply = (string)\substr($this->reply, \strlen($line));

		return $line;
		}

	public function stream_eof() : bool
		{
		return '' === $this->reply;
		}

	public function stream_flush() : bool
		{
		return true;
		}

	public function stream_set_option(int $option, int $arg1, ?int $arg2) : bool
		{
		return false;
		}

	/**
	 * @return array<string, int>
	 */
	public function stream_stat() : array
		{
		return [];
		}
	}
