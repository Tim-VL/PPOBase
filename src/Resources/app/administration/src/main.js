/**
 * PPOBase Administration Module - Main Entry Point
 */

// Initialize services first
import './init/ppobase-service.init';

// Import modules
import './module/ppobase-supplier';
import './module/ppobase-purchase-order';
import './module/ppobase-goods-receipt';
import './module/ppobase-activity-log';
import './module/ppobase-grouped-product';
import './module/ppobase-po-history';
import './module/ppobase-sales-report';
import './module/ppobase-manual-order';
import './module/ppobase-tab-positions';

import './extension/sw-extension-card-base';
import './extension/sw-settings-index';
import './extension/sw-product-detail';
import './extension/sw-product-detail/ppobase-product-tabs';
import './extension/sw-product-detail-base';
import './extension/sw-product-deliverability-form';
import './extension/sw-product-list';

import './component/ppobase-product/ppobase-product-stock-bookings';
import './component/ppobase-product/ppobase-product-purchase';
import './component/ppobase-product/ppobase-product-packaging';
import './component/ppobase-product/ppobase-product-stock-history';
import './component/ppobase-product/ppobase-product-grouped';
