<?php

/**
 * Copyright © GrupoAwamotos. All rights reserved.
 * Fix para compatibilidade do MagePal Gmail SMTP App com Symfony Mailer no Magento 2.4.8
 *
 * Correções aplicadas:
 * 1. Reply-To: usa addMailboxListHeader() ao invés de addMailboxHeader()
 * 2. SSL/TLS: corrige lógica do EsmtpTransport para STARTTLS funcionar corretamente
 *
 * O problema original: O MagePal usa addMailboxHeader() para o header Reply-To, mas o Symfony Mailer
 * agora exige addMailboxListHeader() para esse header específico.
 *
 * O segundo problema: A lógica do TLS está invertida - para porta 587 com STARTTLS,
 * o terceiro parâmetro do EsmtpTransport deve ser false.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SmtpFix\Mail;

use Exception;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface as SymfonyTransportInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Auth\LoginAuthenticator;
use Symfony\Component\Mailer\Transport\Smtp\Auth\PlainAuthenticator;
use Symfony\Component\Mime\Message as SymfonyMessage;
use Symfony\Component\Mime\Address;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Phrase;
use MagePal\GmailSmtpApp\Helper\Data;
use MagePal\GmailSmtpApp\Model\Store;

/**
 * Class Smtp - versão corrigida para Magento 2.4.8 / Symfony Mailer
 */
class Smtp extends \MagePal\GmailSmtpApp\Mail\Smtp
{
    /**
     * @param SymfonyMessage $message
     * @throws MailException
     */
    public function sendSmtpMessage($message)
    {
        $dataHelper = $this->dataHelper;
        $dataHelper->setStoreId($this->storeModel->getStoreId());

        $this->setReplyToPath($message);
        $this->setSender($message);

        try {
            $host = $dataHelper->getConfigSmtpHost();
            $port = (int)$dataHelper->getConfigSmtpPort();
            $username = $dataHelper->getConfigUsername();
            $password = $dataHelper->getConfigPassword();
            $auth = strtolower($dataHelper->getConfigAuth());
            $ssl = strtolower($dataHelper->getConfigSsl());

            /**
             * FIX: Lógica correta para SSL/TLS:
             * - Porta 465: usa SSL direto (tls = true)
             * - Porta 587: usa STARTTLS (tls = false, o Symfony negocia automaticamente)
             * - "none" ou porta 25: sem criptografia (tls = false)
             */
            $useSsl = false;

            if ($ssl === 'ssl' || $port === 465) {
                // SSL direto na porta 465
                $useSsl = true;
            } elseif ($ssl === 'tls' || $ssl === 'starttls') {
                // STARTTLS na porta 587 - não usa SSL inicial
                // O Symfony Mailer negocia STARTTLS automaticamente quando tls=false
                $useSsl = false;
            }

            /** @var SymfonyTransportInterface $transport */
            $transport = new EsmtpTransport($host, $port, $useSsl);

            if ($username) {
                $transport->setUsername($username);
            }

            if ($password) {
                $transport->setPassword($password);
            }

            if ($auth !== 'none') {
                switch ($auth) {
                    case 'plain':
                        $transport->setAuthenticators([new PlainAuthenticator()]);
                        break;
                    case 'login':
                    default:
                        $transport->setAuthenticators([new LoginAuthenticator()]);
                        break;
                }
            }

            $mailer = new Mailer($transport);
            $mailer->send($message);
        } catch (Exception $e) {
            throw new MailException(
                new Phrase($e->getMessage()),
                $e
            );
        }
    }
    /**
     * @param SymfonyMessage $message
     */
    protected function setReplyToPath($message)
    {
        $dataHelper = $this->dataHelper;
        $messageFromAddress = $this->getMessageFromAddressObject($message);

        /*
         * Set reply-to path
         * 0 = No
         * 1 = From
         * 2 = Custom
         */
        switch ($dataHelper->getConfigSetReturnPath()) {
            case 1:
                $returnPathEmail = $messageFromAddress;
                break;
            case 2:
                $returnPathEmail = $dataHelper->getConfigReturnPathEmail();
                break;
            default:
                $returnPathEmail = null;
                break;
        }

        // Verifica se já existe um Reply-To
        $existingReplyTo = $message->getHeaders()->get('reply-to');
        $hasExistingReplyTo = $existingReplyTo && !empty($existingReplyTo->getAddresses());

        if (!$hasExistingReplyTo && $dataHelper->getConfigSetReplyTo()) {
            if ($returnPathEmail instanceof Address) {
                // FIX: Usar addMailboxListHeader ao invés de addMailboxHeader
                // O Symfony Mailer exige MailboxListHeader para o header Reply-To
                $message->getHeaders()->addMailboxListHeader('reply-to', [$returnPathEmail]);
            } elseif (!empty($returnPathEmail)) {
                $name = $messageFromAddress instanceof Address ? $messageFromAddress->getName() : '';

                // FIX: Usar addMailboxListHeader com array de Address
                $message->getHeaders()->addMailboxListHeader(
                    'reply-to',
                    [new Address($returnPathEmail, $name)]
                );
            }
        }
    }

    /**
     * @param SymfonyMessage $message
     */
    protected function setSender($message)
    {
        $dataHelper = $this->dataHelper;
        $messageFromAddress = $this->getMessageFromAddressObject($message);

        //Set from address
        switch ($dataHelper->getConfigSetFrom()) {
            case 1:
                $setFromEmail = $messageFromAddress;
                break;
            case 2:
                $setFromEmail = $dataHelper->getConfigCustomFromEmail();
                break;
            default:
                $setFromEmail = null;
                break;
        }

        if ($setFromEmail !== null && $dataHelper->getConfigSetFrom()) {
            // Remove header Sender existente se houver
            if ($message->getHeaders()->has('Sender')) {
                $message->getHeaders()->remove('Sender');
            }

            if ($setFromEmail instanceof Address) {
                $message->getHeaders()->addMailboxHeader('Sender', $setFromEmail);
            } elseif (!empty($setFromEmail)) {
                $name = $messageFromAddress instanceof Address ? $messageFromAddress->getName() : '';

                $message->getHeaders()->addMailboxHeader(
                    'Sender',
                    new Address($setFromEmail, $name)
                );
            }
        }
    }
}
