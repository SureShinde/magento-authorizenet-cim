<?php

require_once(Mage::getBaseDir('lib') . DS . 'AuthorizeNetSDK' . DS . 'vendor' . DS . 'autoload.php');
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

//define("AUTHORIZENET_LOG_FILE", "corevalue_acim");

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

    /**
     * @param $customerId
     * @param $customerEmail
     * @param $billingInfo
     * @return object
     */
    public function processCreateCustomerProfileRequest($customerId, $customerEmail, $billingInfo)
    {
        // Create the payment data for a credit card
        $ccNumber = htmlentities($billingInfo['payment']['cc_number']);
        $ccExpDate = intval($billingInfo['payment']['cc_exp_year']) . '-' . str_pad(intval($billingInfo['payment']['cc_exp_month']), 2, '0', STR_PAD_LEFT);
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

        $paymentProfile = new AnetAPI\CustomerPaymentProfileType();
            $paymentProfile->setCustomerType('individual');
            $paymentProfile->setBillTo($billTo);
            $paymentProfile->setPayment($paymentCreditCard);
        $paymentProfiles[] = $paymentProfile;
        $customerProfile = new AnetAPI\CustomerProfileType();
            $customerProfile->setMerchantCustomerId($customerId);
            $customerProfile->setEmail($customerEmail);
            $customerProfile->setPaymentProfiles($paymentProfiles);

        $request = $this->initNewRequest(new AnetAPI\CreateCustomerProfileRequest());
            $request->setProfile($customerProfile);
        $controller = new AnetController\CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "Succesfully create customer profile : " . $response->getCustomerProfileId() . "\n";
                $paymentProfiles = $response->getCustomerPaymentProfileIdList();
                echo "SUCCESS: PAYMENT PROFILE ID : " . $paymentProfiles[0] . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("CreateCustomerProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
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
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }


    /**
     * Get all existing customer profile ID's
     * @param $profileId
     * @return object
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
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * Update Customer Payment Profile
     * @param $profileId
     * @param $billingInfo
     * @return object
     */
    public function processUpdateCustomerProfileRequest($profileId, $customerId, $email, $description)
    {
        $customerProfile = new AnetAPI\CustomerProfileExType();
        $customerProfile->setCustomerProfileId($profileId)
            ->setMerchantCustomerId($customerId)
            ->setDescription($description)
            ->setEmail($email);

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
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * Delete an existing customer profile
     * @param $profileId
     * @return object
     */
    public function processDeleteCustomerProfileRequest($profileId) {
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
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * Create the payment data for a credit card
     * @param $profileId
     * @param $billingInfo
     * @return object
     */
    public function processCreatePaymentProfileRequest($profileId, $billingInfo)
    {
        $ccNumber = htmlentities($billingInfo['payment']['cc_number']);
        $ccExpDate = intval($billingInfo['payment']['cc_exp_year']) . '-' . str_pad(intval($billingInfo['payment']['cc_exp_month']), 2, '0', STR_PAD_LEFT);
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
        $paymentProfile = new AnetAPI\CustomerPaymentProfileType();
            $paymentProfile->setCustomerType('individual');
            $paymentProfile->setBillTo($billTo);
            $paymentProfile->setPayment($paymentCreditCard);
            //$paymentProfile->setDefaultPaymentProfile(true);

        $request = $this->initNewRequest(new AnetAPI\CreateCustomerPaymentProfileRequest());
            $request->setCustomerProfileId($profileId);
            $request->setPaymentProfile($paymentProfile);
        $controller = new AnetController\CreateCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->_mode);
        if (($response != null)) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "Create Customer Payment Profile SUCCESS: " . $response->getCustomerPaymentProfileId() . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("CreatePaymentProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

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
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "GetCustomerPaymentProfile SUCCESS: " . "\n";
                echo "Customer Payment Profile Id: " . $response->getPaymentProfile()->getCustomerPaymentProfileId() . "\n";
                echo "Customer Payment Profile Billing Address: " . $response->getPaymentProfile()->getbillTo()->getAddress() . "\n";
                echo "Customer Payment Profile Card Last 4 " . $response->getPaymentProfile()->getPayment()->getCreditCard()->getCardNumber() . "\n";

                if ($response->getPaymentProfile()->getSubscriptionIds() != null) {
                    if ($response->getPaymentProfile()->getSubscriptionIds() != null) {
                        echo "List of subscriptions:";
                        foreach ($response->getPaymentProfile()->getSubscriptionIds() as $subscriptionid)
                            echo $subscriptionid . "\n";
                    }
                }
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("GetCustomerPaymentProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code: ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message: ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
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
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "SUCCESS: Delete Customer Payment Profile  SUCCESS:" . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("DeletePaymentProfile ") .
                    Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }

    /**
     * @param $profileId
     * @param $paymentId
     * @return object
     */
    public function processCreateCustomerProfileFromTransactionRequest($transactionId, $customerId, $email, $description = '')
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
            if ($response->getMessages()->getResultCode() == "Ok") {
                echo "SUCCESS: PROFILE ID : " . $response->getCustomerProfileId() . "\n";
            } else {
                Mage::throwException(
                    Mage::helper('corevalue_acim')->__("CreateCustomerProfileFromTransaction ") .
                    Mage::helper('corevalue_acim')->__(" Error code  : ") . $response->getMessages()->getMessage()[0]->getCode() . "\n".
                    Mage::helper('corevalue_acim')->__(" Error message  : ") . $response->getMessages()->getMessage()[0]->getText() . "\n"
                );
            }
        } else {
            Mage::throwException(Mage::helper('corevalue_acim')->__('No response returned.'));
        }

        return $response;
    }
}