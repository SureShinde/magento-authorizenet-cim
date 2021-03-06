<?php

require_once(Mage::getBaseDir('lib') . DS . 'AuthorizeNetSDK' . DS . 'vendor' . DS . 'autoload.php');
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

//define("AUTHORIZENET_LOG_FILE", "corevalue_acim");

/**
 * http://developer.authorize.net/api/reference/index.html#payment-transactions
 * + Charge a Credit Card
 * + Authorize a Credit Card
 * + Capture a Previously Authorized Amount
 * - Capture Funds Authorized Through Another Channel
 * + Refund a Transaction
 * + Void a Transaction
 * - Update Split Tender Group
 * - Debit a Bank Account
 * - Credit a Bank Account
 * + Charge a Customer Profile
 * - Charge a Tokenized Credit Card
 *
 * Class CoreValue_Acim_Helper_PaymentTransactions
 */
class CoreValue_Acim_Helper_PaymentTransactions extends Mage_Core_Helper_Abstract
{

    /**
     * @var string
     */
    protected $_mode = \net\authorize\api\constants\ANetEnvironment::SANDBOX;

    /**
     * CoreValue_Acim_Helper_PaymentTransactions constructor.
     */
    public function __construct()
    {
        $liveMode = Mage::getStoreConfig('payment/corevalue_acim/live_mode');
        if ($liveMode) {
            $this->_mode = \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
        }
    }

    /**
     * @param $request
     * @return mixed
     */
    public function initNewRequest($request)
    {
        $apiKey = Mage::getStoreConfig('payment/corevalue_acim/api_key');
        $transactionKey = Mage::getStoreConfig('payment/corevalue_acim/transaction_key');

        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($apiKey);
        $merchantAuthentication->setTransactionKey($transactionKey);

        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId('ref' . time());

        return $request;
    }

    /**
     * Charge Customer's credit card
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @param string $action
     * @return AnetAPI\AnetApiResponseType
     */
    public function processChargeCreditCardRequest(
        Mage_Sales_Model_Order_Payment $payment,
        $amount,
        $action = 'authOnlyTransaction'
    )
    {
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper             = Mage::helper('corevalue_acim');
        /* @var $order Mage_Sales_Model_Order */
        $order              = $payment->getOrder();

        /* @var $creditCard AnetAPI\CreditCardType */
        $creditCard         = $helper->getCreditCardObject($payment);
        /* @var $paymentOne AnetAPI\PaymentType */
        $paymentOne         = $helper->getPaymentTypeObject($creditCard);
        /* @var $billTo AnetAPI\CustomerAddressType */
        $billTo             = $helper->getBillingAddressObject($payment);
        /* @var $tOrder AnetAPI\OrderType */
        $tOrder             = $helper->getOrderTypeObject($order);

        $items = [];
        foreach ($order->getAllVisibleItems() as $productItem) {
            $items[] = $helper->getLineItemTypeObject($productItem);
        }

        // create a transaction
        /* @var $transactionRequest AnetAPI\TransactionRequestType */
        $transactionRequest = $helper->getTransactionRequestTypeObject($action, $amount, $items, $paymentOne, $billTo, $tOrder);

        // preparing request
        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
        $request->setTransactionRequest($transactionRequest);

        // executing request
        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if ($response != null) {
            $tresponse = $response->getTransactionResponse();

            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('processChargeCreditCardRequest()', Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card AUTH CODE: ' . $tresponse->getAuthCode(), Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card TRANS ID: ' . $tresponse->getTransId(), Zend_Log::DEBUG, 'cv_acim.log');

                return $helper->handleTransactionResponse($payment, $tresponse);
            } else {
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        }

        Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
    }

