<?php
use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Email\Model\TemplateFactory;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory as TemplateCollectionFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/** @var State $state */
$state = $objectManager->get(State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (\Throwable $e) {
    // already set
}

/** @var TemplateFactory $templateFactory */
$templateFactory = $objectManager->get(TemplateFactory::class);
/** @var TemplateCollectionFactory $templateCollectionFactory */
$templateCollectionFactory = $objectManager->get(TemplateCollectionFactory::class);
/** @var ConfigResource $configResource */
$configResource = $objectManager->get(ConfigResource::class);

/**
 * Aplica substituições de copy em inglês para português nos templates Magento.
 */
function portugueseEmailContent(string $content): string
{
    $replacements = [
        'Your %store_name order confirmation' => 'Pedido confirmado na %store_name',
        'Thank you for your order from %store_name.' => 'Obrigado pelo pedido na %store_name.',
        'Once your package ships we will send you a tracking number.' => 'Quando o pedido for enviado, você receberá o código de rastreamento.',
        'You can check the status of your order by <a href="%account_url">logging into your account</a>.' => 'Acompanhe o status do pedido <a href="%account_url">acessando sua conta</a>.',
        'If you have questions about your order, you can email us at <a href="mailto:%store_email">%store_email</a>' => 'Dúvidas sobre o pedido? Escreva para <a href="mailto:%store_email">%store_email</a>',
        'or call us at <a href="tel:%store_phone">%store_phone</a>' => 'ou ligue para <a href="tel:%store_phone">%store_phone</a>',
        'Our hours are <span class="no-link">%store_hours</span>.' => 'Horário de atendimento: <span class="no-link">%store_hours</span>.',
        'Your Order <span class="no-link">#%increment_id</span>' => 'Pedido <span class="no-link">#%increment_id</span>',
        'Placed on <span class="no-link">%created_at</span>' => 'Realizado em <span class="no-link">%created_at</span>',
        'Billing Info' => 'Cobrança',
        'Shipping Info' => 'Entrega',
        'Payment Method' => 'Forma de pagamento',
        'Shipping Method' => 'Forma de envio',
        'Welcome to %store_name' => 'Bem-vindo à %store_name',
        'To sign in to our site, use these credentials during checkout or on the <a href="%customer_url">My Account</a> page:' => 'Para entrar no site, use estes dados no checkout ou em <a href="%customer_url">Minha conta</a>:',
        'Email:' => 'E-mail:',
        'Password:' => 'Senha:',
        'Password you set when creating account' => 'Senha definida no cadastro',
        'Forgot your account password? Click <a href="%reset_url">here</a> to reset it.' => 'Esqueceu a senha? <a href="%reset_url">Clique aqui</a> para redefinir.',
        'When you sign in to your account, you will be able to:' => 'Na sua conta você pode:',
        'Proceed through checkout faster' => 'Finalizar compras com mais agilidade',
        'Check the status of orders' => 'Consultar status dos pedidos',
        'View past orders' => 'Ver pedidos anteriores',
        'Store alternative addresses (for shipping to multiple family members and friends)' => 'Salvar endereços alternativos',
        'New Invoice for your %store_name order' => 'Nova fatura do pedido na %store_name',
        'Your Invoice #%invoice_id for Order #%order_id' => 'Fatura #%invoice_id do pedido #%order_id',
        'Your Shipment #%shipment_id for Order #%order_id' => 'Envio #%shipment_id do pedido #%order_id',
        'Your Credit Memo #%creditmemo_id for Order #%order_id' => 'Estorno #%creditmemo_id do pedido #%order_id',
        'Reset your %store_name password' => 'Redefina sua senha na %store_name',
        'Please click the following link to reset your password:' => 'Clique no link abaixo para redefinir sua senha:',
        'Newsletter subscription confirmation' => 'Confirmação de inscrição na newsletter',
        'Thank you for subscribing to our newsletter.' => 'Obrigado por se inscrever na nossa newsletter.',
        'To begin receiving the newsletter, you must first confirm your subscription by clicking on the following link:' => 'Para começar a receber, confirme a inscrição clicando no link:',
        'Newsletter subscription success' => 'Inscrição na newsletter confirmada',
        'You have been successfully subscribed to our newsletter.' => 'Sua inscrição na newsletter foi confirmada.',
        'Newsletter unsubscription success' => 'Descadastro da newsletter',
        'You have been unsubscribed from the newsletter.' => 'Você foi descadastrado da newsletter.',
        'Contact Form' => 'Formulário de contato',
        'Name' => 'Nome',
        'Phone' => 'Telefone',
        'Comment' => 'Mensagem',
    ];

    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    return $content;
}

$vendorBase = BP . '/vendor/magento';

$templates = [
    [
        'code' => 'grupoawamotos_sales_order_new_ptbr',
        'file' => $vendorBase . '/module-sales/view/frontend/email/order_new.html',
        'subject' => 'Pedido #{{var order.increment_id}} confirmado',
        'orig' => 'sales_email_order_template',
        'config_paths' => [
            'sales_email/order/template',
            'sales_email/order_guest/template',
            'sales_email/order_comment/template',
            'sales_email/order_comment_guest/template',
        ],
    ],
    [
        'code' => 'grupoawamotos_sales_invoice_new_ptbr',
        'file' => $vendorBase . '/module-sales/view/frontend/email/invoice_new.html',
        'subject' => 'Fatura emitida para o pedido {{var order.increment_id}}',
        'orig' => 'sales_email_invoice_template',
        'config_paths' => [
            'sales_email/invoice/template',
            'sales_email/invoice_guest/template',
        ],
    ],
    [
        'code' => 'grupoawamotos_sales_shipment_new_ptbr',
        'file' => $vendorBase . '/module-sales/view/frontend/email/shipment_new.html',
        'subject' => 'Seu pedido {{var order.increment_id}} saiu para entrega',
        'orig' => 'sales_email_shipment_template',
        'config_paths' => [
            'sales_email/shipment/template',
            'sales_email/shipment_guest/template',
            'sales_email/shipment_comment/template',
            'sales_email/shipment_comment_guest/template',
        ],
    ],
    [
        'code' => 'grupoawamotos_sales_creditmemo_new_ptbr',
        'file' => $vendorBase . '/module-sales/view/frontend/email/creditmemo_new.html',
        'subject' => 'Estorno do pedido {{var order.increment_id}}',
        'orig' => 'sales_email_creditmemo_template',
        'config_paths' => [
            'sales_email/creditmemo/template',
            'sales_email/creditmemo_guest/template',
            'sales_email/creditmemo_comment/template',
            'sales_email/creditmemo_comment_guest/template',
        ],
    ],
    [
        'code' => 'grupoawamotos_customer_account_new_ptbr',
        'file' => $vendorBase . '/module-customer/view/frontend/email/account_new.html',
        'subject' => 'Bem-vindo à {{var store.frontend_name}}',
        'orig' => 'customer_create_account_email_template',
        'config_paths' => [
            'customer/create_account/email_template',
        ],
    ],
    [
        'code' => 'grupoawamotos_customer_password_reset_ptbr',
        'file' => $vendorBase . '/module-customer/view/frontend/email/password_reset_confirmation.html',
        'subject' => 'Redefina sua senha na {{var store.frontend_name}}',
        'orig' => 'customer_password_forgot_email_template',
        'config_paths' => [
            'customer/password/forgot_email_template',
        ],
    ],
    [
        'code' => 'grupoawamotos_newsletter_confirm_ptbr',
        'file' => $vendorBase . '/module-newsletter/view/frontend/email/subscr_confirm.html',
        'subject' => 'Confirme sua inscrição na newsletter',
        'orig' => 'newsletter_subscription_confirm_email_template',
        'config_paths' => [
            'newsletter/subscription/confirm_email_template',
        ],
    ],
    [
        'code' => 'grupoawamotos_newsletter_success_ptbr',
        'file' => $vendorBase . '/module-newsletter/view/frontend/email/subscr_success.html',
        'subject' => 'Inscrição confirmada com sucesso',
        'orig' => 'newsletter_subscription_success_email_template',
        'config_paths' => [
            'newsletter/subscription/success_email_template',
        ],
    ],
    [
        'code' => 'grupoawamotos_newsletter_unsubscribe_ptbr',
        'file' => $vendorBase . '/module-newsletter/view/frontend/email/unsub_success.html',
        'subject' => 'Descadastro confirmado',
        'orig' => 'newsletter_subscription_un_email_template',
        'config_paths' => [
            'newsletter/subscription/un_email_template',
        ],
    ],
    [
        'code' => 'grupoawamotos_contact_form_ptbr',
        'file' => $vendorBase . '/module-contact/view/frontend/email/submitted_form.html',
        'subject' => 'Nova mensagem de contato',
        'orig' => 'contact_email_email_template',
        'config_paths' => [
            'contact/email/email_template',
        ],
    ],
];

$success = true;
foreach ($templates as $templateData) {
    if (!is_readable($templateData['file'])) {
        echo sprintf("[ERRO] Template %s não encontrado em %s\n", $templateData['code'], $templateData['file']);
        $success = false;
        continue;
    }

    $content = portugueseEmailContent((string) file_get_contents($templateData['file']));
    $collection = $templateCollectionFactory->create();
    $collection->addFieldToFilter('template_code', $templateData['code']);
    $existing = $collection->getFirstItem();
    $template = $existing && $existing->getId() ? $existing : $templateFactory->create();
    $template->setTemplateCode($templateData['code']);
    $template->setTemplateText($content);
    $template->setTemplateType(\Magento\Email\Model\Template::TYPE_HTML);
    $template->setTemplateSubject($templateData['subject']);
    $template->setOrigTemplateCode($templateData['orig']);
    $template->setOrigTemplateVariables('{}');
    $template->save();

    $templateId = (int) $template->getId();
    foreach ($templateData['config_paths'] as $path) {
        $configResource->saveConfig($path, $templateId, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
    }

    echo sprintf("[OK] %s aplicado (ID %d)\n", $templateData['code'], $templateId);
}

if ($success) {
    echo "Todas as configurações foram aplicadas com sucesso.\n";
    echo "Execute: php bin/magento cache:flush config\n";
} else {
    echo "Existem erros nos templates listados acima.\n";
}
