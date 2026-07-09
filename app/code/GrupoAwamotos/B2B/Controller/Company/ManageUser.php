<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Company;

use GrupoAwamotos\B2B\Model\Company;
use GrupoAwamotos\B2B\Model\CompanyService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class ManageUser implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private FormKeyValidator $formKeyValidator;
    private Session $customerSession;
    private CompanyService $companyService;
    private CustomerRepositoryInterface $customerRepository;
    private StoreManagerInterface $storeManager;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        Session $customerSession,
        CompanyService $companyService,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->companyService = $companyService;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['success' => false, 'message' => __('Formulário inválido. Tente novamente.')]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Login necessário.')]);
        }

        $currentCustomerId = (int) $this->customerSession->getCustomerId();
        $company = $this->companyService->getCompanyForCustomer($currentCustomerId);

        if (!$company) {
            return $result->setData(['success' => false, 'message' => __('Empresa não encontrada.')]);
        }

        // Passa companyId explicitamente: garante que o papel checado é o do
        // ADMIN nesta empresa (a mesma que $company acima), mesmo se o cliente
        // pertencer a múltiplas empresas (multi-empresa) com papéis diferentes.
        $role = $this->companyService->getUserRole($currentCustomerId, (int) $company->getId());
        if ($role !== Company::ROLE_ADMIN) {
            return $result->setData(['success' => false, 'message' => __('Apenas administradores podem gerenciar usuários.')]);
        }

        $action = (string) $this->request->getParam('action');
        $targetCustomerId = (int) $this->request->getParam('customer_id');
        $companyId = (int) $company->getId();

        try {
            return $this->processAction($result, $action, $companyId, $targetCustomerId, $currentCustomerId);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => __('Erro ao processar a solicitação.')]);
        }
    }

    private function processAction(
        Json $result,
        string $action,
        int $companyId,
        int $targetCustomerId,
        int $currentCustomerId
    ): Json {
        switch ($action) {
            case 'add':
                $targetCustomerId = $this->resolveTargetCustomerId($targetCustomerId);
                if ($targetCustomerId === $currentCustomerId) {
                    throw new LocalizedException(__('Você já faz parte desta empresa.'));
                }

                $newRole = (string) $this->request->getParam('role', Company::ROLE_BUYER);
                if (!array_key_exists($newRole, Company::getRoles())) {
                    $newRole = Company::ROLE_BUYER;
                }

                $this->companyService->addUser($companyId, $targetCustomerId, $newRole);

                return $result->setData(['success' => true, 'message' => __('Usuário adicionado.')]);

            case 'remove':
                if ($targetCustomerId <= 0) {
                    throw new LocalizedException(__('Usuário inválido para remoção.'));
                }
                if ($targetCustomerId === $currentCustomerId) {
                    throw new LocalizedException(__('Você não pode remover seu próprio acesso.'));
                }

                $this->companyService->removeUser($companyId, $targetCustomerId);

                return $result->setData(['success' => true, 'message' => __('Usuário removido.')]);

            case 'update_role':
                if ($targetCustomerId <= 0) {
                    throw new LocalizedException(__('Usuário inválido para atualização.'));
                }

                $updatedRole = (string) $this->request->getParam('role', Company::ROLE_BUYER);
                if (!array_key_exists($updatedRole, Company::getRoles())) {
                    throw new LocalizedException(__('Papel inválido.'));
                }

                $this->companyService->updateUserRole($companyId, $targetCustomerId, $updatedRole);

                return $result->setData(['success' => true, 'message' => __('Papel atualizado.')]);

            default:
                return $result->setData(['success' => false, 'message' => __('Ação inválida.')]);
        }
    }

    private function resolveTargetCustomerId(int $targetCustomerId): int
    {
        if ($targetCustomerId > 0) {
            return $targetCustomerId;
        }

        $email = trim((string) $this->request->getParam('email', ''));
        if ($email === '') {
            throw new LocalizedException(__('Informe o e-mail do usuário.'));
        }

        try {
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $customer = $this->customerRepository->get($email, $websiteId);

            return (int) $customer->getId();
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Nenhum cliente encontrado com o e-mail informado.'));
        }
    }
}
