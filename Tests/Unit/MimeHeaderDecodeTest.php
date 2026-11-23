<?php

namespace Tests\Unit;

/**
 * imap_mime_header_decode() matches ext-imap. Before, it was not available. Expected values are what ext-imap returns.
 */
class MimeHeaderDecodeTest extends \PHPUnit\Framework\TestCase
	{
	/**
	 * Expected parts are [charset, hex of text]
	 *
	 * @return array<string, array{string, array<array{string, string}>}>
	 */
	public static function headers() : array
		{
		return [
			'plain text' => ['plain text', [['default', '706c61696e2074657874']]],
			'empty' => ['', []],
			'quoted-printable' => ['=?UTF-8?Q?R=C3=A9union?=', [['UTF-8', '52c3a9756e696f6e']]],
			'mixed parts' => ['=?UTF-8?Q?R=C3=A9union?= plain =?ISO-8859-1?B?R/xu?=', [['UTF-8', '52c3a9756e696f6e'], ['default', '20706c61696e20'], ['ISO-8859-1', '47fc6e']]],
			'space between encoded words is dropped' => ['=?UTF-8?B?w4lsb2RpZQ==?= =?UTF-8?B?IE3DvGxsZXI=?=', [['UTF-8', 'c3896c6f646965'], ['UTF-8', '204dc3bc6c6c6572']]],
			'folded encoded words' => ["=?UTF-8?Q?a?=\r\n =?UTF-8?Q?b?=", [['UTF-8', '61'], ['UTF-8', '62']]],
			'underscore is a space' => ['=?utf-8?q?under_score?=', [['utf-8', '756e6465722073636f7265']]],
			'text around an encoded word' => ['pre =?UTF-8?Q?x?= post', [['default', '70726520'], ['UTF-8', '78'], ['default', '20706f7374']]],
			'unterminated encoded word' => ['=?UTF-8?Q?broken', [['default', '3d3f5554462d383f513f62726f6b656e']]],
			'unknown encoding gives the raw text' => ['=?UTF-8?X?eA==?=', [['UTF-8', '65413d3d']]],
			'charset is not converted' => ['=?ISO-2022-JP?B?GyRCOzNFRBsoQg==?=', [['ISO-2022-JP', '1b24423b3345441b2842']]],
			'stray =?' => ['a =?bad', [['default', '6120'], ['default', '3d3f626164']]],
			'empty charset' => ['=??Q?x?=', [['', '78']]],
			'invalid quoted-printable is kept' => ['=?UTF-8?Q?=ZZ?=', [['UTF-8', '3d5a5a']]],
		];
		}

	/**
	 * @dataProvider headers
	 *
	 * @param array<array{string, string}> $expected
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('headers')]
	public function testDecode(string $header, array $expected) : void
		{
		$parts = \imap_mime_header_decode($header);
		$this->assertIsArray($parts);
		$this->assertEquals($expected, \array_map(static fn($p) => [$p->charset, \bin2hex($p->text)], $parts));
		}

	public function testInvalidBase64IsFalse() : void
		{
		$this->assertFalse(\imap_mime_header_decode('=?UTF-8?B?!!!?='));
		}
	}