    /**
     * Charge customer's payment profile
     *
     * @param $profileId
     * @param $paymentId
     * @param $amount
     * @param string $action
     * @return AnetAPI\AnetApiResponseType
     */
    public function processChargeCustomerProfileRequest(
        Mage_Sales_Model_Order_Payment $payment,
        $profileId,
        $paymentId,
        $amount,
        $action = 'authOnlyTransaction'
    )
    {
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper             = Mage::helper('corevalue_acim');
        /* @var $order Mage_Sales_Model_Order */
        $order              = $payment->getOrder();

        /* @var $paymentProfile AnetAPI\PaymentProfileType */
        $paymentProfile     = $helper->getPaymentProfileTypeObject($paymentId);
        /* @var $profileToCharge AnetAPI\CustomerProfilePaymentType */
        $profileToCharge    = $helper->getCustomerProfilePaymentTypeObject($profileId, $paymentProfile);
        /* @var $billTo AnetAPI\CustomerAddressType */
        $billTo             = $helper->getBillingAddressObject($payment);
        /* @var $tOrder AnetAPI\OrderType */
        $tOrder             = $helper->getOrderTypeObject($order);

        $items = [];
        foreach ($order->getAllVisibleItems() as $productItem) {
            $items[] = $helper->getLineItemTypeObject($productItem);
        }

        /* @var $transactionRequest AnetAPI\TransactionRequestType */
        $transactionRequest = $helper->getTransactionRequestTypeObject($action, $amount, $items, $profileToCharge, $billTo, $tOrder);

        // preparing request
        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
        $request->setTransactionRequest($transactionRequest);

        // executing request
        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if ($response != null) {
            $tresponse = $response->getTransactionResponse();

            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('processChargeCustomerProfileRequest()', Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card AUTH CODE: ' . $tresponse->getAuthCode(), Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card TRANS ID: ' . $tresponse->getTransId(), Zend_Log::DEBUG, 'cv_acim.log');

                return $helper->handleTransactionResponse($payment, $tresponse);
            } else {
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        }

        Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @return mixed|AnetAPI\AnetApiResponseType
     */
    public function processCaptureAuthorizedAmountRequest(Mage_Sales_Model_Order_Payment $payment, $amount)
    {
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper             = Mage::helper('corevalue_acim');

        // Now capture the previously authorized  amount
        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType('priorAuthCaptureTransaction');
        $transactionRequest->setAmount($amount);
        $transactionRequest->setRefTransId($payment->getAuthorizationTransaction()->getTxnId());

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if ($response != null) {
            $tresponse = $response->getTransactionResponse();

            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('processCaptureAuthorizedAmountRequest()', Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card AUTH CODE: ' . $tresponse->getAuthCode(), Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card TRANS ID: ' . $tresponse->getTransId(), Zend_Log::DEBUG, 'cv_acim.log');

                return $helper->updatePayment($payment, $tresponse);
            } else {
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * @param $transactionId
     * @param $amount
     * @param Varien_Object $payment
     * @return Varien_Object
     */
    public function processRefundTransactionRequest($transactionId, $amount, Varien_Object $payment)
    {
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper             = Mage::helper('corevalue_acim');

        // getting customer profile id and customer payment profile id
        $profileId          = $payment->getAdditionalInformation('profile_id');
        $paymentId          = $payment->getAdditionalInformation('payment_id');

        if (!$profileId && !$paymentId) {
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($payment->getCcLast4());
            $creditCard->setExpirationDate($helper->formatExpDate($payment));

            $paymentCreditCard = new AnetAPI\PaymentType();
            $paymentCreditCard->setCreditCard($creditCard);
        } else {
            /* @var $paymentProfile AnetAPI\PaymentProfileType */
            $paymentProfile     = $helper->getPaymentProfileTypeObject($paymentId);
            /* @var $profileToCharge AnetAPI\CustomerProfilePaymentType */
            $profileToCharge    = $helper->getCustomerProfilePaymentTypeObject($profileId, $paymentProfile);
        }

        // create a transaction
        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType('refundTransaction');
        $transactionRequest->setRefTransId($transactionId);
        $transactionRequest->setAmount($amount);

        if (!$profileId && !$paymentId) {
            $transactionRequest->setPayment($paymentCreditCard);
        } else {
            $transactionRequest->setProfile($profileToCharge);
        }

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if ($response != null) {
            $tresponse = $response->getTransactionResponse();

            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('processVoidTransactionRequest()', Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card AUTH CODE: ' . $tresponse->getTransId(), Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card TRANS ID: ' . $tresponse->getTransId(), Zend_Log::DEBUG, 'cv_acim.log');

                return new Varien_Object([
                    'trans_id' => $tresponse->getTransId(),
                    'result_code' => $tresponse->getResponseCode()
                ]);
            } else {
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        }

        Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
    }

    /**
     * @param $transactionId
     * @return Varien_Object
     */
    public function processVoidTransactionRequest($transactionId)
    {
        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType('voidTransaction');
        $transactionRequest->setRefTransId($transactionId);

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if ($response != null) {
            $tresponse = $response->getTransactionResponse();

            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('processVoidTransactionRequest()', Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card AUTH CODE: ' . $tresponse->getAuthCode(), Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Charge Credit Card TRANS ID: ' . $tresponse->getTransId(), Zend_Log::DEBUG, 'cv_acim.log');

                return new Varien_Object([
                    'auth_code' => $tresponse->getAuthCode(),
                    'trans_id'  => $tresponse->getTransId(),
                ]);
            } else {
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        }

        Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
    }

}