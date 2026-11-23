<?php

namespace Tests\Unit;

/**
 * Pieces the Roundcube 1.7.5 port relies on that php-imap2 provides itself.
 */
class RoundcubePortTest extends \PHPUnit\Framework\TestCase
	{
	public function testArrayFirst() : void
		{
		$this->assertEquals('a', \array_first(['x' => 'a', 'y' => 'b']));
		$this->assertNull(\array_first([]));
		}

	/**
	 * Roundcube reads the prefixes from its configuration, which php-imap2 does not have; its defaults are used
	 */
	public function testRemoveSubjectPrefix() : void
		{
		$this->assertEquals('Hello', \PHPFUI\Imap2\Roundcube\Utils::remove_subject_prefix('Re: Fwd: Hello'));
		$this->assertEquals('Fwd: Hello', \PHPFUI\Imap2\Roundcube\Utils::remove_subject_prefix('Re: Fwd: Hello', 'reply'));
		$this->assertEquals('Hello', \PHPFUI\Imap2\Roundcube\Utils::remove_subject_prefix('Fw: Hello', 'forward'));
		}

	/**
	 * Message::clearCache() calls this; Roundcube 1.7 made it protected
	 */
	public function testClearMailboxCacheIsPublic() : void
		{
		$this->assertTrue((new \ReflectionMethod(\PHPFUI\Imap2\Roundcube\ImapClient::class, 'clear_mailbox_cache'))->isPublic());
		}
	}
