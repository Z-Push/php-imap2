<?php

namespace Tests\Unit;

/**
 * imap_search() criteria are read the way ext-imap reads them and rebuilt as a safe IMAP SEARCH program.
 * Before, the string was passed to the server unchanged: ext-imap's date formats failed, and line breaks were not
 * rejected.
 */
class SearchCriteriaTest extends \PHPUnit\Framework\TestCase
	{
	/**
	 * @return array<string, array{string, string}>
	 */
	public static function validCriteria() : array
		{
		return [
			'empty is ALL' => ['', 'ALL'],
			'spaces only is ALL' => ['   ', 'ALL'],
			'flag keys' => ['SEEN UNANSWERED', 'SEEN UNANSWERED'],
			'keys are case insensitive' => ['unseen flagged', 'UNSEEN FLAGGED'],
			'extra spaces' => ['  SEEN   FLAGGED ', 'SEEN FLAGGED'],
			'IMAP date' => ['SINCE 06-Oct-2026', 'SINCE 6-Oct-2026'],
			'IMAP date, one digit day' => ['SINCE 6-Oct-2026', 'SINCE 6-Oct-2026'],
			'quoted IMAP date' => ['SINCE "06-Oct-2026"', 'SINCE 6-Oct-2026'],
			'ISO date' => ['SINCE "2026-10-06"', 'SINCE 6-Oct-2026'],
			'unquoted ISO date' => ['BEFORE 2026-10-04', 'BEFORE 4-Oct-2026'],
			'day month year' => ['SINCE "6 Oct 2026"', 'SINCE 6-Oct-2026'],
			'ON' => ['ON 03-Oct-2026', 'ON 3-Oct-2026'],
			'two dates' => ['SINCE "6 Oct 2026" BEFORE "2026-10-08"', 'SINCE 6-Oct-2026 BEFORE 8-Oct-2026'],
			'atom string' => ['SUBJECT plain', 'SUBJECT plain'],
			'quoted string' => ['TEXT "hello world"', 'TEXT "hello world"'],
			'backslash kept literally, then escaped' => ['TEXT "C:\\temp"', 'TEXT "C:\\\\temp"'],
			'keyword' => ['KEYWORD $Forwarded', 'KEYWORD $Forwarded'],
			'unkeyword' => ['UNKEYWORD $Forwarded', 'UNKEYWORD $Forwarded'],
			'all string keys' => ['FROM a TO b CC c BCC d BODY e', 'FROM a TO b CC c BCC d BODY e'],
			'8-bit text becomes a literal' => ['SUBJECT "café"', "SUBJECT {5}\r\ncafé"],
		];
		}

	/**
	 * @dataProvider validCriteria
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('validCriteria')]
	public function testValidCriteria(string $criteria, string $expected) : void
		{
		$this->assertEquals($expected, \PHPFUI\Imap2\SearchCriteria::toImap($criteria));
		}

	public function testCharsetIsAddedForEightBitText() : void
		{
		$this->assertEquals("CHARSET UTF-8 SUBJECT {5}\r\ncafé", \PHPFUI\Imap2\SearchCriteria::toImap('SUBJECT "café"', 'UTF-8'));
		$this->assertEquals('SUBJECT plain', \PHPFUI\Imap2\SearchCriteria::toImap('SUBJECT plain', 'UTF-8'));
		}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidCriteria() : array
		{
		return [
			'CRLF between keys' => ["SEEN\r\nFLAGGED"],
			'bare CR' => ["SEEN\rFLAGGED"],
			'NUL' => ["SEEN\0FLAGGED"],
			'line break inside quotes' => ["TEXT \"a\r\nb\""],
			'bare LF' => ["SEEN\nFLAGGED"],
			'unknown key (DRAFT is not a c-client key)' => ['DRAFT'],
			'OR is not supported by ext-imap' => ['OR SEEN FLAGGED'],
			'NOT' => ['NOT SEEN'],
			'UID' => ['UID 1:3'],
			'HEADER' => ['HEADER X-Foo bar'],
			'trailing word' => ['SUBJECT plain extra'],
			'missing argument' => ['TEXT'],
			'missing date' => ['SINCE'],
			'empty string' => ['TEXT ""'],
			'unterminated string' => ['TEXT "unterminated'],
			'escaped quote is not supported by ext-imap' => ['TEXT "say \\"hello"'],
			'invalid day' => ['SINCE 32-Oct-2026'],
			'invalid month' => ['SINCE "2026-13-01"'],
			'relative date' => ['SINCE tomorrow'],
			'keyword with a space' => ['KEYWORD "a b"'],
			'keyword with a bracket' => ['KEYWORD a]b'],
		];
		}

	/**
	 * @dataProvider invalidCriteria
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('invalidCriteria')]
	public function testInvalidCriteria(string $criteria) : void
		{
		$this->expectException(\InvalidArgumentException::class);
		\PHPFUI\Imap2\SearchCriteria::toImap($criteria);
		}

	public function testUnknownKeyMessageMatchesExtImap() : void
		{
		try
			{
			\PHPFUI\Imap2\SearchCriteria::toImap('DRAFT');
			$this->fail('no exception');
			}
		catch (\InvalidArgumentException $e)
			{
			$this->assertEquals('Unknown search criterion: DRAFT', $e->getMessage());
			}
		}
	}
