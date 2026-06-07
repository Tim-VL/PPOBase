<?php declare(strict_types=1);

namespace PPOBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * PPOBase - Main Plugin Class
 * 
 * @package PPOBase
 * @author Priyanshu Nandan
 */
class PPOBase extends Plugin
{
    /**
     * Called when the plugin is installed
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
    }

    /**
     * Called when the plugin is uninstalled
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        $this->deleteSystemConfig($connection);
        $this->cleanupOrderCustomFields($connection);
        $this->dropDatabaseTables($connection);
    }

    /**
     * Drops all custom database tables created by this plugin
     */
    private function dropDatabaseTables(Connection $connection): void
    {
        // Drop tables in correct order (child tables first due to foreign keys)
        $tables = [
            'ppobase_grouped_product',     // must drop before product (FK to product.id)
            'ppobase_goods_receipt_item',
            'ppobase_purchase_order_item',
            'ppobase_goods_receipt',
            'ppobase_supplier_product',
            'ppobase_purchase_order',
            'ppobase_supplier',
            'ppobase_stock_booking',
            'ppobase_product_extension',
            'ppobase_activity_log',
        ];

        foreach ($tables as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }

    /**
     * Remove all plugin configuration values from system_config.
     */
    private function deleteSystemConfig(Connection $connection): void
    {
        // Shopware stores plugin config under "<PluginName>.config.<fieldName>".
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` LIKE :prefix',
            ['prefix' => 'PPOBase.config.%']
        );
    }

    /**
     * Remove plugin-specific customFields from core entities (manual order feature).
     */
    private function cleanupOrderCustomFields(Connection $connection): void
    {
        // Only touch orders that have any of our keys.
        // JSON functions are available in Shopware-supported MySQL/MariaDB versions.
        $jsonPaths = [
            '$.ppobase_manual_order',
            '$.ppobase_order_reference',
            '$.ppobase_order_notes',
            '$.ppobase_shipping_date',
            '$.ppobase_shipping_method_id',
            '$.ppobase_shipping_method_name',
        ];

        $whereParts = [];
        foreach ($jsonPaths as $path) {
            $whereParts[] = "JSON_CONTAINS_PATH(`custom_fields`, 'one', " . $connection->quote($path) . ')';
        }
        $where = implode(' OR ', $whereParts);

        $connection->executeStatement(
            "UPDATE `order`
             SET `custom_fields` = JSON_REMOVE(`custom_fields`, " . implode(', ', array_map(fn (string $p) => $connection->quote($p), $jsonPaths)) . ")
             WHERE `custom_fields` IS NOT NULL AND ($where)"
        );
    }
}
