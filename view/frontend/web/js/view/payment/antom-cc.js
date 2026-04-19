define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'antom_cc',
                component: 'CaravanGlory_Antom/js/view/payment/method-renderer/antom-cc'
            }
        );

        return Component.extend({});
    }
);
