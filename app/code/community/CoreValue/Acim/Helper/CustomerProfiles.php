<?php

require_once(Mage::getBaseDir('lib') . DS . 'AuthorizeNetSDK' . DS . 'vendor' . DS . 'autoload.php');
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/**
 * http://developer.authorize.net/api/reference/index.html#customer-profiles
 *  + Create Customer Profile
 *  + Get Customer Profile
 *  + Get Customer Profile IDs
 *  + Update Customer Profile
 *  + Delete Customer Profile
 *  + Create Customer Payment Profile
 *  + Get Customer Payment Profile
 *  - Get Customer Payment Profile List
 *  - Validate Customer Payment Profile
 *  + Update Customer Payment Profile
 *  + Delete Customer Payment Profile
 *  - Create Customer Shipping Address
 *  - Get Customer Shipping Address
 *  - Update Customer Shipping Address
 *  - Delete Customer Shipping Address
 *  - Get Accept Customer Profile Page
 *  + Create a Customer Profile from a Transaction
 *
 * Class CoreValue_Acim_Helper_CustomerProfiles
 */
class CoreValue_Acim_Helper_CustomerProfiles extends Mage_Core_Helper_Abstract
{

    /**
     * @var string
     */
    protected $_mode = \net\authorize\api\constants\ANetEnvironment::SANDBOX;

    /**
     * CoreValue_Acim_Helper_CustomerProfiles constructor.
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
     * @param Varien_Object $data
     * @return object
     */
    public function processCreateCustomerProfileRequest(Varien_Object $data)
    {
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper = Mage::helper('corevalue_acim');

        /* @var $creditCard AnetAPI\CreditCardType */
        $creditCard         = $helper->getCreditCardObject($data->getCard());
        /* @var $paymentCreditCard AnetAPI\PaymentType */
        $paymentCreditCard  = $helper->getPaymentTypeObject($creditCard);
        /* @var $billTo AnetAPI\CustomerAddressType */
        $billTo             = $helper->getBillingAddressObject($data->getBillTo());
        /* @var $paymentProfile AnetAPI\CustomerPaymentProfileType */
        $paymentProfile     = $helper->getCustomerPaymentTypeObject($billTo, $paymentCreditCard);
        /* @var $customerProfile AnetAPI\CustomerProfileType() */
        $customerProfile    = $helper->getCustomerProfileTypeObject($data->getCustomer(), $paymentProfile);

        // preparing request
        $request = $this->initNewRequest(new AnetAPI\CreateCustomerProfileRequest());
        $request->setProfile($customerProfile);

        // executing request
        $controller = new AnetController\CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('Customer Profile Saved: ' . $response->getCustomerProfileId(), Zend_Log::DEBUG, 'cv_acim.log');
                $helper->saveCustomerProfile($response->getCustomerProfileId(), $data->getCustomer());

                // walking trough all payment profiles received in response
                foreach ($response->getCustomerPaymentProfileIdList() as $paymentProfileId) {
                    Mage::log('Customer Payment Profile Saved: ' . $paymentProfileId, Zend_Log::DEBUG, 'cv_acim.log');

                    $helper->saveCustomerPaymentProfile(
                        $response->getCustomerProfileId(),
                        $paymentProfileId,
                        $data->getCustomer(),
                        $data->getCard()
                    );
                }
            } elseif ($response->getMessages()->getMessage()[0]->getCode() == 'E00039') {
                $profileId = preg_replace('/\D+/', '', $response->getMessages()->getMessage()[0]->getText());
                Mage::log('Customer Profile Saved: ' . $profileId, Zend_Log::DEBUG, 'cv_acim.log');
                $helper->saveCustomerProfile($profileId, $data->getCustomer());
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__('CreateCustomerProfile') .
                    Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return [
            $profileId, // profile ID
            (!empty($paymentProfileId) ? $paymentProfileId : null) // payment profile ID
        ];
    }

