<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Test\Unit\Model;

use GrupoAwamotos\AiAssistant\Model\PiiRedactor;
use PHPUnit\Framework\TestCase;

class PiiRedactorTest extends TestCase
{
    private PiiRedactor $redactor;

    protected function setUp(): void
    {
        if (!class_exists(PiiRedactor::class, false)) {
            require_once dirname(__DIR__, 3) . '/Model/PiiRedactor.php';
        }
        $this->redactor = new PiiRedactor();
    }

    public function testRedactsEmailCpfCnpjAndPhone(): void
    {
        $raw = 'Meu e-mail é joao@awamotos.com CPF 529.982.247-25 CNPJ 12.345.678/0001-95 tel (16) 99999-1234';
        $safe = $this->redactor->redact($raw);

        $this->assertStringNotContainsString('joao@awamotos.com', $safe);
        $this->assertStringNotContainsString('529.982.247-25', $safe);
        $this->assertStringNotContainsString('12.345.678/0001-95', $safe);
        $this->assertStringNotContainsString('99999-1234', $safe);
        $this->assertStringContainsString('[email]', $safe);
        $this->assertStringContainsString('[cpf]', $safe);
        $this->assertStringContainsString('[cnpj]', $safe);
        $this->assertStringContainsString('[telefone]', $safe);
    }

    public function testKeepsCatalogQuery(): void
    {
        $raw = 'preciso de retrovisor titan 150 sku ABC-123';
        $this->assertSame($raw, $this->redactor->redact($raw));
    }

    public function testDetectsPasswordAndCard(): void
    {
        $this->assertTrue($this->redactor->containsSecret('minha senha é 123456'));
        $this->assertTrue($this->redactor->containsSecret('cartao 4111 1111 1111 1111'));
        $this->assertFalse($this->redactor->containsSecret('quero filtro de ar para cg 160'));
    }

    public function testRedactsSecretAssignment(): void
    {
        $safe = $this->redactor->redact('token=sk-or-v1-abc123 e depois a peça');
        $this->assertStringNotContainsString('sk-or-v1-abc123', $safe);
        $this->assertStringContainsString('[redigido]', $safe);
    }
}
