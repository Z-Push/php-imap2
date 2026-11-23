<?php

namespace Tests\Unit;

/**
 * imap_fetchstructure() parts. Before, a NIL parameter list (as in message/rfc822 parts) threw a TypeError, and
 * properties without a value were set to null where ext-imap leaves them out.
 */
class BodyStructureTest extends \PHPUnit\Framework\TestCase
	{
	private function structure(array $bodystructure) : \stdClass | false
		{
		return \PHPFUI\Imap2\BodyStructure::fromMessage((object)['bodystructure' => $bodystructure]);
		}

	public function testTextPart() : void
		{
		$body = $this->structure(['TEXT', 'PLAIN', ['CHARSET', 'UTF-8'], null, null, '7BIT', '12', '1']);
		$this->assertEquals(\TYPETEXT, $body->type);
		$this->assertEquals('PLAIN', $body->subtype);
		$this->assertEquals(12, $body->bytes);
		$this->assertEquals(1, $body->lines);
		$this->assertEquals(1, $body->ifparameters);
		$this->assertEquals('UTF-8', $body->parameters[0]->value);
		foreach (['description', 'id', 'disposition', 'dparameters'] as $name)
			{
			$this->assertObjectNotHasProperty($name, $body);
			}
		}

	public function testNilParameters() : void
		{
		$body = $this->structure(['APPLICATION', 'OCTET-STREAM', null, null, null, 'BASE64', '100']);
		$this->assertEquals(\TYPEAPPLICATION, $body->type);
		$this->assertEquals(0, $body->ifparameters);
		$this->assertObjectNotHasProperty('lines', $body);
		foreach ((array)$body as $name => $value)
			{
			$this->assertNotNull($value, $name);
			}
		}

	public function testMultipart() : void
		{
		$body = $this->structure([
			['TEXT', 'PLAIN', ['CHARSET', 'UTF-8'], null, null, '7BIT', '5', '1'],
			['TEXT', 'HTML', ['CHARSET', 'UTF-8'], null, null, 'QUOTED-PRINTABLE', '20', '1'],
			'ALTERNATIVE', ['BOUNDARY', 'b1'], null, null,
		]);
		$this->assertEquals(\TYPEMULTIPART, $body->type);
		$this->assertEquals('ALTERNATIVE', $body->subtype);
		$this->assertCount(2, $body->parts);
		$this->assertEquals(\ENCQUOTEDPRINTABLE, $body->parts[1]->encoding);
		}

	public function testNil() : void
		{
		$this->assertFalse($this->structure([]));
		}
	}
