<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Adminhtml\Topic;

use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
use GrupoAwamotos\HelpCenter\Model\TopicFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_HelpCenter::helpcenter_manage';

    public function __construct(
        Context $context,
        private readonly TopicRepositoryInterface $repository,
        private readonly TopicFactory $factory,
        private readonly Escaper $escaper,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (empty($data)) {
            return $redirect->setPath('*/*/index');
        }

        try {
            $id = (int) ($data['topic_id'] ?? 0);
            $model = $id ? $this->repository->getById($id) : $this->factory->create();
            $tourSteps = $this->normalizeTourSteps($data['tour_steps'] ?? null);

            $model->setCategoryId(($data['category_id'] ?? '') !== '' ? (int) $data['category_id'] : null);
            $model->setTitle((string) ($data['title'] ?? ''));
            $model->setSummary($this->sanitizePlainText($data['summary'] ?? null));
            $model->setContent($this->sanitizeContent($data['content'] ?? null));
            $model->setKeywords($this->sanitizePlainText($data['keywords'] ?? null));
            $model->setAudience((string) ($data['audience'] ?? 'all'));
            $model->setRoutePattern($this->sanitizePlainText($data['route_pattern'] ?? null));
            $model->setSortOrder((int) ($data['sort_order'] ?? 0));
            $model->setStatus((int) ($data['status'] ?? 1));
            $model->setTourSteps($tourSteps);

            $this->repository->save($model);
            $this->messageManager->addSuccessMessage(__('Tópico salvo com sucesso.'));

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $redirect->setPath('*/*/edit', ['topic_id' => $model->getTopicId()]);
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $back = $this->getRequest()->getParam('topic_id')
                ? ['topic_id' => $this->getRequest()->getParam('topic_id')]
                : [];
            return $redirect->setPath('*/*/edit', $back);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro ao salvar tópico.'));
        }

        return $redirect->setPath('*/*/index');
    }

    private function sanitizePlainText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = trim(strip_tags((string) $value));
        return $clean !== '' ? $clean : null;
    }

    private function sanitizeContent(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = $this->escaper->escapeHtml((string) $value, [
            'p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i',
            'h2', 'h3', 'h4', 'blockquote', 'code', 'a', 'span',
        ]);

        return trim($clean) !== '' ? $clean : null;
    }

    /**
     * @throws LocalizedException
     */
    private function normalizeTourSteps(mixed $tourSteps): ?string
    {
        if ($tourSteps === null || trim((string) $tourSteps) === '') {
            return null;
        }

        $decoded = json_decode((string) $tourSteps, true);
        if (!is_array($decoded)) {
            throw new LocalizedException(__('JSON inválido em passos do tour.'));
        }

        return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
