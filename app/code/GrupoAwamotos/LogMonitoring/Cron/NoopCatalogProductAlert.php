<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Cron;

/**
 * No-op for Magento's legacy DB job code "catalog_product_alert".
 *
 * Magento_ProductAlert registers the real worker as job "product_alert" and stores
 * the cron expression under crontab/default/jobs/catalog_product_alert/schedule/cron_expr.
 * The DB reader also materializes a sibling job named "catalog_product_alert" with only
 * that schedule (no instance/method), which then fails with "No callbacks found".
 *
 * Declaring this job in crontab.xml supplies a safe callback so the phantom schedule
 * does not error, while product_alert continues to run Magento\ProductAlert\Model\Observer.
 */
class NoopCatalogProductAlert
{
    /**
     * Intentionally empty — real alert processing runs via job "product_alert".
     */
    public function execute(): void
    {
    }
}
