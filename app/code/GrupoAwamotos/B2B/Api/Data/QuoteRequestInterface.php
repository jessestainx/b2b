<?php

/**
 * Quote Request Data Interface
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api\Data;

interface QuoteRequestInterface
{
    public const REQUEST_ID = 'request_id';
    public const CUSTOMER_ID = 'customer_id';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const CUSTOMER_NAME = 'customer_name';
    public const COMPANY_NAME = 'company_name';
    public const CNPJ = 'cnpj';
    public const PHONE = 'phone';
    public const STATUS = 'status';
    public const ITEMS_JSON = 'items_json';
    public const MESSAGE = 'message';
    public const ADMIN_NOTES = 'admin_notes';
    public const QUOTED_TOTAL = 'quoted_total';
    public const QUOTE_ID = 'quote_id';
    public const ORDER_ID = 'order_id';
    public const EXPIRES_AT = 'expires_at';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONVERTED = 'converted';

    /**
     * Get request ID
     *
     * @return int|null
     */
    public function getRequestId(): ?int;

    /**
     * Set request ID
     *
     * @param int $requestId
     * @return $this
     */
    public function setRequestId(int $requestId);

    /**
     * Get customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * Set customer ID
     *
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId(?int $customerId);

    /**
     * Get customer email
     *
     * @return string
     */
    public function getCustomerEmail(): string;

    /**
     * Set customer email
     *
     * @param string $email
     * @return $this
     */
    public function setCustomerEmail(string $email);

    /**
     * Get customer name
     *
     * @return string
     */
    public function getCustomerName(): string;

    /**
     * Set customer name
     *
     * @param string $name
     * @return $this
     */
    public function setCustomerName(string $name);

    /**
     * Get company name
     *
     * @return string|null
     */
    public function getCompanyName(): ?string;

    /**
     * Set company name
     *
     * @param string|null $companyName
     * @return $this
     */
    public function setCompanyName(?string $companyName);

    /**
     * Get CNPJ
     *
     * @return string|null
     */
    public function getCnpj(): ?string;

    /**
     * Set CNPJ
     *
     * @param string|null $cnpj
     * @return $this
     */
    public function setCnpj(?string $cnpj);

    /**
     * Get phone
     *
     * @return string|null
     */
    public function getPhone(): ?string;

    /**
     * Set phone
     *
     * @param string|null $phone
     * @return $this
     */
    public function setPhone(?string $phone);

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status);

    /**
     * Get items JSON
     *
     * @return string
     */
    public function getItemsJson(): string;

    /**
     * Set items JSON
     *
     * @param string $itemsJson
     * @return $this
     */
    public function setItemsJson(string $itemsJson);

    /**
     * Get items as array
     *
     * @return array
     */
    public function getItems(): array;

    /**
     * Set items from array
     *
     * @param array $items
     * @return $this
     */
    public function setItems(array $items);

    /**
     * Get message
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * Set message
     *
     * @param string|null $message
     * @return $this
     */
    public function setMessage(?string $message);

    /**
     * Get admin notes
     *
     * @return string|null
     */
    public function getAdminNotes(): ?string;

    /**
     * Set admin notes
     *
     * @param string|null $adminNotes
     * @return $this
     */
    public function setAdminNotes(?string $adminNotes);

    /**
     * Get quoted total
     *
     * @return float|null
     */
    public function getQuotedTotal(): ?float;

    /**
     * Set quoted total
     *
     * @param float|null $quotedTotal
     * @return $this
     */
    public function setQuotedTotal(?float $quotedTotal);

    /**
     * Get Magento quote ID
     *
     * @return int|null
     */
    public function getQuoteId(): ?int;

    /**
     * Set Magento quote ID
     *
     * @param int|null $quoteId
     * @return $this
     */
    public function setQuoteId(?int $quoteId);

    /**
     * Get order ID
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set order ID
     *
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId);

    /**
     * Get expiration date
     *
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * Set expiration date
     *
     * @param string|null $expiresAt
     * @return $this
     */
    public function setExpiresAt(?string $expiresAt);

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
