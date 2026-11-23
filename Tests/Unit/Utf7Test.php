<?php

namespace Tests\Unit;

/**
 * imap_utf7_encode() and imap_utf7_decode() work on bytes like ext-imap: each byte is one character (ISO-8859-1), and
 * "&" becomes "&-". Before, input was treated as UTF-8 and invalid input was not rejected. Expected values are what
 * ext-imap returns.
 */
class Utf7Test extends \PHPUnit\Framework\TestCase
	{
	public function testEncode() : void
		{
		$this->assertEquals('&3A-n&7w-c&9g-d&6Q-', \imap_utf7_encode(\mb_convert_encoding('Ünïcödé', 'ISO-8859-1', 'UTF-8')));
		$this->assertEquals('plain', \imap_utf7_encode('plain'));
		$this->assertEquals('a&-b', \imap_utf7_encode('a&b'));
		$this->assertEquals('', \imap_utf7_encode(''));
		}

	public function testDecode() : void
		{
		$this->assertEquals("\x00\xe9", \imap_utf7_decode('&AOk-'));
		$this->assertEquals('plain', \imap_utf7_decode('plain'));
		$this->assertEquals('a&b', \imap_utf7_decode('a&-b'));
		}

	public function testInvalidDecodeIsFalse() : void
		{
		$this->assertFalse(\imap_utf7_decode('&bad'));
		$this->assertFalse(\imap_utf7_decode('&AOk'));
		}
	}
