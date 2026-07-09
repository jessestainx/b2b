<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Pricing\Render;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Service\CustomerGroupManager;
use Magento\Catalog\Pricing\Render\FinalPriceBox;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\UrlInterface;

class FinalPricePlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpContext $httpContext,
        private readonly CustomerGroupManager $groupManager,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Se o modo B2B estrito estiver ativo, substitui o bloco de preço por um
     * CTA de login/cadastro APENAS para clientes com status "B2B Pendente".
     *
     * Visitantes anônimos (NOT LOGGED IN) e clientes B2C (General) continuam
     * vendo preços normalmente, pois podem comprar como clientes B2C.
     * Somente clientes que iniciaram cadastro B2B mas ainda não foram aprovados
     * ficam bloqueados — incentivando a conclusão da aprovação.
     *
     * Usa HttpContext em vez de CustomerSession para compatibilidade com FPC:
     * o CONTEXT_GROUP é setado antes do cache e varia a página por grupo.
     */
    public function aroundToHtml(FinalPriceBox $subject, \Closure $proceed): string
    {
        if (!$this->config->isEnabled() || !$this->config->isStrictB2B()) {
            return $proceed();
        }

        // Mostrar preço: B2B Aprovado OU qualquer não-B2B (visitante/B2C)
        if ($this->isApprovedB2B() || !$this->isPendingB2B()) {
            return $proceed();
        }

        // Bloquear somente B2B Pendente
        return $this->renderLoginCta();
    }

    private function isApprovedB2B(): bool
    {
        $currentGroupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        if ($currentGroupId === null) {
            return false;
        }

        $approvedGroupId = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_APPROVED);
        return $approvedGroupId !== null && (int) $currentGroupId === $approvedGroupId;
    }

    /**
     * Retorna true somente para o grupo "B2B Pendente".
     * Visitante anônimo (grupo 0) e B2C (grupo 1) retornam false.
     */
    private function isPendingB2B(): bool
    {
        $currentGroupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        if ($currentGroupId === null) {
            return false;
        }

        $pendingGroupId = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_PENDING);
        return $pendingGroupId !== null && (int) $currentGroupId === $pendingGroupId;
    }

    private function renderLoginCta(): string
    {
        $loginUrl    = $this->url->getUrl('customer/account/login');
        $registerUrl = $this->url->getUrl('b2b/register');

        return sprintf(
            '<div class="b2b-login-to-see-price">'
            . '<a href="%s" class="b2b-login-link">Faça login</a> ou '
            . '<a href="%s" class="b2b-register-link">cadastre-se</a> '
            . 'para ver os preços.'
            . '</div>',
            htmlspecialchars($loginUrl),
            htmlspecialchars($registerUrl)
        );
    }
}
