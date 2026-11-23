<?php

namespace Tests\Unit;

/**
 * An empty SEARCH or ESEARCH result followed by an unrelated untagged response. Before, the unrelated response was
 * read as the result, so an empty search returned bogus message numbers.
 */
class ResultIndexTest extends \PHPUnit\Framework\TestCase
	{
	public function testEmptySearchFollowedByFlags() : void
		{
		$result = new \PHPFUI\Imap2\Roundcube\ResultIndex('INBOX', "* SEARCH\r\n* FLAGS (\\Seen \\Answered)\r\n");
		$this->assertFalse($result->is_error());
		$this->assertEquals(0, $result->count());
		$this->assertEquals([], $result->get());
		}

	public function testEmptyEsearchFollowedByExists() : void
		{
		$result = new \PHPFUI\Imap2\Roundcube\ResultIndex('INBOX', "* ESEARCH (TAG \"A1\") UID\r\n* 3 EXISTS\r\n");
		$this->assertFalse($result->is_error());
		$this->assertEquals(0, $result->count());
		}

	public function testSearchResult() : void
		{
		$result = new \PHPFUI\Imap2\Roundcube\ResultIndex('INBOX', "* SEARCH 2 4 7\r\n");
		$this->assertEquals(['2', '4', '7'], $result->get());
		}

	public function testEsearchResult() : void
		{
		$result = new \PHPFUI\Imap2\Roundcube\ResultIndex('INBOX', "* ESEARCH (TAG \"A1\") UID ALL 2:4,7\r\n");
		$this->assertEquals(['2', '3', '4', '7'], $result->get());
		}

	public function testNoSearchResponseIsAnError() : void
		{
		$this->assertTrue((new \PHPFUI\Imap2\Roundcube\ResultIndex('INBOX', "* 3 EXISTS\r\n"))->is_error());
		}
	}
