<?php

declare(strict_types=1);

namespace GrupoAwamotos\CspFix\Model\SubresourceIntegrity;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/**
 * Recalcula hashes SRI a partir dos arquivos reais em pub/static.
 */
class DiskHashVerifier
{
    private static ?string $lastInputDigest = null;

    private static ?string $lastOutput = null;

    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Só reconcilia assets que mudam com deploy do tema/módulos AWA (evita 5k hashes por request).
     */
    public function shouldVerifyPath(string $relativePath): bool
    {
        return str_contains($relativePath, 'AWA_Custom/')
            || str_contains($relativePath, 'GrupoAwamotos_');
    }

    /**
     * @return string|null Hash no formato sha256-{base64}
     */
    public function computeHashForPath(string $relativePath): ?string
    {
        if (!str_ends_with($relativePath, '.js') || !$this->shouldVerifyPath($relativePath)) {
            return null;
        }

        $staticDir = $this->filesystem->getDirectoryRead(DirectoryList::STATIC_VIEW);
        if (!$staticDir->isExist($relativePath)) {
            return null;
        }

        $content = $staticDir->readFile($relativePath);

        return 'sha256-' . base64_encode(hash('sha256', $content, true));
    }

    /**
     * Atualiza entradas do JSON de hashes quando o arquivo em disco diverge.
     *
     * @param array<string, string> $hashes
     * @return array{0: array<string, string>, 1: int} [hashes, updatedCount]
     */
    public function reconcileHashes(array $hashes): array
    {
        $updated = 0;

        foreach ($hashes as $path => $storedHash) {
            if (!is_string($storedHash) || !str_ends_with((string) $path, '.js')) {
                continue;
            }

            $computed = $this->computeHashForPath((string) $path);
            if ($computed === null || $computed === $storedHash) {
                continue;
            }

            $hashes[$path] = $computed;
            $updated++;
        }

        return [$hashes, $updated];
    }

    /**
     * @param string $serializedJson JSON object path => hash
     */
    public function reconcileSerialized(string $serializedJson): string
    {
        $digest = hash('sha256', $serializedJson);
        if (self::$lastInputDigest === $digest && self::$lastOutput !== null) {
            return self::$lastOutput;
        }

        $decoded = json_decode($serializedJson, true);
        if (!is_array($decoded)) {
            return $serializedJson;
        }

        [$reconciled] = $this->reconcileHashes($decoded);
        $output = json_encode($reconciled, JSON_UNESCAPED_SLASHES) ?: $serializedJson;

        self::$lastInputDigest = $digest;
        self::$lastOutput = $output;

        return $output;
    }
}
