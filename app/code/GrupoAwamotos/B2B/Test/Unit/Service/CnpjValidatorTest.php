<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Service;

use GrupoAwamotos\B2B\Service\CnpjValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\Service\CnpjValidator
 */
class CnpjValidatorTest extends TestCase
{
    private CnpjValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CnpjValidator();
    }

    public function testIsValidWithKnownCnpjs(): void
    {
        $this->assertTrue($this->validator->isValid('11222333000181'));
        $this->assertTrue($this->validator->isValid('11.222.333/0001-81'));
        $this->assertTrue($this->validator->isValid('27865757000102'));
        $this->assertTrue($this->validator->isValid('33000167000101'));
    }

    public function testIsValidRejectsInvalid(): void
    {
        $this->assertFalse($this->validator->isValid('11222333000100'));
        $this->assertFalse($this->validator->isValid('00000000000000'));
        $this->assertFalse($this->validator->isValid('11111111111111'));
        $this->assertFalse($this->validator->isValid('1122233300018'));
        $this->assertFalse($this->validator->isValid(''));
        $this->assertFalse($this->validator->isValid('abcdefghijklmn'));
    }

    public function testCleanAndFormat(): void
    {
        $this->assertSame('11222333000181', $this->validator->clean('11.222.333/0001-81'));
        $this->assertSame('11.222.333/0001-81', $this->validator->format('11222333000181'));
        $this->assertSame('12345', $this->validator->format('12345'));
    }
}
