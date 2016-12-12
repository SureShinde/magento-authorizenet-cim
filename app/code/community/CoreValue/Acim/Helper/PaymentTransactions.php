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

    protected $_mode = \net\authorize\api\constants\ANetEnvironment::SANDBOX;

    public function __construct()
    {
        $liveMode = Mage::getStoreConfig('payment/corevalue_acim/live_mode');
        if ($liveMode) {
            $this->_mode = \net\authorize\api\constants\ANetEnvironment::PRODUCTION;
        }
    }

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

    public function processChargeCreditCardRequest(Mage_Sales_Model_Order_Payment $payment, Mage_Sales_Model_Order $order, $amount, $action = 'authOnlyTransaction')
    {
        // Create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($payment->getCcNumber());
            $creditCard->setExpirationDate($payment->getCcExpMonth().'-'.$payment->getCcExpYear());
            $creditCard->setCardCode($payment->getCcCid());
        $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);

        $items = array();
        foreach ($order->getAllVisibleItems() as $productItem){
            $item = new AnetAPI\LineItemType();
            $item->setItemId($productItem->getSku())
                ->setName($productItem->getName())
                ->setQuantity($productItem->getQtyOrdered());
                //->setUnitPrice($productItem->);
            $items[] = $item;
        }

        //create a transaction
        $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType($action);//authCaptureTransaction
            $transactionRequest->setAmount($amount);
            $transactionRequest->setLineItems($items);
            $transactionRequest->setPayment($paymentOne);

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
            $request->setTransactionRequest( $transactionRequest);
        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    echo "Charge Credit Card AUTH CODE : " . $tresponse->getAuthCode() . "\n";
                    echo "Charge Credit Card TRANS ID  : " . $tresponse->getTransId() . "\n";
                    echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                    echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                } else {
                    echo "Transaction Failed \n";
                    if ($tresponse->getErrors() != null) {
                        echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    }
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    public function processChargeCustomerProfileRequest(Mage_Sales_Model_Order $order, $profileId, $paymentId, $amount, $action = 'authOnlyTransaction')
    {
        $paymentProfile = new AnetAPI\PaymentProfileType();
            $paymentProfile->setPaymentProfileId($paymentId);
        $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
            $profileToCharge->setCustomerProfileId($profileId);
            $profileToCharge->setPaymentProfile($paymentProfile);


        $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType($action);//authCaptureTransaction
            $transactionRequest->setAmount($amount);
            $transactionRequest->setProfile($profileToCharge);

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
            $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    echo "Charge Customer Profile APPROVED  :" . "\n";
                    echo " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
                    echo " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
                    echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                    echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                } else {
                    echo "Transaction Failed \n";
                    if ($tresponse->getErrors() != null) {
                        echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    }
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    public function processCaptureAuthorizedAmountRequest($transactionId)
    {
        // Now capture the previously authorized  amount
        $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType('priorAuthCaptureTransaction');
            $transactionRequest->setRefTransId($transactionId);

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
            $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    echo "Successful." . "\n";
                    echo "Capture Previously Authorized Amount, Trans ID : " . $tresponse->getRefTransId() . "\n";
                    echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                    echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                } else {
                    echo "Transaction Failed \n";
                    if ($tresponse->getErrors() != null) {
                        echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    }
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    public function processRefundTransactionRequest($transactionId, $amount, $last4)
    {
        // Create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($last4);
        $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);

        //create a transaction
        $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType('refundTransaction');
            $transactionRequest->setRefTransId($transactionId);
            $transactionRequest->setAmount($amount);
            $transactionRequest->setPayment($paymentOne);

        $request = $this->initNewRequest(new AnetAPI\CreateTransactionRequest());
            $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    echo "Refund SUCCESS: " . $tresponse->getTransId() . "\n";
                    echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                    echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                } else {
                    echo "Transaction Failed \n";
                    if ($tresponse->getErrors() != null) {
                        echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    }
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

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
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    echo " Void transaction SUCCESS AUTH CODE: " . $tresponse->getAuthCode() . "\n";
                    echo " Void transaction SUCCESS TRANS ID  : " . $tresponse->getTransId() . "\n";
                    echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                    echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                } else {
                    echo "Transaction Failed \n";
                    if ($tresponse->getErrors() != null) {
                        echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    }
                }
            } else {
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $tresponse->getErrors()[0]->getErrorCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $tresponse->getErrors()[0]->getErrorText() . "\n"
                    );
                } else {
                    Mage::throwException(
                        Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                        Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                    );
                }
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

}