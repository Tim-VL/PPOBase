import template from './sw-extension-card-base.html.twig';
import './sw-extension-card-base.scss';

Shopware.Component.override('sw-extension-card-base', {
    template,

    computed: {
        isPPOBase() {
            return this.extension && this.extension.name === 'PPOBase';
        },

        ppobaseDescription() {
            if (!this.isPPOBase) return null;
            return this.extension.description || null;
        }
    }
});
