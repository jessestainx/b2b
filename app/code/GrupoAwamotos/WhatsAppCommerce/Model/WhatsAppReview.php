<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\ReviewInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Review\Model\ReviewFactory;
use Magento\Review\Model\RatingFactory;
use Magento\Review\Model\Review;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WhatsAppReview implements ReviewInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly ReviewFactory $reviewFactory,
        private readonly RatingFactory $ratingFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function saveReview(
        string $phone,
        string $sku,
        int $rating,
        string $text,
        ?string $nickname = null
    ): array {
        $rating = max(1, min(5, $rating));

        try {
            $product = $this->productRepository->get($sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['success' => false, 'message' => "Produto SKU '{$sku}' nao encontrado."];
        }

        $customer = $this->findCustomerByPhone($phone);
        $customerId = $customer ? (int) $customer->getId() : 0;

        if ($nickname === null && $customer) {
            $nickname = $customer->getFirstname();
        }
        if (empty($nickname)) {
            $nickname = 'Cliente WhatsApp';
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();

            /** @var Review $review */
            $review = $this->reviewFactory->create();
            $review->setData([
                'nickname' => $nickname,
                'title' => sprintf('Avaliação de %s via WhatsApp', $product->getName()),
                'detail' => $text,
                'entity_id' => $review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE),
                'entity_pk_value' => (int) $product->getId(),
                'status_id' => Review::STATUS_PENDING,
                'store_id' => $storeId,
                'stores' => [$storeId],
            ]);

            if ($customerId > 0) {
                $review->setCustomerId($customerId);
            }

            $review->save();
            $review->aggregate();

            // Save rating
            $connection = $this->resource->getConnection();
            $ratingTable = $this->resource->getTableName('rating');

            $ratingId = (int) $connection->fetchOne(
                $connection->select()->from($ratingTable, ['rating_id'])->limit(1)
            );

            if ($ratingId > 0) {
                $ratingOptionTable = $this->resource->getTableName('rating_option');
                $optionId = (int) $connection->fetchOne(
                    $connection->select()
                        ->from($ratingOptionTable, ['option_id'])
                        ->where('rating_id = ?', $ratingId)
                        ->where('value = ?', $rating)
                );

                if ($optionId > 0) {
                    $ratingModel = $this->ratingFactory->create();
                    $ratingModel->setRatingId($ratingId)
                        ->setReviewId((int) $review->getId())
                        ->addOptionVote($optionId, (int) $product->getId());
                }
            }

            $this->logger->info('Review saved via WhatsApp', [
                'review_id' => $review->getId(),
                'product_id' => $product->getId(),
                'sku' => $sku,
                'rating' => $rating,
                'customer_id' => $customerId,
            ]);

            return [
                'success' => true,
                'review_id' => (int) $review->getId(),
                'status' => 'pending_approval',
                'message' => 'Obrigado pela avaliacao! Ela sera publicada apos aprovacao.',
            ];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppReview::saveReview error', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Erro ao salvar avaliacao. Tente novamente.'];
        }
    }

    private function findCustomerByPhone(string $phone): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (strlen($cleanPhone) >= 13 && str_starts_with($cleanPhone, '55')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $lastDigits = substr($cleanPhone, -8);
        if (strlen($lastDigits) < 8) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $sql = $connection->select()
            ->from(['a' => $this->resource->getTableName('customer_address_entity')], ['parent_id'])
            ->where(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.telephone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?",
                '%' . $lastDigits
            )
            ->limit(1);
        $customerId = (int) $connection->fetchOne($sql);

        if (!$customerId) {
            $collection = $this->customerCollectionFactory->create();
            $collection->addAttributeToFilter('b2b_phone', ['like' => "%" . $lastDigits . "%"]);
            $collection->setPageSize(1);
            $customerId = (int) $collection->getFirstItem()->getId();
        }

        if (!$customerId) {
            return null;
        }

        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
