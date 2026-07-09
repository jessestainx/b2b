<?php

/**
 * GrupoAwamotos_CspFix
 *
 * Objetivo:
 * - Evitar corrupsão do arquivo pub/static/{context}/sri-hashes.json por escrita não-atômica
 * - Evitar 500 em caso de leitura parcial (JSON inválido) retornando null (equivale a "sem dados")
 */

declare(strict_types=1);

namespace GrupoAwamotos\CspFix\Plugin\Csp\SubresourceIntegrity\Storage;

use GrupoAwamotos\CspFix\Model\SubresourceIntegrity\DiskHashVerifier;
use Magento\Csp\Model\SubresourceIntegrity\Storage\File as Subject;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

class FileAtomicPlugin
{
    private const FILENAME = 'sri-hashes.json';

    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private DiskHashVerifier $diskHashVerifier;

    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger,
        DiskHashVerifier $diskHashVerifier
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->diskHashVerifier = $diskHashVerifier;
    }

    /**
     * Escrita atômica (write temp + rename) para reduzir chance de arquivo truncado.
     */
    public function aroundSave(Subject $subject, callable $proceed, string $data, ?string $context): bool
    {
        $data = $this->diskHashVerifier->reconcileSerialized($data);

        $staticDir = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);
        $path      = $this->resolveFilePath($context);
        $absBase   = rtrim($staticDir->getAbsolutePath(''), '/');
        $tmpRel    = $path . '.tmp.' . bin2hex(random_bytes(6));
        $absTmp    = $absBase . '/' . $tmpRel;
        $absDest   = $absBase . '/' . $path;

        try {
            if ($context) {
                $staticDir->create($context);
            }

            $staticDir->writeFile($tmpRel, $data, 'w');

            $renameError = null;
            $writeError   = null;
            if (!$this->safeRename($absTmp, $absDest, $renameError)) {
                if (!$this->safeWrite($absDest, $data, $writeError)) {
                    throw new \RuntimeException(sprintf(
                        '[CspFix] Falha ao persistir %s (rename: %s | write: %s)',
                        $absDest,
                        $renameError ?? 'unknown',
                        $writeError ?? 'unknown'
                    ));
                }

                $this->safeUnlink($absTmp);
            }

            return true;
        } catch (\Throwable $e) {
            if (file_exists($absTmp)) {
                $this->safeUnlink($absTmp);
            }

            $this->logger->critical('[CspFix] Atomic write falhou: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Leitura tolerante: se o JSON estiver inválido (leitura parcial por concorrência),
     * retorna null para evitar exception no unserialize.
     */
    public function aroundLoad(Subject $subject, callable $proceed, ?string $context): ?string
    {
        $raw = null;

        try {
            $raw = $proceed($context);
        } catch (\Throwable $e) {
            $this->logger->warning('[CspFix] Erro ao carregar sri-hashes.json: ' . $e->getMessage());
            return null;
        }

        if (!$raw) {
            return $raw;
        }

        if ($this->isValidJson($raw)) {
            return $raw;
        }

        try {
            $raw2 = $proceed($context);
            if ($raw2 && $this->isValidJson($raw2)) {
                return $raw2;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[CspFix] Retry de leitura do sri-hashes.json falhou: ' . $e->getMessage());
        }

        return null;
    }

    private function isValidJson(string $raw): bool
    {
        json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function resolveFilePath(?string $context): string
    {
        return ($context ? $context . DIRECTORY_SEPARATOR : '') . self::FILENAME;
    }

    private function safeRename(string $from, string $to, ?string &$errorMessage = null): bool
    {
        return $this->withFilesystemWarningCapture(
            static fn (): bool => rename($from, $to),
            $errorMessage
        );
    }

    private function safeWrite(string $path, string $content, ?string &$errorMessage = null): bool
    {
        return $this->withFilesystemWarningCapture(
            static fn (): bool => file_put_contents($path, $content, LOCK_EX) !== false,
            $errorMessage
        );
    }

    private function safeUnlink(string $path): void
    {
        $ignoreError = null;
        $this->withFilesystemWarningCapture(
            static fn (): bool => !file_exists($path) || unlink($path),
            $ignoreError
        );
    }

    private function withFilesystemWarningCapture(callable $callback, ?string &$errorMessage = null): bool
    {
        $lastError = null;
        set_error_handler(static function (int $severity, string $message) use (&$lastError): bool {
            $lastError = $message;
            return true;
        });

        try {
            $result = (bool) $callback();
        } finally {
            restore_error_handler();
        }

        $errorMessage = $lastError;

        return $result;
    }
}