    /**
     * @param $profileId
     * @return object
     */
    public function processGetCustomerProfile($profileId)
    {
        $request = $this->initNewRequest(new AnetAPI\GetCustomerProfileRequest());
        $request->setCustomerProfileId($profileId);

        $controller = new AnetController\GetCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "GetCustomerProfile SUCCESS : " . "\n";
                $profileSelected = $response->getProfile();
                $paymentProfilesSelected = $profileSelected->getPaymentProfiles();
                echo "Profile Has " . count($paymentProfilesSelected) . " Payment Profiles" . "\n";

                if ($response->getSubscriptionIds() != null) {
                    if ($response->getSubscriptionIds() != null) {
                        echo "List of subscriptions:";
                        foreach ($response->getSubscriptionIds() as $subscriptionid)
                            echo $subscriptionid . "\n";
                    }
                }
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__(" GetCustomerProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::log('No response returned', Zend_Log::DEBUG, 'cv_acim.log');
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * @return AnetAPI\AnetApiResponseType
     */
    public function processGetCustomerProfileIDs()
    {
        $request = $this->initNewRequest(new AnetAPI\GetCustomerProfileIdsRequest());

        $controller = new AnetController\GetCustomerProfileIdsController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "GetCustomerProfileId's SUCCESS: " . "\n";
                $profileIds[] = $response->getIds();
                echo "There are " . count($profileIds[0]) . " Customer Profile ID's for this Merchant Name and Transaction Key" . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__(" GetCustomerProfileId's ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::log('No response returned', Zend_Log::DEBUG, 'cv_acim.log');
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * @param $profileId
     * @param $customerId
     * @param $email
     * @param $description
     * @return AnetAPI\AnetApiResponseType
     */
    public function processUpdateCustomerProfileRequest($profileId, $customerId, $email, $description)
    {
        $customerProfile = new AnetAPI\CustomerProfileExType();
        $customerProfile
            ->setCustomerProfileId($profileId)
            ->setMerchantCustomerId($customerId)
            ->setDescription($description)
            ->setEmail($email)
        ;

        $request = $this->initNewRequest(new AnetAPI\UpdateCustomerProfileRequest());
        $request->setProfile($customerProfile);

        $controller = new AnetController\UpdateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "UpdateCustomerProfile SUCCESS : " . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("UpdateCustomerProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::log('No response returned', Zend_Log::DEBUG, 'cv_acim.log');
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * Delete an existing customer profile
     * @param $profileId
     * @return object
     */
    public function processDeleteCustomerProfileRequest($profileId)
    {
        $request = $this->initNewRequest(new AnetAPI\DeleteCustomerProfileRequest());
        $request->setCustomerProfileId($profileId);

        $controller = new AnetController\DeleteCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "DeleteCustomerProfile SUCCESS : " . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("DeleteCustomerProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::log('No response returned', Zend_Log::DEBUG, 'cv_acim.log');
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * Send Credit Card to secure storage on Authorize.Net (CIM)
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return object
     */
    public function processCreatePaymentProfileRequest(Mage_Sales_Model_Order_Payment $payment)
    {
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper             = Mage::helper('corevalue_acim');

        /* @var $creditCard AnetAPI\CreditCardType */
        $creditCard         = $helper->getCreditCardObject($payment);
        /* @var $paymentCreditCard AnetAPI\PaymentType */
        $paymentCreditCard  = $helper->getPaymentTypeObject($creditCard);
        /* @var $billTo AnetAPI\CustomerAddressType */
        $billTo             = $helper->getBillingAddressObject($payment);
        /* @var $paymentProfile AnetAPI\CustomerPaymentProfileType */
        $paymentProfile     = $helper->getCustomerPaymentTypeObject($billTo, $paymentCreditCard);

        // preparing request
        $request = $this->initNewRequest(new AnetAPI\CreateCustomerPaymentProfileRequest());
        $request->setCustomerProfileId($payment->getAdditionalInformation('profile_id'));
        $request->setPaymentProfile($paymentProfile);

        // executing request
        $controller = new AnetController\CreateCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log(
                    'Create Customer Payment Profile SUCCESS:' . $response->getCustomerPaymentProfileId(),
                    Zend_Log::DEBUG,
                    'cv_acim.log'
                );
            } elseif ($response->getMessages()->getMessage()[0]->getCode() == 'E00039') {
                Mage::log(
                    'Create Customer Payment Profile RECEIVED:' . $response->getCustomerPaymentProfileId(),
                    Zend_Log::DEBUG,
                    'cv_acim.log'
                );
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__('CreatePaymentProfile') .
                    Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        // saving payment profile to DB
        // ToDO: need to adjust this code to new version
        $helper->saveCustomerPaymentProfile($response->getCustomerPaymentProfileId(), $payment);

        return $response;
    }

    /**
     * @param $profileId
     * @param $paymentId
     * @return object
     */
    public function processGetCustomerPaymentProfileRequest($profileId, $paymentId)
    {
        $request = $this->initNewRequest(new AnetAPI\GetCustomerPaymentProfileRequest());
        $request->setCustomerProfileId($profileId);
        $request->setCustomerPaymentProfileId($paymentId);

        $controller = new AnetController\GetCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                return new Varien_Object([
                    'credit_card'   => new Varien_Object([
                        'number'        => $response->getPaymentProfile()->getPayment()->getCreditCard()->getCardNumber(),
                        'exp_date'      => $response->getPaymentProfile()->getPayment()->getCreditCard()->getExpirationDate(),
                        'type'          => $response->getPaymentProfile()->getPayment()->getCreditCard()->getCardType(),
                    ]),
                    'bill_to'       => new Varien_Object([
                        'firstname'     => $response->getPaymentProfile()->getbillTo()->getFirstName(),
                        'lastname'      => $response->getPaymentProfile()->getbillTo()->getLastName(),
                        'company'       => $response->getPaymentProfile()->getbillTo()->getCompany(),
                        'address'       => $response->getPaymentProfile()->getbillTo()->getAddress(),
                        'city'          => $response->getPaymentProfile()->getbillTo()->getCity(),
                        'region'        => $response->getPaymentProfile()->getbillTo()->getState(),
                        'zip'           => $response->getPaymentProfile()->getbillTo()->getZip(),
                        'country'       => $response->getPaymentProfile()->getbillTo()->getCountry(),
                        'phone'         => $response->getPaymentProfile()->getbillTo()->getPhoneNumber(),
                        'fax'           => $response->getPaymentProfile()->getbillTo()->getFaxNumber(),
                    ]),
                ]);
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__('processGetCustomerPaymentProfileRequest()') .
                    Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        }

        Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
    }

    /**
     * @param $profileId
     * @param $paymentId
     * @param $billingInfo
     * @return object
     */
    public function processUpdateCustomerPaymentProfileRequest($profileId, $paymentId, $billingInfo)
    {
        // We're updating the billing address but everything has to be passed in an update
        // For card information you can pass exactly what comes back from an GetCustomerPaymentProfile
        // if you don't need to update that info Create the payment data for a credit card
        $ccNumber = htmlentities($billingInfo['payment']['cc_number']);
        $ccExpDate = intval($billingInfo['payment']['cc_exp_year']) . '-' . str_pad(intval($billingInfo['payment']['cc_exp_month']), 2, '0', STR_PAD_LEFT);
        $ccExpDate = 'XXXX-XX' ? 'XXXX' : $ccExpDate;
        $cvv = $billingInfo['payment']['cc_cid'];
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($ccNumber);
        $creditCard->setExpirationDate($ccExpDate);
        $creditCard->setCardCode($cvv);
        $paymentCreditCard = new AnetAPI\PaymentType();
        $paymentCreditCard->setCreditCard($creditCard);

        // Create the Bill To info
        $region = '';
        if (isset($billingInfo['region']) && $billingInfo['region'] != "") {
            $region = $billingInfo['region'];
        } elseif (isset($billingInfo['region_id']) && $billingInfo['region_id'] != "") {
            $region = $billingInfo['region_id'];
        }
        $billTo = new AnetAPI\CustomerAddressType();
        $billTo->setFirstName($billingInfo['first_name'])
            ->setLastName($billingInfo['last_name'])
            ->setCompany($billingInfo['company'])
            ->setAddress($billingInfo['address'])
            ->setCity($billingInfo['city'])
            ->setState($region)
            ->setZip($billingInfo['zip'])
            ->setCountry($billingInfo['bill']['country_id'])
            ->setPhoneNumber($billingInfo['telephone'])
            ->setFaxNumber($billingInfo['fax']);

        // Create a new Customer Payment Profile
        $paymentProfile = new AnetAPI\CustomerPaymentProfileExType();
        $paymentProfile->setCustomerType('individual')
            ->setCustomerPaymentProfileId($paymentId)
            ->setBillTo($billTo)
            ->setPayment($paymentCreditCard);
            //->setDefaultPaymentProfile(true);

        //Set profile ids of profile to be updated
        $request = $this->initNewRequest(new AnetAPI\UpdateCustomerPaymentProfileRequest());
        $request->setCustomerProfileId($profileId);
        $request->setPaymentProfile($paymentProfile);

        // Submit a UpdatePaymentProfileRequest
        $controller = new AnetController\UpdateCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "Update Customer Payment Profile SUCCESS: " . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("UpdateCustomerPaymentProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::log('No response returned', Zend_Log::DEBUG, 'cv_acim.log');
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * @param $profileId
     * @param $paymentId
     * @return object
     */
    public function processDeletePaymentProfileRequest($profileId, $paymentId)
    {
        $request = $this->initNewRequest(new AnetAPI\DeleteCustomerPaymentProfileRequest());
        $request->setCustomerProfileId($profileId);
        $request->setCustomerPaymentProfileId($paymentId);

        $controller = new AnetController\DeleteCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);

        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                return true;
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__('processDeletePaymentProfileRequest()') .
                    Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        }

        Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
    }

    /**
     * @param $transactionId
     * @param $customerId
     * @param $email
     * @param string $description
     * @return AnetAPI\AnetApiResponseType
     */
    public function processCreateCustomerProfileFromTransactionRequest(
        $transactionId,
        $customerId,
        $email,
        $description = ''
    )
    {
        $customerProfile = new AnetAPI\CustomerProfileBaseType();
        $customerProfile->setMerchantCustomerId($customerId)
            ->setDescription($description)
            ->setEmail($email);

        $request = $this->initNewRequest(new AnetAPI\CreateCustomerProfileFromTransactionRequest());
        $request->setTransId($transactionId);
        // You can either specify the customer information in form of customerProfileBaseType object
        $request->setCustomer($customerProfile);
        // OR you can just provide the customer Profile ID
        //$request->setCustomerProfileId($profileId);

        $controller = new AnetController\CreateCustomerProfileFromTransactionController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                Mage::log('SUCCESS: PROFILE ID: ' . $response->getCustomerProfileId(), Zend_Log::DEBUG, 'cv_acim.log');
            } else {
                Mage::log('Error code: ' . $response->getMessages()->getMessage()[0]->getCode(), Zend_Log::DEBUG, 'cv_acim.log');
                Mage::log('Error message: ' . $response->getMessages()->getMessage()[0]->getText(), Zend_Log::DEBUG, 'cv_acim.log');

                Mage::throwException(
                    Mage::helper('corevalue_acim')->__('CreateCustomerProfileFromTransaction') .
                    Mage::helper('corevalue_acim')->__('Error code:') . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__('Error message:') . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::log('No response returned', Zend_Log::DEBUG, 'cv_acim.log');
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }
}