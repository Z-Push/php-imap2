<?php

namespace Tests\Unit;

/**
 * Address formatting matches ext-imap (c-client). Before, display names were MIME-encoded (already encoded ones a
 * second time), names with specials such as "," were not quoted, an empty host was dropped and null arguments threw
 * a TypeError. Expected values are what ext-imap returns.
 */
class WriteAddressTest extends \PHPUnit\Framework\TestCase
	{
	/**
	 * @return array<string, array{?string, ?string, ?string, string}>
	 */
	public static function addresses() : array
		{
		return [
			'plain name' => ['john', 'example.com', 'John Doe', 'John Doe <john@example.com>'],
			'dot in name is quoted' => ['john', 'example.com', 'J. Doe', '"J. Doe" <john@example.com>'],
			'comma in name is quoted' => ['john', 'example.com', 'Doe, John', '"Doe, John" <john@example.com>'],
			'quotes in name are escaped' => ['john', 'example.com', 'Say "hi"', '"Say \\"hi\\"" <john@example.com>'],
			'backslash in name is escaped' => ['john', 'example.com', 'back\\slash', '"back\\\\slash" <john@example.com>'],
			'UTF-8 name is left as it is' => ['john', 'example.com', 'Jörg Müller', 'Jörg Müller <john@example.com>'],
			'encoded name is not encoded again' => ['john', 'example.com', '=?UTF-8?B?w4lsb2RpZSBNw7xsbGVy?=', '=?UTF-8?B?w4lsb2RpZSBNw7xsbGVy?= <john@example.com>'],
			'parentheses in name' => ['john', 'example.com', '(paren)', '"(paren)" <john@example.com>'],
			'at sign in name' => ['john', 'example.com', 'a@b', '"a@b" <john@example.com>'],
			'empty name' => ['john', 'example.com', '', 'john@example.com'],
			'null name' => ['john', 'example.com', null, 'john@example.com'],
			'space in mailbox is quoted' => ['john doe', 'example.com', '', '"john doe"@example.com'],
			'dot in mailbox is not quoted' => ['john.doe', 'example.com', '', 'john.doe@example.com'],
			'quote in mailbox' => ['john"q', 'example.com', '', '"john\\"q"@example.com'],
			'empty host' => ['john', '', '', 'john@""'],
			'null host' => ['john', null, null, 'john@""'],
			'empty mailbox and host' => ['', '', '', '""@""'],
			'all null' => [null, null, null, '""@""'],
			'empty mailbox' => ['', 'example.com', '', '""@example.com'],
			'full address as mailbox' => ['john@example.com', 'other.com', '', '"john@example.com"@other.com'],
		];
		}

	/**
	 * @dataProvider addresses
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('addresses')]
	public function testWriteAddress(?string $mailbox, ?string $host, ?string $personal, string $expected) : void
		{
		$this->assertEquals($expected, \PHPFUI\Imap2\Functions::writeAddress($mailbox, $host, $personal));
		$this->assertEquals($expected, \imap_rfc822_write_address($mailbox, $host, $personal));
		}

	public function testEnvelopeList() : void
		{
		// envelope entries are [personal, route, mailbox, host]; NIL arrives as null
		$list = [['John Doe', null, 'john', 'example.com'], [null, null, 'jane', 'example.com']];
		$this->assertEquals('John Doe <john@example.com>, jane@example.com', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope($list));
		}

	public function testEnvelopeGroups() : void
		{
		// "Team: x@example.com, y@example.com;" and "undisclosed-recipients:;"
		$team = [[null, null, 'Team', null], [null, null, 'x', 'example.com'], [null, null, 'y', 'example.com'], [null, null, null, null]];
		$this->assertEquals('Team: x@example.com, y@example.com;', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope($team));
		$this->assertEquals('undisclosed-recipients: ;', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope([[null, null, 'undisclosed-recipients', null], [null, null, null, null]]));
		}

	public function testEnvelopeEmptyOrInvalid() : void
		{
		$this->assertEquals('', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope(null));
		$this->assertEquals('', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope([]));
		$this->assertEquals('', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope(['not an entry']));
		}

	/**
	 * imap_fetch_overview() gives only the first address of From and To, as ext-imap does
	 */
	public function testFirstAddress() : void
		{
		$list = [['A', null, 'a', 'example.com'], ['B', null, 'b', 'example.com']];
		$this->assertEquals([['A', null, 'a', 'example.com']], \PHPFUI\Imap2\Functions::firstAddress($list));
		$this->assertEquals('A <a@example.com>', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope(\PHPFUI\Imap2\Functions::firstAddress($list)));

		// a message without the header has NIL here, and a broken server could send a string
		$this->assertNull(\PHPFUI\Imap2\Functions::firstAddress(null));
		$this->assertNull(\PHPFUI\Imap2\Functions::firstAddress([]));
		$this->assertNull(\PHPFUI\Imap2\Functions::firstAddress('test@example.com'));
		$this->assertEquals('', \PHPFUI\Imap2\Functions::writeAddressFromEnvelope(\PHPFUI\Imap2\Functions::firstAddress(null)));
		}
	}
