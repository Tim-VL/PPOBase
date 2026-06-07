/**
 * Purchase Order Detail Page
 *
 * EMAIL MODAL: Built with pure DOM (no Vue template).
 * sw-text-editor triggers the Vue 3 runtime template compiler on email HTML,
 * causing SyntaxError: 10. The DOM modal avoids this entirely.
 */

import template from './ppobase-purchase-order-detail.html.twig';
import './ppobase-purchase-order-detail.scss';

var ShopwareComponent = Shopware.Component;
var ShopwareMixin = Shopware.Mixin;
var ShopwareCriteria = Shopware.Data.Criteria;

ShopwareComponent.register('ppobase-purchase-order-detail', {
    template: template,

    inject: ['repositoryFactory', 'ppobaseApiService'],

    mixins: [ShopwareMixin.getByName('notification')],

    props: {
        purchaseOrderId: { type: String, required: true }
    },

    data: function() {
        return {
            purchaseOrder: null,
            isLoading: false,
            isSaving: false,
            isExporting: false,
            hasChanges: false,
            showAddItemModal: false,
            selectedProducts: [],
            availableProducts: null,
            isLoadingProducts: false,
            productSearchQuery: '',
            itemSortBy: 'lineNumber',
            itemSortDirection: 'ASC',
            showUnsavedChangesModal: false,
            unsavedChangesAction: null,
            activeTab: 'items',
            goodsReceipts: [],
            isLoadingReceipts: false
        };
    },

    computed: {
        purchaseOrderRepository: function() { return this.repositoryFactory.create('ppobase_purchase_order'); },
        purchaseOrderItemRepository: function() { return this.repositoryFactory.create('ppobase_purchase_order_item'); },
        supplierProductRepository: function() { return this.repositoryFactory.create('ppobase_supplier_product'); },

        pageTitle: function() {
            return this.purchaseOrder
                ? (this.purchaseOrder.poNumber || this.$tc('ppobase-purchase-order.detail.title'))
                : this.$tc('ppobase-purchase-order.detail.title');
        },

        configFromState: function() {
            return Shopware.State.get('systemConfig') || {};
        },

        configShowSupplierAddress: function() {
            var v = this.configFromState['PPOBase.config.showSupplierAddress'];
            return (v === undefined) ? true : Boolean(v);
        },

        configShowEan: function() {
            var v = this.configFromState['PPOBase.config.showEan'];
            return (v === undefined) ? false : Boolean(v);
        },

        configShowMpn: function() {
            var v = this.configFromState['PPOBase.config.showMpn'];
            return (v === undefined) ? false : Boolean(v);
        },

        configShowTaxColumn: function() {
            var v = this.configFromState['PPOBase.config.showTaxColumn'];
            return (v === undefined) ? true : Boolean(v);
        },

        isEmailDeliveryDisabled: function() {
            var v = this.configFromState['core.mailerSettings.disableDelivery'];
            return v === true || v === 1 || v === '1' || v === 'true';
        },

        itemColumns: function() {
            var cols = [
                { property: 'lineNumber', label: '#', width: '60px', allowResize: true },
                { property: 'productNumber', label: this.$tc('ppobase-purchase-order.detail.columnSku'), width: '120px', allowResize: true, sortable: true },
                { property: 'ean', label: this.$tc('ppobase-purchase-order.detail.columnEan'), width: '100px', allowResize: true },
                { property: 'mpn', label: this.$tc('ppobase-purchase-order.detail.columnMpn'), width: '100px', allowResize: true },
                { property: 'productName', label: this.$tc('ppobase-purchase-order.detail.columnProduct'), primary: true, width: '220px', allowResize: true },
                { property: 'quantityOrdered', label: this.$tc('ppobase-purchase-order.detail.columnQty'), width: '130px', align: 'center', allowResize: true },
                { property: 'unitPrice', label: this.$tc('ppobase-purchase-order.detail.columnUnitPrice'), width: '150px', align: 'right', allowResize: true }
            ];
            if (this.configShowTaxColumn) {
                cols.push({ property: 'taxPercent', label: this.$tc('ppobase-purchase-order.detail.columnTaxPercent'), width: '80px', align: 'center', allowResize: true });
                cols.push({ property: 'taxAmount', label: this.$tc('ppobase-purchase-order.detail.columnTaxAmount'), width: '80px', align: 'right', allowResize: true });
            }
            cols.push({ property: 'lineTotal', label: this.$tc('ppobase-purchase-order.detail.columnLineTotal'), width: '110px', align: 'right', allowResize: true });
            return cols;
        },

        isEditable: function() { return this.purchaseOrder && this.purchaseOrder.status === 'draft'; },

        canReceiveGoods: function() {
            return this.purchaseOrder && (this.purchaseOrder.status === 'sent' || this.purchaseOrder.status === 'partial');
        },

        supplierVatPercent: function() {
            if (this.purchaseOrder && this.purchaseOrder.supplier && this.purchaseOrder.supplier.vatPercent) {
                return this.purchaseOrder.supplier.vatPercent;
            }
            return 0;
        },

        emailSentInfo: function() {
            if (!this.purchaseOrder || !this.purchaseOrder.emailSent) return null;
            return {
                to: this.purchaseOrder.emailSentTo || '-',
                at: this.purchaseOrder.emailSentAt
                    ? new Date(this.purchaseOrder.emailSentAt).toLocaleString()
                    : '-'
            };
        },

        filteredProducts: function() {
            if (!this.availableProducts) return [];
            var q = (this.productSearchQuery || '').toLowerCase().trim();
            if (!q) return this.availableProducts;
            return this.availableProducts.filter(function(p) {
                return (p.name && p.name.toLowerCase().includes(q))
                    || (p.productNumber && p.productNumber.toLowerCase().includes(q));
            });
        },

        sortedItems: function() {
            if (!this.purchaseOrder || !this.purchaseOrder.items) return [];
            var items = this.purchaseOrder.items.slice();
            var key = this.itemSortBy;
            var dir = this.itemSortDirection === 'ASC' ? 1 : -1;
            items.sort(function(a, b) {
                var va = (a[key] !== null && a[key] !== undefined) ? String(a[key]).toLowerCase() : '';
                var vb = (b[key] !== null && b[key] !== undefined) ? String(b[key]).toLowerCase() : '';
                if (va < vb) return -1 * dir;
                if (va > vb) return 1 * dir;
                return 0;
            });
            return items;
        },

        statusOptions: function() {
            return [
                { value: 'draft', label: this.$tc('ppobase-purchase-order.status.draft') },
                { value: 'sent', label: this.$tc('ppobase-purchase-order.status.sent') },
                { value: 'partial', label: this.$tc('ppobase-purchase-order.status.partial') },
                { value: 'received', label: this.$tc('ppobase-purchase-order.status.received') },
                { value: 'cancelled', label: this.$tc('ppobase-purchase-order.status.cancelled') }
            ];
        }
    },

    created: function() {
        this.loadPurchaseOrder();
    },

    beforeRouteLeave: function(to, from, next) {
        if (this.hasChanges) {
            this.unsavedChangesAction = next;
            this.showUnsavedChangesModal = true;
            return;
        }
        next();
    },

    beforeUnmount: function() {
        this.destroyEmailModal();
    },

    methods: {

        onItemColumnSort: function(column) {
            if (this.itemSortBy === column.property) {
                this.itemSortDirection = this.itemSortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.itemSortBy = column.property;
                this.itemSortDirection = 'ASC';
            }
        },

        loadPurchaseOrder: async function() {
            this.isLoading = true;
            try {
                var criteria = new ShopwareCriteria();
                criteria.addAssociation('supplier');
                criteria.addAssociation('salesChannel');
                criteria.addAssociation('items');
                criteria.addAssociation('items.product');
                criteria.getAssociation('items').addSorting(ShopwareCriteria.sort('lineNumber', 'ASC'));
                this.purchaseOrder = await this.purchaseOrderRepository.get(this.purchaseOrderId, Shopware.Context.api, criteria);
                this.hasChanges = false;
                this.loadGoodsReceipts();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.detail.errorTitle'), message: error.message });
            } finally {
                this.isLoading = false;
            }
        },

        onSave: async function() {
            if (this.purchaseOrder && this.purchaseOrder.items) {
                for (var i = this.purchaseOrder.items.length - 1; i >= 0; i--) {
                    if ((this.purchaseOrder.items[i].quantityOrdered || 0) === 0) {
                        this.purchaseOrder.items.splice(i, 1);
                    }
                }
            }
            this.isSaving = true;
            try {
                this.recalculateTotals();
                await this.purchaseOrderRepository.save(this.purchaseOrder, Shopware.Context.api);
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-purchase-order.detail.saveSuccessTitle'),
                    message: this.$tc('ppobase-purchase-order.detail.saveSuccessMessage')
                });
                this.hasChanges = false;
                await this.loadPurchaseOrder();
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.detail.saveErrorTitle'), message: error.message });
            } finally {
                this.isSaving = false;
            }
        },

        onSavePurchaseOrder: function() {
            if (this.purchaseOrder && this.purchaseOrder.items) {
                for (var i = this.purchaseOrder.items.length - 1; i >= 0; i--) {
                    if ((this.purchaseOrder.items[i].quantityOrdered || 0) === 0) {
                        this.purchaseOrder.items.splice(i, 1);
                    }
                }
            }
            this.recalculateTotals();
            this.isSaving = true;
            this.purchaseOrderRepository.save(this.purchaseOrder, Shopware.Context.api).then(() => {
                this.isSaving = false;
                this.createNotificationSuccess({
                    title: this.$tc('ppobase-purchase-order.detail.saveSuccessTitle'),
                    message: this.$tc('ppobase-purchase-order.detail.saveSuccessMessage')
                });
                this.hasChanges = false;
            }).catch((err) => {
                this.isSaving = false;
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.detail.saveErrorTitle'),
                    message: err.message || this.$tc('ppobase-purchase-order.detail.saveErrorMessage')
                });
            });
        },

        recalculateTotals: function() {
            if (!this.purchaseOrder.items) return;
            var subtotal = 0;
            var itemCount = 0;
            var vatPercent = this.supplierVatPercent;
            this.purchaseOrder.items.forEach(function(item, index) {
                item.lineNumber = index + 1;
                item.lineTotal = (item.quantityOrdered || 0) * (item.unitPrice || 0);
                item.taxRate = vatPercent;
                item.taxAmount = Math.round(item.lineTotal * (vatPercent / 100) * 100) / 100;
                subtotal += item.lineTotal;
                itemCount++;
            });
            this.purchaseOrder.subtotal = subtotal;
            var taxAmount = subtotal * (vatPercent / 100);
            this.purchaseOrder.taxAmount = Math.round(taxAmount * 100) / 100;
            this.purchaseOrder.total = this.purchaseOrder.subtotal + this.purchaseOrder.taxAmount;
            this.purchaseOrder.itemCount = itemCount;
        },

        onExportPdf: async function() {
            this.isExporting = true;
            try {
                var blob = await this.ppobaseApiService.exportPurchaseOrderPdf(this.purchaseOrderId);
                var supplierName = (this.purchaseOrder && this.purchaseOrder.supplier) ? this.purchaseOrder.supplier.companyTradeName || '' : '';
                var sanitized = supplierName.replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, '_');
                var dateStr = new Date().toISOString().split('T')[0];
                this.downloadBlob(blob, this.purchaseOrder.poNumber + '_' + sanitized + '_' + dateStr + '.pdf');
                this.createNotificationSuccess({ title: this.$tc('ppobase-purchase-order.detail.exportSuccessTitle'), message: this.$tc('ppobase-purchase-order.detail.exportPdfSuccessMessage') });
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.detail.exportErrorTitle'), message: error.message });
            } finally {
                this.isExporting = false;
            }
        },

        onExportHtml: async function() {
            this.isExporting = true;
            try {
                var blob = await this.ppobaseApiService.exportPurchaseOrderHtml(this.purchaseOrderId);
                var supplierName = (this.purchaseOrder && this.purchaseOrder.supplier) ? this.purchaseOrder.supplier.companyTradeName || '' : '';
                var sanitized = supplierName.replace(/[^a-zA-Z0-9\s]/g, '').replace(/\s+/g, '_');
                var dateStr = new Date().toISOString().split('T')[0];
                this.downloadBlob(blob, this.purchaseOrder.poNumber + '_' + sanitized + '_' + dateStr + '.html');
                this.createNotificationSuccess({ title: this.$tc('ppobase-purchase-order.detail.exportSuccessTitle'), message: this.$tc('ppobase-purchase-order.detail.exportHtmlSuccessMessage') });
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.detail.exportErrorTitle'), message: error.message });
            } finally {
                this.isExporting = false;
            }
        },

        stripHtmlEnvelope: function(html) {
            if (!html || typeof html !== 'string') return '';
            var bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
            if (bodyMatch && bodyMatch[1]) return bodyMatch[1].trim();
            return html
                .replace(/<!doctype[^>]*>/i, '')
                .replace(/<head[^>]*>[\s\S]*?<\/head>/gi, '')
                .replace(/<html[^>]*>/gi, '')
                .replace(/<\/html>/gi, '')
                .trim();
        },

        onOpenEmailModal: async function() {
            var supplier = this.purchaseOrder ? this.purchaseOrder.supplier : null;
            var emailTo = supplier ? (supplier.purchaseEmail || supplier.companyEmail || supplier.email) : null;

            if (this.isEmailDeliveryDisabled) {
                this.createNotificationWarning({
                    title: this.$tc('ppobase-purchase-order.detail.warningTitle'),
                    message: this.$tc('ppobase-purchase-order.detail.emailDeliveryDisabledWarning')
                });
                return;
            }

            if (!emailTo) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.detail.errorTitle'),
                    message: this.$tc('ppobase-purchase-order.detail.emailNoAddress')
                });
                return;
            }

            this.buildEmailModal(emailTo);

            try {
                var result = await this.ppobaseApiService.getEmailPreview(this.purchaseOrderId);
                if (result && result.success && result.data) {
                    var bodyHtml = this.stripHtmlEnvelope(result.data.body || result.data.html || '');
                    this.fillEmailModal(result.data, emailTo, bodyHtml);
                } else {
                    this.fillEmailModal({}, emailTo, '');
                }
            } catch (err) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.emailCompose.errorTitle'),
                    message: this.$tc('ppobase-purchase-order.emailCompose.previewError')
                });
                this.destroyEmailModal();
            }
        },

        buildEmailModal: function(defaultTo) {
            this.destroyEmailModal();
            var self = this;

            function mk(tag) { return document.createElement(tag); }
            function st(node, s) { node.style.cssText = s; return node; }

            var backdrop = st(mk('div'), 'position:absolute;inset:0;background:rgba(0,0,0,0.5)');
            backdrop.addEventListener('click', function() { self.destroyEmailModal(); });

            var titleEl = st(mk('h3'), 'margin:0;font-size:18px;font-weight:600;color:#111827');
            titleEl.textContent = self.$tc('ppobase-purchase-order.emailCompose.title');
            var closeBtn = st(mk('button'), 'background:none;border:none;cursor:pointer;font-size:22px;color:#6b7280;padding:4px 8px');
            closeBtn.textContent = '×';
            closeBtn.addEventListener('click', function() { self.destroyEmailModal(); });
            var header = st(mk('div'), 'display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #e5e7eb');
            header.appendChild(titleEl);
            header.appendChild(closeBtn);

            var loader = st(mk('div'), 'text-align:center;padding:40px;color:#6b7280');
            loader.id = 'ppo-email-loader';
            loader.textContent = self.$tc('ppobase-purchase-order.emailCompose.loadingDetails');

            var form = mk('div');
            form.id = 'ppo-email-form';
            form.style.display = 'none';

            var senderInfo = st(mk('div'), 'margin:0 0 16px;color:#3b82f6;font-size:13px;background:#eff6ff;padding:10px 14px;border-radius:6px;border:1px solid #bfdbfe');
            senderInfo.id = 'ppo-email-sender';
            form.appendChild(senderInfo);

            function makeField(id, labelText, inputType, placeholder) {
                var wrap = st(mk('div'), 'margin-bottom:14px');
                var lbl = st(mk('label'), 'display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:5px');
                lbl.textContent = labelText;
                var inp = st(mk('input'), 'width:100%;box-sizing:border-box;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;outline:none;font-family:inherit');
                inp.type = inputType || 'text';
                inp.id = id;
                if (placeholder) inp.placeholder = placeholder;
                wrap.appendChild(lbl);
                wrap.appendChild(inp);
                return wrap;
            }

            form.appendChild(makeField('ppo-email-to', self.$tc('ppobase-purchase-order.emailCompose.labelTo'), 'email', self.$tc('ppobase-purchase-order.emailCompose.placeholderTo')));
            form.appendChild(makeField('ppo-email-cc', self.$tc('ppobase-purchase-order.emailCompose.labelCc'), 'text', self.$tc('ppobase-purchase-order.emailCompose.placeholderCc')));
            form.appendChild(makeField('ppo-email-bcc', self.$tc('ppobase-purchase-order.emailCompose.labelBcc'), 'text', self.$tc('ppobase-purchase-order.emailCompose.placeholderBcc')));
            form.appendChild(makeField('ppo-email-subject', self.$tc('ppobase-purchase-order.emailCompose.labelSubject'), 'text'));

            var att = st(mk('div'), 'display:none;margin-bottom:14px;padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;color:#374151');
            att.id = 'ppo-email-attachment';
            form.appendChild(att);

            var bodyLabel = st(mk('label'), 'display:block;font-size:13px;font-weight:600;color:#374151;margin:4px 0 6px');
            bodyLabel.textContent = self.$tc('ppobase-purchase-order.emailCompose.labelBody');
            form.appendChild(bodyLabel);

            // Toolbar
            var toolbar = st(mk('div'), 'display:flex;gap:4px;padding:6px 8px;background:#f3f4f6;border:1px solid #d1d5db;border-bottom:none;border-radius:6px 6px 0 0');
            function mkToolBtn(html, cmd) {
                var btn = st(mk('button'), 'padding:3px 9px;border:1px solid #d1d5db;border-radius:4px;background:#fff;font-size:13px;cursor:pointer;color:#374151;line-height:1.4');
                btn.type = 'button';
                btn.innerHTML = html;
                btn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    var editor = document.getElementById('ppo-email-body');
                    if (editor) { editor.focus(); document.execCommand(cmd, false, null); }
                });
                return btn;
            }
            toolbar.appendChild(mkToolBtn('<b>B</b>', 'bold'));
            toolbar.appendChild(mkToolBtn('<i>I</i>', 'italic'));
            toolbar.appendChild(mkToolBtn('<u>U</u>', 'underline'));
            form.appendChild(toolbar);

            // contenteditable editor — no iframe, no designMode quirks
            var editor = st(mk('div'),
                'width:100%;box-sizing:border-box;min-height:280px;max-height:380px;overflow-y:auto;' +
                'border:1px solid #d1d5db;border-radius:0 0 6px 6px;padding:14px;background:#fff;' +
                'font-size:14px;font-family:inherit;outline:none;line-height:1.6'
            );
            editor.id = 'ppo-email-body';
            editor.contentEditable = 'true';
            form.appendChild(editor);

            if (this.purchaseOrder && this.purchaseOrder.status === 'draft') {
                var note = st(mk('div'), 'margin-top:12px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:13px;color:#92400e');
                note.textContent = self.$tc('ppobase-purchase-order.emailCompose.draftNote');
                form.appendChild(note);
            }

            var bodyArea = st(mk('div'), 'padding:24px;overflow-y:auto;flex:1');
            bodyArea.appendChild(loader);
            bodyArea.appendChild(form);

            var cancelBtn = st(mk('button'), 'padding:9px 18px;border:1px solid #d1d5db;border-radius:6px;background:#fff;font-size:14px;cursor:pointer;color:#374151;font-family:inherit');
            cancelBtn.textContent = self.$tc('ppobase-purchase-order.emailCompose.buttonCancel');
            cancelBtn.addEventListener('click', function() { self.destroyEmailModal(); });

            var sendBtn = st(mk('button'), 'padding:9px 18px;border:none;border-radius:6px;background:#3b82f6;color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit');
            sendBtn.id = 'ppo-email-send-btn';
            sendBtn.textContent = self.$tc('ppobase-purchase-order.emailCompose.buttonSend');
            sendBtn.addEventListener('click', function() { self.handleSendEmail(); });

            var footer = st(mk('div'), 'display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;border-radius:0 0 8px 8px;flex-shrink:0');
            footer.appendChild(cancelBtn);
            footer.appendChild(sendBtn);

            var dialog = st(mk('div'), 'position:relative;background:#fff;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.3);width:720px;max-width:92vw;max-height:90vh;display:flex;flex-direction:column');
            dialog.appendChild(header);
            dialog.appendChild(bodyArea);
            dialog.appendChild(footer);

            var overlay = st(mk('div'), 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center');
            overlay.id = 'ppo-email-modal-overlay';
            overlay.appendChild(backdrop);
            overlay.appendChild(dialog);

            document.body.appendChild(overlay);

            var toInput = document.getElementById('ppo-email-to');
            if (toInput) toInput.value = defaultTo || '';
        },

        fillEmailModal: function(data, fallbackTo, bodyHtml) {
            var loader = document.getElementById('ppo-email-loader');
            var form = document.getElementById('ppo-email-form');
            if (!loader || !form) return;

            loader.style.display = 'none';
            form.style.display = 'block';

            var sender = document.getElementById('ppo-email-sender');
            if (sender) {
                var n = data.senderName || data.fromName || '';
                var e = data.senderEmail || data.fromAddress || '';
                sender.textContent = this.$tc('ppobase-purchase-order.emailCompose.senderPrefix') + ': ' + n + (e ? ' (' + e + ')' : '');
            }

            var toEl = document.getElementById('ppo-email-to');
            if (toEl) toEl.value = data.to || fallbackTo || '';

            var ccEl = document.getElementById('ppo-email-cc');
            if (ccEl) ccEl.value = Array.isArray(data.cc) ? data.cc.join(', ') : (data.cc || '');

            var bccEl = document.getElementById('ppo-email-bcc');
            if (bccEl) bccEl.value = Array.isArray(data.bcc) ? data.bcc.join(', ') : (data.bcc || '');

            var subjEl = document.getElementById('ppo-email-subject');
            if (subjEl) subjEl.value = data.subject || '';

            var editor = document.getElementById('ppo-email-body');
            if (editor) editor.innerHTML = bodyHtml || '';

            if (data.attachmentFilename) {
                var attEl = document.getElementById('ppo-email-attachment');
                if (attEl) {
                    attEl.textContent = this.$tc('ppobase-purchase-order.emailCompose.attachmentPrefix') + ': ' + data.attachmentFilename;
                    attEl.style.display = 'block';
                }
            }
        },

        destroyEmailModal: function() {
            var overlay = document.getElementById('ppo-email-modal-overlay');
            if (overlay) overlay.remove();
        },

        handleSendEmail: async function() {
            var toEl = document.getElementById('ppo-email-to');
            var ccEl = document.getElementById('ppo-email-cc');
            var bccEl = document.getElementById('ppo-email-bcc');
            var subjEl = document.getElementById('ppo-email-subject');
            var editor = document.getElementById('ppo-email-body');
            var sendBtn = document.getElementById('ppo-email-send-btn');

            if (this.isEmailDeliveryDisabled) {
                this.createNotificationWarning({
                    title: this.$tc('ppobase-purchase-order.detail.warningTitle'),
                    message: this.$tc('ppobase-purchase-order.detail.emailDeliveryDisabledWarning')
                });
                return;
            }

            var emailTo = toEl ? toEl.value.trim() : '';
            if (!emailTo) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.emailCompose.errorTitle'),
                    message: this.$tc('ppobase-purchase-order.emailCompose.noRecipient')
                });
                return;
            }

            if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = this.$tc('ppobase-purchase-order.emailCompose.buttonSending'); sendBtn.style.opacity = '0.6'; }

            function normalizeList(val) {
                if (!val) return '';
                return val.split(',').map(function(v) { return v.trim(); }).filter(function(v) { return v; }).join(', ');
            }

            try {
                var payload = {
                    to: emailTo,
                    cc: normalizeList(ccEl ? ccEl.value : ''),
                    bcc: normalizeList(bccEl ? bccEl.value : ''),
                    subject: subjEl ? subjEl.value.trim() : '',
                    body: editor ? editor.innerHTML : ''
                };

                var result = await this.ppobaseApiService.sendPurchaseOrderEmail(this.purchaseOrderId, payload);

                if (result && result.success) {
                    this.createNotificationSuccess({
                        title: this.$tc('ppobase-purchase-order.detail.successTitle'),
                        message: this.$tc('ppobase-purchase-order.detail.emailSentSuccess')
                    });
                    this.destroyEmailModal();
                    await this.loadPurchaseOrder();
                } else {
                    this.createNotificationError({
                        title: this.$tc('ppobase-purchase-order.detail.errorTitle'),
                        message: result && result.error ? result.error : 'Unknown error'
                    });
                    this.destroyEmailModal();
                }

            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.emailCompose.errorTitle'),
                    message: (error && error.message) ? error.message : this.$tc('ppobase-purchase-order.detail.emailSentError')
                });
                if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = this.$tc('ppobase-purchase-order.emailCompose.buttonSend'); sendBtn.style.opacity = '1'; }
            }
        },

        downloadBlob: function(blob, filename) {
            var url = window.URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        },

        openAddItemModal: async function() {
            this.selectedProducts = [];
            this.showAddItemModal = true;
            await this.loadSupplierProducts();
        },

        closeAddItemModal: function() {
            this.showAddItemModal = false;
            this.selectedProducts = [];
            this.availableProducts = null;
            this.productSearchQuery = '';
        },

        loadSupplierProducts: async function() {
            if (!this.purchaseOrder || !this.purchaseOrder.supplierId) return;
            this.isLoadingProducts = true;
            try {
                var criteria = new ShopwareCriteria();
                criteria.addFilter(ShopwareCriteria.equals('supplierId', this.purchaseOrder.supplierId));
                criteria.addAssociation('product');
                criteria.addAssociation('product.options.group');
                var context = Object.assign({}, Shopware.Context.api, { inheritance: true });
                var result = await this.supplierProductRepository.search(criteria, context);
                var self = this;
                this.availableProducts = result.map(function(sp) {
                    var product = sp.product || {};
                    var productDisplayName = self.getProductDisplayName(product);
                    var resolvedProductPrice = self.resolveProductUnitPrice(product);
                    var effectiveSupplierPrice = self.resolveEffectiveUnitPrice(sp.supplierPrice, product);

                    return Object.assign({}, sp.product, {
                        name: productDisplayName,
                        displayName: productDisplayName,
                        supplierSku: sp.supplierSku,
                        supplierPrice: effectiveSupplierPrice,
                        originalSupplierPrice: sp.supplierPrice,
                        productPrice: resolvedProductPrice,
                        ean: product.ean || '',
                        mpn: product.manufacturerNumber || ''
                    });
                });
            } catch (error) {
                this.createNotificationError({ title: this.$tc('ppobase-purchase-order.detail.errorTitle'), message: error.message });
            } finally {
                this.isLoadingProducts = false;
            }
        },

        onProductSelectionChange: function(selection) {
            this.selectedProducts = Object.values(selection);
        },

        addSelectedProducts: async function() {
            if (this.selectedProducts.length === 0) return;
            var maxLine = (this.purchaseOrder.items && this.purchaseOrder.items.length) ? this.purchaseOrder.items.length : 0;
            for (var i = 0; i < this.selectedProducts.length; i++) {
                var product = this.selectedProducts[i];
                var effectiveUnitPrice = this.resolveEffectiveUnitPrice(product.originalSupplierPrice, product);
                var newItem = this.purchaseOrderItemRepository.create(Shopware.Context.api);
                Object.assign(newItem, {
                    purchaseOrderId: this.purchaseOrder.id,
                    productId: product.id,
                    lineNumber: maxLine + i + 1,
                    productNumber: product.productNumber,
                    productName: product.displayName || product.name || product.productNumber,
                    supplierSku: product.supplierSku || '',
                    ean: product.ean || '',
                    mpn: product.manufacturerNumber || '',
                    quantityOrdered: 1,
                    quantityReceived: 0,
                    unit: 'pcs',
                    unitPrice: effectiveUnitPrice,
                    taxRate: this.supplierVatPercent,
                    taxAmount: 0,
                    lineTotal: effectiveUnitPrice
                });
                if (!this.purchaseOrder.items) this.purchaseOrder.items = [];
                this.purchaseOrder.items.push(newItem);
            }
            this.recalculateTotals();
            this.hasChanges = true;
            this.closeAddItemModal();
            this.createNotificationSuccess({ title: this.$tc('ppobase-purchase-order.detail.successTitle'), message: this.$tc('ppobase-purchase-order.detail.itemsAddedMessage') });
        },

        addManualItem: function() {
            var maxLine = (this.purchaseOrder.items && this.purchaseOrder.items.length) ? this.purchaseOrder.items.length : 0;
            var newItem = this.purchaseOrderItemRepository.create(Shopware.Context.api);
            Object.assign(newItem, {
                purchaseOrderId: this.purchaseOrder.id,
                lineNumber: maxLine + 1,
                productName: 'New Item',
                quantityOrdered: 1,
                quantityReceived: 0,
                unit: 'pcs',
                unitPrice: 0,
                taxRate: this.supplierVatPercent,
                taxAmount: 0,
                lineTotal: 0,
                ean: '',
                mpn: ''
            });
            if (!this.purchaseOrder.items) this.purchaseOrder.items = [];
            this.purchaseOrder.items.push(newItem);
            this.recalculateTotals();
            this.hasChanges = true;
        },

        removeItem: function(item) {
            var index = this.purchaseOrder.items.findIndex(function(i) { return i.id === item.id; });
            if (index > -1) {
                this.purchaseOrder.items.splice(index, 1);
                this.recalculateTotals();
                this.hasChanges = true;
            }
        },

        onItemQuantityChange: function(item) {
            item.lineTotal = (item.quantityOrdered || 0) * (item.unitPrice || 0);
            this.recalculateTotals();
            this.hasChanges = true;
        },

        onItemPriceChange: function(item) {
            item.lineTotal = (item.quantityOrdered || 0) * (item.unitPrice || 0);
            this.recalculateTotals();
            this.hasChanges = true;
        },

        resolveEffectiveUnitPrice: function(supplierPrice, product) {
            var normalizedSupplierPrice = this.normalizePriceValue(supplierPrice);
            if (normalizedSupplierPrice > 0) {
                return normalizedSupplierPrice;
            }

            return this.resolveProductUnitPrice(product);
        },

        resolveProductUnitPrice: function(product) {
            if (!product) {
                return 0;
            }

            var directCandidates = [
                product.calculatedPrice && product.calculatedPrice.unitPrice,
                product.calculatedPrice && product.calculatedPrice.totalPrice,
                product.purchasePrice,
                product.purchasePrices,
                product.price,
                product.prices,
                product.calculatedPrices
            ];

            for (var i = 0; i < directCandidates.length; i++) {
                var normalized = this.normalizePriceValue(directCandidates[i]);
                if (normalized > 0) {
                    return normalized;
                }
            }

            return 0;
        },

        normalizePriceValue: function(value) {
            if (value === null || value === undefined || value === '') {
                return 0;
            }

            if (typeof value === 'number') {
                return isNaN(value) ? 0 : value;
            }

            if (typeof value === 'string') {
                var parsed = parseFloat(value);
                return isNaN(parsed) ? 0 : parsed;
            }

            if (Array.isArray(value)) {
                for (var i = 0; i < value.length; i++) {
                    var arrayPrice = this.normalizePriceValue(value[i]);
                    if (arrayPrice > 0) {
                        return arrayPrice;
                    }
                }

                return 0;
            }

            if (typeof value === 'object') {
                var objectCandidates = [
                    value.unitPrice,
                    value.gross,
                    value.net,
                    value.price
                ];

                for (var j = 0; j < objectCandidates.length; j++) {
                    var objectPrice = this.normalizePriceValue(objectCandidates[j]);
                    if (objectPrice > 0) {
                        return objectPrice;
                    }
                }

                var objectValues = Object.values(value);
                for (var k = 0; k < objectValues.length; k++) {
                    var nestedObjectPrice = this.normalizePriceValue(objectValues[k]);
                    if (nestedObjectPrice > 0) {
                        return nestedObjectPrice;
                    }
                }
            }

            return 0;
        },

        getProductDisplayName: function(product) {
            if (!product) {
                return '';
            }

            var translated = product.translated || {};
            var name = translated.name || product.name || '';

            if (product.parentId && product.options && product.options.length) {
                var optionNames = [];
                product.options.forEach(function(option) {
                    var optionTranslated = option.translated || {};
                    var optionName = optionTranslated.name || option.name;
                    if (optionName) {
                        optionNames.push(optionName);
                    }
                });

                if (optionNames.length) {
                    return name
                        ? name + ' (' + optionNames.join(', ') + ')'
                        : product.productNumber + ' (' + optionNames.join(', ') + ')';
                }
            }

            return name || product.productNumber || '';
        },

        onFormChange: function() {
            this.hasChanges = true;
        },

        onTextareaChange: function(field, value) {
            if (this.purchaseOrder) {
                this.purchaseOrder[field] = value;
                this.hasChanges = true;
            }
        },

        formatCurrency: function(value) {
            if (!value && value !== 0) return '-';
            var currency = (this.purchaseOrder && this.purchaseOrder.currency) ? this.purchaseOrder.currency : 'EUR';
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(value);
        },

        formatDate: function(dateString) {
            return dateString ? new Date(dateString).toLocaleString() : '-';
        },

        onCancel: function() {
            var self = this;
            if (this.hasChanges) {
                this.unsavedChangesAction = function() {
                    self.$router.push({ name: 'ppobase.purchase.order.list' });
                };
                this.showUnsavedChangesModal = true;
                return;
            }
            this.$router.push({ name: 'ppobase.purchase.order.list' });
        },

        onSaveAndLeave: async function() {
            this.showUnsavedChangesModal = false;
            await this.onSave();
            if (this.unsavedChangesAction && typeof this.unsavedChangesAction === 'function') {
                this.unsavedChangesAction();
            }
            this.unsavedChangesAction = null;
        },

        onDiscardAndLeave: function() {
            this.showUnsavedChangesModal = false;
            this.hasChanges = false;
            if (this.unsavedChangesAction && typeof this.unsavedChangesAction === 'function') {
                this.unsavedChangesAction();
            }
            this.unsavedChangesAction = null;
        },

        onReceiveGoods: async function() {
            try {
                var result = await this.ppobaseApiService.createGoodsReceipt(this.purchaseOrderId);
                if (result && result.success && result.data && result.data.id) {
                    this.createNotificationSuccess({
                        title: this.$tc('ppobase-purchase-order.detail.successTitle'),
                        message: this.$tc('ppobase-purchase-order.detail.receiptCreatedMessage')
                    });
                    this.$router.push({ name: 'ppobase.goods.receipt.detail', params: { id: result.data.id } });
                } else {
                    throw new Error(result && result.error ? result.error : 'Failed to create goods receipt');
                }
            } catch (error) {
                this.createNotificationError({
                    title: this.$tc('ppobase-purchase-order.detail.errorTitle'),
                    message: error.message || 'Failed to create goods receipt'
                });
            }
        },

        loadGoodsReceipts: async function() {
            this.isLoadingReceipts = true;
            try {
                var result = await this.ppobaseApiService.getGoodsReceiptsByPo(this.purchaseOrderId);
                if (result && result.success && result.data) {
                    this.goodsReceipts = result.data;
                } else {
                    this.goodsReceipts = [];
                }
            } catch (error) {
                this.goodsReceipts = [];
            } finally {
                this.isLoadingReceipts = false;
            }
        },

        onStay: function() {
            this.showUnsavedChangesModal = false;
            this.unsavedChangesAction = null;
        }
    }
});
