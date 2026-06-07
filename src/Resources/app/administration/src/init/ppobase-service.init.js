/**
 * Initialize and register PPOBase services
 */

import PpobaseApiService from '../service/ppobase-api.service';
import PpobaseTabPositionService from '../service/ppobase-tab-position.service';

const { Application } = Shopware;

Application.addServiceProvider('ppobaseApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new PpobaseApiService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('ppobaseTabPositionService', () => {
    return new PpobaseTabPositionService(Shopware.Service('systemConfigApiService'));
});
