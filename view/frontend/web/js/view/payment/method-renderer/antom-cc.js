/**
 * Antom Card payment method renderer for Magento 2 Luma checkout.
 *
 * Integrates the Antom AMS Element SDK to provide an embedded card payment form.
 * The Element renders inside an iframe managed by the Antom SDK, so no sensitive
 * card data touches Magento's frontend.
 *
 * Payment flow:
 *  1. Customer selects "Credit or Debit Card" → Element SDK is loaded and a
 *     quote-level payment session is created via POST /antom/payment/createquotesession.
 *  2. AMSElement is mounted into #antom-cc-element-container.
 *  3. Customer fills card details in the Element iframe and clicks "Place Order".
 *  4. Magento order is created (pending_payment) then element.submitPayment()
 *     submits the card data to Antom.
 *  5. On success the customer is redirected to the success page.
 *     On 3DS / processing the customer is redirected to the processing page
 *     which polls the backend until a terminal state is reached.
 *     On failure the pending order is cancelled, the quote is restored, and
 *     the Element is re-mounted with a fresh session.
 */
define(
    [
        'Magento_Payment/js/view/payment/default',
        'jquery',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'mage/translate'
    ],
    function (
        Component,
        $,
        placeOrderAction,
        quote,
        fullScreenLoader,
        errorProcessor,
        additionalValidators,
        redirectOnSuccessAction,
        $t
    ) {
        'use strict';

        // ── Antom error code dictionary (shared with Hyva implementation) ──────────

        var ERROR_CONFIG = {
            UNKNOWN_EXCEPTION:                { message: $t('Unknown exception. Please check your payment status and contact the merchant.'), supportRetry: true },
            USER_BALANCE_NOT_ENOUGH:          { message: $t('Insufficient balance. Please top up or choose another payment method.'), supportRetry: true },
            ORDER_NOT_EXIST:                  { message: $t('Order status is abnormal. Please check your payment status and contact the merchant.'), supportRetry: true },
            PROCESS_FAIL:                     { message: $t('Payment failed. Please check your payment status and contact the merchant.'), supportRetry: true },
            ORDER_IS_CANCELLED:               { message: $t('Order has been cancelled. Please refresh the page and try again.'), supportRetry: false },
            RISK_REJECT:                      { message: $t('Risk control rejected. Please try with a different card or contact your bank.'), supportRetry: true },
            ORDER_IS_CLOSED:                  { message: $t('Order has been closed. Please refresh the page and try again.'), supportRetry: false },
            INQUIRY_PAYMENT_SESSION_FAILED:   { message: $t('Payment session expired. Please refresh the page and try again.'), supportRetry: false },
            ACCESS_DENIED:                    { message: $t('Payment failed. Please check your payment status and contact the merchant.'), supportRetry: true },
            CARD_EXPIRED:                     { message: $t('Card has expired. Please check the expiry date or use another card.'), supportRetry: true },
            INVALID_EXPIRY_DATE_FORMAT:       { message: $t('Invalid expiry date format. Please check the expiry date or use another card.'), supportRetry: true },
            INVALID_EXPIRATION_DATE:          { message: $t('Invalid expiration date. Please check the expiry date or use another card.'), supportRetry: true },
            INVALID_CVV:                      { message: $t('Invalid CVV. Please check the CVV or use another card.'), supportRetry: true },
            INVALID_CARD_NUMBER:              { message: $t('Invalid card number. Please try with a different card or contact your bank.'), supportRetry: true },
            SELECTED_CARD_BRAND_NOT_AVAILABLE:{ message: $t('Card brand not supported. Please try with a different card or contact your bank.'), supportRetry: true },
            CARD_NOT_SUPPORTED:               { message: $t('Card not supported. Please try with a different card or contact your bank.'), supportRetry: true },
            CARD_BIN_QUERY_ERROR:             { message: $t('Invalid card number. Please try with a different card or contact your bank.'), supportRetry: true },
            PAYMENT_IN_PROCESS:               { message: $t('Payment is being processed. Please wait for completion.'), supportRetry: true },
            CURRENCY_NOT_SUPPORT:             { message: $t('Currency not supported by merchant. Transaction cannot be initiated.'), supportRetry: true },
            INVALID_CARD:                     { message: $t('Invalid card. Please check card details or use another card.'), supportRetry: true },
            ISSUER_REJECTS_TRANSACTION:       { message: $t('Transaction rejected by issuing bank. Please try with a different card or contact your bank.'), supportRetry: true },
            INVALID_MERCHANT_STATUS:          { message: $t('Merchant status is abnormal. Please refresh the page and try again.'), supportRetry: false },
            KEY_NOT_FOUND:                    { message: $t('Unknown exception. Please check your payment status and contact the merchant.'), supportRetry: false },
            MERCHANT_KYB_NOT_QUALIFIED:       { message: $t('Merchant status is abnormal. Please refresh the page and try again.'), supportRetry: false },
            NO_PAY_OPTIONS:                   { message: $t('No payment options available. Please refresh the page and try again.'), supportRetry: false },
            PARAM_ILLEGAL:                    { message: $t('Unknown exception. Please check your payment status and contact the merchant.'), supportRetry: false },
            PAYMENT_AMOUNT_EXCEED_LIMIT:      { message: $t('Payment amount exceeds limit. Please refresh the page and try with a lower amount.'), supportRetry: false },
            PAYMENT_COUNT_EXCEED_LIMIT:       { message: $t('Payment count exceeds limit. Please refresh the page and try again.'), supportRetry: false },
            PAYMENT_NOT_QUALIFIED:            { message: $t('Merchant status is abnormal. Please refresh the page and try again.'), supportRetry: false },
            SUSPECTED_CARD:                   { message: $t('Risk control rejected. Please try with a different card or contact your bank.'), supportRetry: true },
            SYSTEM_ERROR:                     { message: $t('System error. Please check your payment status and contact the merchant.'), supportRetry: true },
            USER_AMOUNT_EXCEED_LIMIT:         { message: $t('Amount exceeds limit. Please try with an amount within your available balance.'), supportRetry: true },
            USER_KYC_NOT_QUALIFIED:           { message: $t('User status is abnormal. Please try with a different card or payment method.'), supportRetry: true },
            USER_PAYMENT_VERIFICATION_FAILED: { message: $t('User status is abnormal. Please try with a different card or contact your bank.'), supportRetry: true },
            USER_STATUS_ABNORMAL:             { message: $t('User status is abnormal. Please try with a different card or contact your bank.'), supportRetry: true },
            DO_NOT_HONOR:                     { message: $t('Payment rejected by issuing bank. Please try with a different card or contact your bank.'), supportRetry: true },
            EXTERNAL_RESOURCE_LOAD_FAILED:    { message: $t('Request failed. Please check your network connection or device status.'), supportRetry: true },
            SUBMIT_PAYMENT_TIMEOUT:           { message: $t('Request timeout. Transaction cannot be initiated.'), supportRetry: true },
            PAYMENT_RESULT_TIMEOUT:           { message: $t('Request timeout. Transaction cannot be initiated.'), supportRetry: true },
            ERR_DATA_STRUCT_UNRECOGNIZED:     { message: $t('Request failed. Transaction cannot be initiated.'), supportRetry: false },
            USER_CANCELED:                    { message: $t('Payment has been cancelled by user.'), supportRetry: true },
            FORM_INVALID:                     { message: $t('Payment information is invalid.'), supportRetry: true }
        };

        /**
         * @param {string|undefined} code
         * @returns {{code: string, message: string, supportRetry: boolean}}
         */
        function getErrorInfo(code) {
            if (code && ERROR_CONFIG[code]) {
                return {
                    code: code,
                    message: ERROR_CONFIG[code].message,
                    supportRetry: ERROR_CONFIG[code].supportRetry
                };
            }

            return {
                code: code || 'UNKNOWN',
                message: $t('Payment did not complete. Please try again or contact the merchant.'),
                supportRetry: true
            };
        }

        return Component.extend({
            defaults: {
                template: 'CaravanGlory_Antom/payment/antom-cc'
            },

            /** @type {knockout.observable<boolean>} */
            isElementMounted: false,

            /** @type {knockout.observable<boolean>} */
            isLoading: false,

            /** @type {Object|null} AMSElement instance */
            elementInstance: null,

            /** @type {string|null} Payment session expiry timestamp */
            paymentSessionExpiryTime: null,

            /** @type {boolean} Guard to prevent concurrent place-order calls */
            submitting: false,

            /** @type {boolean} Prevent duplicate activation */
            _elementActivated: false,

            // ── Lifecycle ───────────────────────────────────────────────────────

            /**
             * @returns {void}
             */
            initialize: function () {
                this._super();
                this.isElementMounted = ko.observable(false);
                this.isLoading = ko.observable(false);

                var self = this;

                this.isPlaceOrderActionAllowed = ko.computed(function () {
                    return self.isElementMounted() && !self.isLoading();
                }, this);

                // Activate Element when this payment method is selected.
                quote.paymentMethod.subscribe(function (method) {
                    if (method && method.method === self.getCode()) {
                        self.activateElement();
                    }
                });
            },

            /**
             * @returns {string}
             */
            getCode: function () {
                return 'antom_cc';
            },

            // ── Element lifecycle ───────────────────────────────────────────────

            /**
             * Activates the Antom Element when the payment method is first selected.
             * Only runs once; subsequent selections reuse the mounted Element.
             */
            activateElement: function () {
                if (this._elementActivated) {
                    return;
                }
                this._elementActivated = true;
                this.createAntomElement();
            },

            /**
             * Loads the Antom SDK, creates a quote-level payment session, and
             * mounts the AMSElement into the container div.
             */
            createAntomElement: function () {
                var self = this;

                if (self.elementInstance) {
                    return;
                }

                self.isLoading(true);
                fullScreenLoader.startLoader();

                self.loadSdk()
                    .then(function () {
                        return self.createQuoteSession();
                    })
                    .then(function (sessionResponse) {
                        if (!sessionResponse.paymentSessionData) {
                            throw new Error($t('Invalid payment session response'));
                        }

                        var env = self.getSdkEnvironment();
                        self.paymentSessionExpiryTime = sessionResponse.paymentSessionExpiryTime || null;

                        self.elementInstance = new AMSElement({
                            sessionData: sessionResponse.paymentSessionData,
                            environment: env,
                            locale: document.documentElement.lang || 'en_US',
                            appearance: {
                                showSubmitButton: false
                            },
                            notRedirectAfterComplete: true,
                            analytics: {
                                enabled: false
                            },
                            onEventCallback: function () {
                                // AMSElement resolves submitPayment() directly.
                                // Keep the callback wired for SDK compatibility.
                            }
                        });

                        return self.elementInstance.mount(
                            { type: 'payment', notRedirectAfterComplete: true },
                            '#antom-cc-element-container'
                        );
                    })
                    .then(function (mountResult) {
                        var mountError = mountResult && mountResult.error;
                        if (mountError && (mountError.code || mountError.message)) {
                            self.elementInstance = null;
                            var info = getErrorInfo(mountError.code);
                            throw new Error(info.message || mountError.message);
                        }
                        self.isElementMounted(true);
                    })
                    .catch(function (error) {
                        self.addErrorMessage(
                            error.message || $t('Failed to load payment form.')
                        );
                    })
                    .finally(function () {
                        self.isLoading(false);
                        fullScreenLoader.stopLoader();
                    });
            },

            /**
             * Dynamically loads the Antom Web SDK if not already present.
             * @returns {Promise}
             */
            loadSdk: function () {
                return new Promise(function (resolve, reject) {
                    if (window.AMSElement) {
                        resolve();
                        return;
                    }

                    var config = window.checkoutConfig.payment['antom_cc'] || {};
                    var sdkUrl = config.sdkUrl || 'https://js.antom.com/v2/ams-checkout.js';

                    var script = document.createElement('script');
                    script.src = sdkUrl;
                    script.addEventListener('load', resolve);
                    script.addEventListener('error', function () {
                        reject(new Error($t('Failed to load payment SDK.')));
                    });
                    document.head.appendChild(script);
                });
            },

            /**
             * @returns {string} "sandbox" or "production"
             */
            getSdkEnvironment: function () {
                var config = window.checkoutConfig.payment['antom_cc'] || {};
                return config.sdkEnvironment || 'sandbox';
            },

            /**
             * Destroys the current AMSElement instance.
             */
            destroyElement: function () {
                if (this.elementInstance) {
                    try {
                        this.elementInstance.unmount();
                    } catch (e) {
                        // Already unmounted
                    }
                    this.elementInstance = null;
                }
                this.isElementMounted(false);
            },

            /**
             * Destroys the Element and re-creates it with a fresh session after
             * a brief delay to let the server-side quote restoration settle.
             */
            reMountElement: function () {
                var self = this;
                this.destroyElement();
                this._elementActivated = false;

                setTimeout(function () {
                    self._elementActivated = true;
                    self.createAntomElement();
                }, 1000);
            },

            // ── Backend API helpers ─────────────────────────────────────────────

            /**
             * Creates a quote-level payment session.
             * @returns {Promise<Object>}
             */
            createQuoteSession: function () {
                var baseUrl = this.getBaseUrl();

                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: baseUrl + 'antom/payment/createquotesession',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            payment_method_type: 'CARD'
                        }),
                        global: false,
                        success: function (response) {
                            if (response.error) {
                                reject(new Error(
                                    response.message || $t('Failed to create payment session')
                                ));
                                return;
                            }
                            resolve(response);
                        },
                        error: function (xhr) {
                            var msg = $t('Failed to create payment session. Please try again.');
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (data && data.message) {
                                    msg = data.message;
                                }
                            } catch (e) {
                                // Use default message
                            }
                            reject(new Error(msg));
                        }
                    });
                });
            },

            /**
             * Queries the order payment status.
             * @returns {Promise<Object>}
             */
            queryOrderStatus: function () {
                var baseUrl = this.getBaseUrl();

                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: baseUrl + 'antom/payment/orderstatus',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({}),
                        global: false,
                        success: function (response) {
                            resolve(response);
                        },
                        error: function (xhr) {
                            var msg = $t('Unable to determine payment status.');
                            try {
                                var data = JSON.parse(xhr.responseText);
                                if (data && data.message) {
                                    msg = data.message;
                                }
                            } catch (e) {
                                // Use default message
                            }
                            reject(new Error(msg));
                        }
                    });
                });
            },

            /**
             * Cancels the pending_payment order and restores the quote so the
             * customer can retry without leaving an orphan order behind.
             * @returns {Promise}
             */
            restoreOrder: function () {
                var baseUrl = this.getBaseUrl();

                return new Promise(function (resolve) {
                    $.ajax({
                        url: baseUrl + 'antom/payment/restoreorder',
                        type: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify({}),
                        global: false,
                        complete: function () {
                            resolve();
                        }
                    });
                });
            },

            // ── Place Order ─────────────────────────────────────────────────────

            /**
             * Returns payment data for the Magento place-order action.
             * @returns {Object}
             */
            getData: function () {
                return {
                    method: this.getCode(),
                    additional_data: {}
                };
            },

            /**
             * Place Order button handler.
             * Creates the Magento order first, then submits the card payment
             * through the Antom Element SDK.
             *
             * @param {Object} data
             * @param {Event} [event]
             */
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (!this.isPlaceOrderActionAllowed()) {
                    return;
                }

                if (!this.elementInstance) {
                    this.addErrorMessage(
                        $t('Payment form is not ready. Please wait a moment and try again.')
                    );
                    return;
                }

                if (!additionalValidators.validate()) {
                    return;
                }

                if (this.submitting) {
                    return;
                }
                this.submitting = true;

                this.isLoading(true);
                fullScreenLoader.startLoader();

                // Step 1: Create Magento order (state = pending_payment)
                placeOrderAction(this.getData(), this.messageContainer)
                    .done(function () {
                        // Step 2: Submit card payment through Antom Element
                        self.submitPayment();
                    })
                    .fail(function (response) {
                        self.submitting = false;
                        self.isLoading(false);
                        fullScreenLoader.stopLoader();
                        errorProcessor.process(response, self.messageContainer);
                    });
            },

            /**
             * Submits the payment through the Antom Element SDK and handles the
             * result (success, 3DS, error).
             */
            submitPayment: function () {
                var self = this;

                this.elementInstance.submitPayment()
                    .then(function (result) {
                        var status = result && result.status;
                        var error = result && result.error;
                        var userCanceled3D = result && result.userCanceled3D;

                        if (status === 'SUCCESS' && !error) {
                            self.destroyElement();
                            redirectOnSuccessAction.execute();
                            return;
                        }

                        // 3DS cancellation / in-flight processing: redirect to
                        // the processing page which polls the backend.
                        if (userCanceled3D || status === 'PROCESSING' || status === 'PENDING') {
                            self.destroyElement();
                            window.location.href = self.getBaseUrl() + 'antom/payment/processing';
                            return;
                        }

                        if (error) {
                            self.handlePaymentError(error);
                            return;
                        }

                        // Unexpected result shape — try server-side recovery.
                        self.recoverPendingOrder(
                            new Error($t('Payment did not complete. Please try again.'))
                        );
                    })
                    .catch(function (err) {
                        self.recoverPendingOrder(err);
                    });
            },

            // ── Error handling & recovery ───────────────────────────────────────

            /**
             * Handles an error from the Element SDK's submitPayment().
             * Non-retryable errors cancel the order and re-mount with a fresh session.
             *
             * @param {Object} error
             */
            handlePaymentError: function (error) {
                var self = this;
                var info = getErrorInfo(error && error.code);
                var msg = info.message || (error && error.message) || $t('Payment failed. Please try again.');

                this.submitting = false;
                this.isLoading(false);
                fullScreenLoader.stopLoader();

                if (!info.supportRetry) {
                    this.restoreOrder().then(function () {
                        self.reMountElement();
                        self.addErrorMessage(msg);
                    });
                    return;
                }

                // Retryable: restore order so customer can try again with same session
                this.restoreOrder().then(function () {
                    self.reMountElement();
                    self.addErrorMessage(msg);
                });
            },

            /**
             * Attempts to recover from an ambiguous payment result by polling the
             * backend order-status endpoint.
             *
             * @param {Error} fallbackError
             */
            recoverPendingOrder: function (fallbackError) {
                var self = this;

                this.pollOrderStatus(5, 3000)
                    .then(function (result) {
                        if (result.status === 'success') {
                            self.destroyElement();
                            redirectOnSuccessAction.execute();
                            return;
                        }

                        if (result.status === 'processing') {
                            self.destroyElement();
                            window.location.href = self.getBaseUrl() + 'antom/payment/processing';
                            return;
                        }

                        // Failed or unknown
                        self.finishRecovery(fallbackError, result);
                    })
                    .catch(function () {
                        self.finishRecovery(fallbackError, null);
                    });
            },

            /**
             * Common recovery cleanup: restore order, re-mount element, show error.
             *
             * @param {Error} fallbackError
             * @param {Object|null} pollResult
             */
            finishRecovery: function (fallbackError, pollResult) {
                var self = this;
                var msg = (pollResult && pollResult.message)
                    || (fallbackError && fallbackError.message)
                    || $t('Payment failed. Please try again.');

                this.submitting = false;
                this.isLoading(false);
                fullScreenLoader.stopLoader();

                this.restoreOrder().then(function () {
                    self.reMountElement();
                    self.addErrorMessage(msg);
                });
            },

            /**
             * Polls the order status endpoint up to maxAttempts times.
             *
             * @param {number} maxAttempts
             * @param {number} intervalMs
             * @returns {Promise<Object>}
             */
            pollOrderStatus: function (maxAttempts, intervalMs) {
                var self = this;

                function poll(attempt) {
                    return self.queryOrderStatus().then(function (result) {
                        if (result.status === 'success' || result.status === 'failed') {
                            return result;
                        }
                        if (attempt < maxAttempts - 1) {
                            return new Promise(function (resolve) {
                                setTimeout(function () {
                                    resolve(poll(attempt + 1));
                                }, intervalMs);
                            });
                        }
                        return result;
                    });
                }

                return poll(0);
            },

            // ── Utilities ───────────────────────────────────────────────────────

            /**
             * @returns {string}
             */
            getBaseUrl: function () {
                return window.checkoutConfig.base_url || window.BASE_URL || '/';
            },

            /**
             * Adds an error message to the payment method message container.
             *
             * @param {string} message
             */
            addErrorMessage: function (message) {
                this.messageContainer.addErrorMessage({
                    message: message
                });
            }
        });
    }
);
