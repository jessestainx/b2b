<?php

declare(strict_types=1);

namespace GrupoAwamotos\CspFix\Plugin\Csp\SubresourceIntegrity;

use GrupoAwamotos\CspFix\Model\SubresourceIntegrity\DiskHashVerifier;
use Magento\Csp\Model\SubresourceIntegrity;
use Magento\Csp\Model\SubresourceIntegrityRepository;

/**
 * Garante que o hash SRI servido ao browser corresponda ao .js em disco.
 */
class RepositoryDiskVerifyPlugin
{
    private DiskHashVerifier $diskHashVerifier;

    public function __construct(DiskHashVerifier $diskHashVerifier)
    {
        $this->diskHashVerifier = $diskHashVerifier;
    }

    public function afterGetByPath(
        SubresourceIntegrityRepository $subject,
        ?SubresourceIntegrity $result,
        string $path
    ): ?SubresourceIntegrity {
        if ($result === null) {
            return null;
        }

        $storedHash = $result->getHash();
        if ($storedHash === null || $storedHash === '') {
            return $result;
        }

        if (!$this->diskHashVerifier->shouldVerifyPath($path)) {
            return $result;
        }

        $computed = $this->diskHashVerifier->computeHashForPath($path);
        if ($computed === null || $computed === $storedHash) {
            return $result;
        }

        return new SubresourceIntegrity([
            'path' => $result->getPath() ?? $path,
            'hash' => $computed,
        ]);
    }

    /**
     * RequireJS injeta integrity via window.sriHashes (Hashes::getAll), não getByPath.
     *
     * @param SubresourceIntegrity[] $result
     * @return SubresourceIntegrity[]
     */
    public function afterGetAll(SubresourceIntegrityRepository $subject, array $result): array
    {
        $fixed = [];

        foreach ($result as $integrity) {
            if (!$integrity instanceof SubresourceIntegrity) {
                continue;
            }

            $path = $integrity->getPath();
            $storedHash = $integrity->getHash();

            if ($path === null || $storedHash === null || $storedHash === '') {
                $fixed[] = $integrity;
                continue;
            }

            if (!$this->diskHashVerifier->shouldVerifyPath($path)) {
                $fixed[] = $integrity;
                continue;
            }

            $computed = $this->diskHashVerifier->computeHashForPath($path);
            if ($computed !== null && $computed !== $storedHash) {
                $fixed[] = new SubresourceIntegrity([
                    'path' => $path,
                    'hash' => $computed,
                ]);
                continue;
            }

            $fixed[] = $integrity;
        }

        return $fixed;
    }
}
