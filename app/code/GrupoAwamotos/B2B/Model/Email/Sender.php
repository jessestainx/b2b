<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Email;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\B2bCustomer;
use GrupoAwamotos\B2B\Model\Cotacao;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Sender
{
    private const TEMPLATE_RECEIVED          = 'grupoawamotos_b2b_registration_received';
    private const TEMPLATE_APPROVED          = 'grupoawamotos_b2b_registration_approved';
    private const TEMPLATE_REJECTED          = 'grupoawamotos_b2b_registration_rejected';
    private const TEMPLATE_PENDING_SUMMARY   = 'grupoawamotos_b2b_admin_pending_summary';
    private const TEMPLATE_COTACAO_RECEIVED  = 'grupoawamotos_b2b_cotacao_received';
    private const TEMPLATE_COTACAO_QUOTED    = 'grupoawamotos_b2b_cotacao_quoted';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly BackendUrl $backendUrl
    ) {
    }

    public function sendRegistrationReceived(B2bCustomer $b2bCustomer): void
    {
        $this->send(
            self::TEMPLATE_RECEIVED,
            $b2bCustomer->getEmail(),
            $b2bCustomer->getContactName(),
            $this->buildVars($b2bCustomer)
        );
    }

    public function sendApprovalEmail(B2bCustomer $b2bCustomer): void
    {
        $this->send(
            self::TEMPLATE_APPROVED,
            $b2bCustomer->getEmail(),
            $b2bCustomer->getContactName(),
            $this->buildVars($b2bCustomer)
        );
    }

    public function sendRejectionEmail(B2bCustomer $b2bCustomer): void
    {
        $vars = $this->buildVars($b2bCustomer);
        $vars['rejection_reason'] = $b2bCustomer->getData('rejection_reason') ?? '';

        $this->send(
            self::TEMPLATE_REJECTED,
            $b2bCustomer->getEmail(),
            $b2bCustomer->getContactName(),
            $vars
        );
    }

    /**
     * Envia resumo diário de aprovações pendentes ao admin.
     *
     * @param int   $count           Total de cadastros pendentes
     * @param array $pendingCustomers Array de ['razao_social', 'cnpj', 'contact_name', 'created_at']
     */
    public function sendPendingSummaryToAdmin(int $count, array $pendingCustomers): void
    {
        $adminEmail = $this->config->getAdminEmail();
        if (!$adminEmail) {
            return;
        }

        $store    = $this->storeManager->getStore();
        $adminUrl = $this->backendUrl->getUrl('awamotos_b2b/customer/index');

        $rows = '';
        foreach ($pendingCustomers as $item) {
            $rows .= sprintf(
                '<tr><td style="padding:6px 8px;border-bottom:1px solid #eee;">%s</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;">%s</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #eee;color:#777;">%s</td></tr>',
                htmlspecialchars((string) ($item['razao_social'] ?? '')),
                htmlspecialchars((string) ($item['cnpj'] ?? '')),
                htmlspecialchars((string) ($item['created_at'] ?? ''))
            );
        }

        $pendingList = $rows
            ? '<table style="width:100%;border-collapse:collapse;margin:12px 0;">'
              . '<thead><tr>'
              . '<th style="text-align:left;padding:6px 8px;background:#f5f5f5;">Razão Social</th>'
              . '<th style="text-align:left;padding:6px 8px;background:#f5f5f5;">CNPJ</th>'
              . '<th style="text-align:left;padding:6px 8px;background:#f5f5f5;">Cadastrado em</th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            : '';

        $this->send(
            self::TEMPLATE_PENDING_SUMMARY,
            $adminEmail,
            'Administrador AWA Motos',
            [
                'pending_count' => $count,
                'pending_list'  => $pendingList,
                'store_name'    => $store->getName(),
                'admin_url'     => $adminUrl,
            ]
        );
    }

    public function notifyAdmin(B2bCustomer $b2bCustomer): void
    {
        $adminEmail = $this->config->getAdminEmail();
        if (!$adminEmail) {
            return;
        }

        $this->send(
            self::TEMPLATE_RECEIVED,
            $adminEmail,
            'Administrador AWA Motos',
            $this->buildVars($b2bCustomer)
        );
    }

    public function sendCotacaoReceived(Cotacao $cotacao, B2bCustomer $b2b): void
    {
        $store      = $this->storeManager->getStore();
        $cotacaoUrl = $store->getBaseUrl() . 'b2b/cotacao/view/id/' . $cotacao->getId();

        $this->send(
            self::TEMPLATE_COTACAO_RECEIVED,
            $b2b->getEmail(),
            $b2b->getContactName(),
            [
                'cotacao_id'   => $cotacao->getId(),
                'contact_name' => $b2b->getContactName(),
                'razao_social' => $b2b->getRazaoSocial(),
                'notes'        => $cotacao->getNotes(),
                'cotacao_url'  => $cotacaoUrl,
                'store_name'   => $store->getName(),
                'store_url'    => $store->getBaseUrl(),
            ]
        );
    }

    public function sendCotacaoQuoted(Cotacao $cotacao, B2bCustomer $b2b): void
    {
        $store      = $this->storeManager->getStore();
        $cotacaoUrl = $store->getBaseUrl() . 'b2b/cotacao/view/id/' . $cotacao->getId();

        $this->send(
            self::TEMPLATE_COTACAO_QUOTED,
            $b2b->getEmail(),
            $b2b->getContactName(),
            [
                'cotacao_id'   => $cotacao->getId(),
                'contact_name' => $b2b->getContactName(),
                'razao_social' => $b2b->getRazaoSocial(),
                'admin_notes'  => $cotacao->getAdminNotes(),
                'cotacao_url'  => $cotacaoUrl,
                'store_name'   => $store->getName(),
                'store_url'    => $store->getBaseUrl(),
            ]
        );
    }

    private function buildVars(B2bCustomer $b2bCustomer): array
    {
        return [
            'contact_name'       => $b2bCustomer->getContactName(),
            'razao_social'       => $b2bCustomer->getRazaoSocial(),
            'cnpj'               => $b2bCustomer->getCnpj(),
            'email'              => $b2bCustomer->getEmail(),
            'phone'              => $b2bCustomer->getPhone(),
            'store_name'         => $this->storeManager->getStore()->getName(),
            'store_url'          => $this->storeManager->getStore()->getBaseUrl(),
        ];
    }

    private function send(string $template, string $toEmail, string $toName, array $vars): void
    {
        $this->inlineTranslation->suspend();
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $sender  = $this->config->getEmailSender();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($sender, $storeId)
                ->addTo($toEmail, $toName)
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Falha ao enviar e-mail: ' . $e->getMessage());
            throw $e;
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
