/**
 * PPOBase API Service
 */

const ApiService = Shopware.Classes.ApiService;

class PpobaseApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'ppobase') {
        super(httpClient, loginService, apiEndpoint);
    }

    getPurchaseOrder(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/purchase-order/${id}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getPurchaseOrderHistory(id, limit = 50) {
        const apiRoute = `/_action/${this.getApiBasePath()}/purchase-order/${id}/history`;
        return this.httpClient.get(apiRoute, { params: { limit }, headers: this.getBasicHeaders() }).then(response => response.data);
    }

    exportPurchaseOrderPdf(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/purchase-order/${id}/export/pdf`;
        return this.httpClient.get(apiRoute, { responseType: 'blob', headers: this.getBasicHeaders() }).then(response => response.data);
    }

    exportPurchaseOrderHtml(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/purchase-order/${id}/export/html`;
        return this.httpClient.get(apiRoute, { responseType: 'blob', headers: this.getBasicHeaders() }).then(response => response.data);
    }

    /**
     * Get pre-filled email data for the compose modal.
     * Returns: { to, cc, bcc, subject, body, senderEmail, senderName, supplierName, attachmentFilename }
     */
    getEmailPreview(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/purchase-order/${id}/email-preview`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    /**
     * Send PO email with optional overrides from the compose modal.
     * @param {string} id - Purchase Order ID
     * @param {Object} emailData - { to, cc, bcc, subject, body }
     */
    sendPurchaseOrderEmail(id, emailData = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/purchase-order/${id}/send-email`;
        return this.httpClient.post(apiRoute, emailData, { headers: this.getBasicHeaders() })
            .then(response => response.data)
            .catch(error => {
                if (error.response && error.response.data) {
                    return error.response.data;
                }
                throw error;
            });
    }

    getActivityLogs(entityType, entityId, limit = 50) {
        const apiRoute = `/_action/${this.getApiBasePath()}/activity-log/entity/${entityType}/${entityId}`;
        return this.httpClient.get(apiRoute, { params: { limit }, headers: this.getBasicHeaders() }).then(response => response.data);
    }

    listActivityLogs(params = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/activity-log`;
        return this.httpClient.get(apiRoute, { params, headers: this.getBasicHeaders() }).then(response => response.data);
    }

    createGoodsReceipt(purchaseOrderId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/goods-receipt/${purchaseOrderId}/create`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getGoodsReceipt(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/goods-receipt/${id}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    bookGoodsReceipt(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/goods-receipt/${id}/book`;
        return this.httpClient.post(apiRoute, {}, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    cancelGoodsReceipt(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/goods-receipt/${id}/cancel`;
        return this.httpClient.post(apiRoute, {}, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getGoodsReceiptsByPo(purchaseOrderId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/goods-receipt/by-po/${purchaseOrderId}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getProductExtension(productId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    saveProductExtension(productId, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    createStockBooking(productId, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/stock-booking`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getStockBookings(productId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/stock-bookings`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getProductPurchaseOrders(productId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/purchase-orders`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getProductGoodsReceipts(productId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/goods-receipts`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getProductSuppliers(productId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/suppliers`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    addProductSupplier(productId, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/suppliers`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    removeProductSupplier(productId, supplierProductId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/suppliers/${supplierProductId}`;
        return this.httpClient.delete(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    getProductStockHistory(productId, includeAll) {
        const params = includeAll ? '?includeAll=true' : '';
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/stock-history${params}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    addProductToPo(productId, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/product-extension/${productId}/add-to-po`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(response => response.data);
    }

    // Grouped Products
    getGroupedProductParents() {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/parents`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getGroupedProducts(parentProductId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/list/${parentProductId}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    assignGroupedProducts(parentProductId, items) {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/assign`;
        return this.httpClient.post(apiRoute, { parentProductId, items }, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    updateGroupedProduct(id, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/update/${id}`;
        return this.httpClient.patch(apiRoute, data, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    removeGroupedProduct(id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/remove/${id}`;
        return this.httpClient.delete(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    removeAllGroupedProducts(parentProductId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/remove-all/${parentProductId}`;
        return this.httpClient.delete(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    toggleGroupedProductActive(parentProductId, active) {
        const apiRoute = `/_action/${this.getApiBasePath()}/grouped-products/toggle-active/${parentProductId}`;
        return this.httpClient.post(apiRoute, { active }, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    // Sales Reports
    getCustomersByOrders(params) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/customers-by-orders`;
        return this.httpClient.get(apiRoute, { params, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getItemsBySales(params) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/items-by-sales`;
        return this.httpClient.get(apiRoute, { params, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getSalesByCustomer(customerId, params) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/sales-by-customer/${customerId}`;
        return this.httpClient.get(apiRoute, { params, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getSalesByItem(productId, params) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/sales-by-item/${productId}`;
        return this.httpClient.get(apiRoute, { params, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getOrdersOverview(params) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/orders-overview`;
        return this.httpClient.get(apiRoute, { params, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    searchCustomersForReport(term) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/customer-search`;
        return this.httpClient.get(apiRoute, { params: { term }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    searchProductsForReport(term) {
        const apiRoute = `/_action/${this.getApiBasePath()}/sales-report/product-search`;
        return this.httpClient.get(apiRoute, { params: { term }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    // Manual Sales Orders
    createManualOrder(data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/create`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    createInlineCustomer(data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/create-customer`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    createCustomerAddress(customerId, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/create-address/${customerId}`;
        return this.httpClient.post(apiRoute, data, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    searchOrderCustomers(term) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/search-customers`;
        return this.httpClient.get(apiRoute, { params: { term }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    searchOrderProducts(term, salesChannelId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/search-products`;
        return this.httpClient.get(apiRoute, { params: { term, salesChannelId }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getSalesChannels() {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/sales-channels`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getShippingMethods(salesChannelId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/shipping-methods`;
        return this.httpClient.get(apiRoute, { params: { salesChannelId }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getPaymentMethods(salesChannelId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/payment-methods`;
        return this.httpClient.get(apiRoute, { params: { salesChannelId }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getCustomerAddresses(customerId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/customer-addresses/${customerId}`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getRecentManualOrders(term = '', page = 1, limit = 50) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/recent`;
        return this.httpClient.get(apiRoute, { params: { term, page, limit }, headers: this.getBasicHeaders() }).then(r => r.data);
    }

    exportManualOrderExcel(orderId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/${orderId}/export-excel`;
        return this.httpClient.get(apiRoute, { responseType: 'blob', headers: this.getBasicHeaders() }).then(r => r.data);
    }

    bulkExportManualOrdersCsv({ ids = [], term = '', limit = 1000 } = {}) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/bulk-export-csv`;
        return this.httpClient.post(apiRoute, { ids, term, limit }, { responseType: 'blob', headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getManualOrderPrintData(orderId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/${orderId}/print-data`;
        return this.httpClient.get(apiRoute, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    updateManualOrder(orderId, data) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/${orderId}/update`;
        return this.httpClient.patch(apiRoute, data, { headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getManualOrderInvoiceHtml(orderId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/${orderId}/invoice-html`;
        return this.httpClient.get(apiRoute, { responseType: 'blob', headers: this.getBasicHeaders() }).then(r => r.data);
    }

    getManualOrderDeliveryNoteHtml(orderId) {
        const apiRoute = `/_action/${this.getApiBasePath()}/manual-order/${orderId}/delivery-note-html`;
        return this.httpClient.get(apiRoute, { responseType: 'blob', headers: this.getBasicHeaders() }).then(r => r.data);
    }

    createShopwareDocument(orderId, documentType, config = {}) {
        return this.httpClient.post(
            `/_action/order/document/${documentType}/create`,
            [{ orderId, config, referencedDocumentId: null }],
            { headers: this.getBasicHeaders() }
        ).then(r => {
            const data = r.data && r.data.data;
            const doc = Array.isArray(data) ? data[0] : data;
            if (!doc || !doc.documentId || !doc.documentDeepLink) {
                return Promise.reject(new Error('Document creation failed'));
            }
            return doc;
        });
    }

    downloadShopwareDocument(documentId, deepLink) {
        return this.httpClient.get(
            `/_action/document/${documentId}/${deepLink}?fileType=pdf&download=1`,
            { responseType: 'blob', headers: this.getBasicHeaders() }
        ).then(r => r.data);
    }
}

export default PpobaseApiService;
