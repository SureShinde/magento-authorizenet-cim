<?php
require_once(Mage::getBaseDir('lib') . DS . 'AuthorizeNetSDK' . DS . 'vendor' . DS . 'autoload.php');
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/**
 * Class CoreValue_Acim_Helper_Data
 */
class CoreValue_Acim_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Getting Customer Profile by customer id
     *
     * @param $customerId
     * @return CoreValue_Acim_Model_Profile_Customer
     */
    public function getProfile($customerId)
    {
        return Mage::getModel('corevalue_acim/profile_customer')->load($customerId, 'customer_id');
    }

    /**
     * Getting Customer Payment profile by payment profile ID
     *
     * @param $paymentId
     * @return bool|object
     */
    public function getPayment($paymentId)
    {
        return Mage::getModel('corevalue_acim/profile_payment')->load($paymentId, 'payment_id');
    }

    /**
     * @param $customerId
     * @return mixed
     */
    public function getPaymentCollection($customerId)
    {
        return Mage::getResourceModel('corevalue_acim/profile_payment_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->setOrder('id', 'DESC')
        ;
    }

    /**
     * Get Customer profile_id and check customer payment profile id at the same time, will return [null, null] in
     * case of failure.
     *
     * @param $paymentId
     * @return array
     */
    public function getPaymentDataByPaymentId($paymentId)
    {
        $profilePayment = Mage::getModel('corevalue_acim/profile_payment')->load($paymentId, 'payment_id');

        if (empty($profilePayment->getId()) || empty($profilePayment->getProfileId())) {
            return [null, null];
        }

        return [$profilePayment->getProfileId(), $paymentId];
    }

    /**
     * Creating Credit Card object as per Authorize.NET CIM specification
     *
     * @param Varien_Object $card
     * @return AnetAPI\CreditCardType
     */
    public function getCreditCardObject(Varien_Object $card)
    {
        $creditCard = new AnetAPI\CreditCardType();

        $creditCard->setCardNumber($card->getNumber());
        $creditCard->setExpirationDate($card->getExpDate());
        if (!empty($card->getCvv()) && strpos($card->getCvv(), 'X') === false) {
            $creditCard->setCardCode($card->getCvv());
        }

        return $creditCard;
    }

    /**
     * @param AnetAPI\CreditCardType $creditCard
     * @return AnetAPI\PaymentType
     */
    public function getPaymentTypeObject(AnetAPI\CreditCardType $creditCard)
    {
        $paymentCreditCard = new AnetAPI\PaymentType();

        $paymentCreditCard->setCreditCard($creditCard);

        return $paymentCreditCard;
    }

    /**
     * Creating Billing Address object as per Authorize.NET CIM specification
     *
     * @param Varien_Object $billingAddress
     * @return AnetAPI\CustomerAddressType
     */
    public function getBillingAddressObject(Varien_Object $billingAddress)
    {
        $billTo = new AnetAPI\CustomerAddressType();

        $billTo
            ->setFirstName($billingAddress->getFirstname())
            ->setLastName($billingAddress->getLastname())
            ->setCompany($billingAddress->getCompany())
            ->setAddress($billingAddress->getAddress())
            ->setCity($billingAddress->getCity())
            ->setState($billingAddress->getRegion())
            ->setZip($billingAddress->getZip())
            ->setCountry($billingAddress->getCountry())
            ->setPhoneNumber($billingAddress->getPhone())
            ->setFaxNumber($billingAddress->getFax())
        ;

        return $billTo;
    }

    /**
     * Getting object of AnetAPI\CustomerPaymentProfileType
     *
     * @param AnetAPI\CustomerAddressType $billTo
     * @param AnetAPI\PaymentType $paymentCreditCard
     * @return AnetAPI\CustomerPaymentProfileType
     */
    public function getCustomerPaymentTypeObject(
        AnetAPI\CustomerAddressType $billTo,
        AnetAPI\PaymentType $paymentCreditCard
    )
    {
        $paymentProfile = new AnetAPI\CustomerPaymentProfileType();

        $paymentProfile->setCustomerType('individual');
        $paymentProfile->setBillTo($billTo);
        $paymentProfile->setPayment($paymentCreditCard);

        return $paymentProfile;
    }

    /**
     * Getting object of AnetAPI\CustomerProfileType
     *
     * @param Mage_Customer_Model_Customer $customer
     * @param AnetAPI\CustomerPaymentProfileType $paymentProfile
     * @return AnetAPI\CustomerProfileType
     */
    public function getCustomerProfileTypeObject(
        Mage_Customer_Model_Customer $customer,
        AnetAPI\CustomerPaymentProfileType $paymentProfile
    )
    {
        $customerProfile = new AnetAPI\CustomerProfileType();

        $customerProfile->setMerchantCustomerId($customer->getId());
        $customerProfile->setEmail($customer->getEmail());
        $customerProfile->setPaymentProfiles([$paymentProfile]);

        return $customerProfile;
    }

    /**
     * Trying to update customer profile if exists, and adding new one if aren't.
     *
     * @param $profileId
     * @param Mage_Customer_Model_Customer $customer
     * @return Mage_Core_Model_Abstract
     */
    public function saveCustomerProfile($profileId, Mage_Customer_Model_Customer $customer)
    {
        /* @var $profile CoreValue_Acim_Model_Profile_Customer */
        $profile = Mage::getModel('corevalue_acim/profile_customer')->load($profileId, 'profile_id');

        $profile
            ->setProfileId($profileId)
            ->setCustomerId($customer->getId())
            ->setEmail($customer->getEmail())
            ->save()
        ;

        return $profile;
    }

    /**
     * Trying to update customer payment profile if exists, and adding new one if aren't.
     *
     * @param $customerProfileId
     * @param $paymentProfileId
     * @param Mage_Customer_Model_Customer $customer
     * @param Varien_Object $creditCard
     * @return CoreValue_Acim_Model_Profile_Payment
     */
    public function saveCustomerPaymentProfile(
        $customerProfileId,
        $paymentProfileId,
        Mage_Customer_Model_Customer $customer,
        Varien_Object $creditCard
    )
    {
        /* @var $profile CoreValue_Acim_Model_Profile_Payment */
        $profile = Mage::getModel('corevalue_acim/profile_payment')->load($paymentProfileId, 'payment_id');

        $profile
            ->setProfileId($customerProfileId)
            ->setPaymentId($paymentProfileId)
            ->setCustomerId($customer->getId())
            ->setEmail($customer->getEmail())
            ->setCcType($creditCard->getType())
        ;

        if (strpos($creditCard->getExpDate(), 'X') === false) {
            $profile->setExpirationDate($creditCard->getExpDate());
        }
        if (strpos($creditCard->getNumber(), 'X') === false) {
            $profile->setCcLast4(substr($creditCard->getNumber(), -4, 4));
        }

        $profile->save();

        return $profile;
    }

    /**
     * @param $paymentId
     * @return AnetAPI\PaymentProfileType
     */
    public function getPaymentProfileTypeObject($paymentId)
    {
        $paymentProfile = new AnetAPI\PaymentProfileType();

        $paymentProfile->setPaymentProfileId($paymentId);

        return $paymentProfile;
    }

    /**
     * @param $profileId
     * @param $paymentProfile
     * @return AnetAPI\CustomerProfilePaymentType
     */
    public function getCustomerProfilePaymentTypeObject($profileId, $paymentProfile)
    {
        $profileToCharge = new AnetAPI\CustomerProfilePaymentType();

        $profileToCharge->setCustomerProfileId($profileId);
        $profileToCharge->setPaymentProfile($paymentProfile);

        return $profileToCharge;
    }

    /**
     * @param $action
     * @param $amount
     * @param $items
     * @param $payment
     * @param $billTo
     * @return AnetAPI\TransactionRequestType
     */
    public function getTransactionRequestTypeObject($action, $amount, $items, $payment, $billTo, $tOrder)
    {
        $transactionRequest = new AnetAPI\TransactionRequestType();

        $transactionRequest->setTransactionType($action);
        $transactionRequest->setAmount($amount);
        $transactionRequest->setLineItems($items);
        $transactionRequest->setOrder($tOrder);

        if ($payment instanceof net\authorize\api\contract\v1\CustomerProfilePaymentType) {
            $transactionRequest->setProfile($payment);
        } else {
            $transactionRequest->setPayment($payment);
            $transactionRequest->setBillTo($billTo);
        }

        return $transactionRequest;
    }

    /**
     * @param $productItem
     * @return AnetAPI\LineItemType
     */
    public function getLineItemTypeObject($productItem)
    {
        $item = new AnetAPI\LineItemType();

        $item->setItemId($productItem->getSku())
            ->setName($productItem->getName())
            ->setQuantity($productItem->getQtyOrdered())
            ->setUnitPrice($productItem->getPrice());

        return $item;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return AnetAPI\OrderType
     */
    public function getOrderTypeObject(Mage_Sales_Model_Order $order)
    {
        $tOrder = new AnetAPI\OrderType();

        $tOrder->setInvoiceNumber($order->getIncrementId());

        return $tOrder;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $response
     * @return mixed
     */
    public function handleTransactionResponse(Mage_Sales_Model_Order_Payment $payment, $response)
    {
        $payment
            ->setStatus(CoreValue_Acim_Model_PaymentMethod::STATUS_APPROVED)
            ->setTransactionId($response->getTransId())
            ->setIsTransactionClosed(0)
            ->setTxnId($response->getTransId())
            ->setParentTxnId($response->getTransId())
            ->setCcTransId($response->getTransId())
        ;

        return $response;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $response
     * @return mixed
     */
    public function updatePayment(Mage_Sales_Model_Order_Payment $payment, $response)
    {
        $payment
            ->setTransactionId($response->getTransId())
            ->setIsTransactionClosed(0)
            ->setTxnId($response->getTransId())
            ->setCcTransId($response->getTransId());

        return $response;
    }

    /**
     * Formatting CC Expiration date with first zero if needed, but actual number will never be longer than 2 chars.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return string
     */
    public function formatExpDate(Mage_Sales_Model_Order_Payment $payment)
    {
        return $payment->getCcExpYear() . '-' . str_pad($payment->getCcExpMonth(), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Prepare Profiles object from $_POST data
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return Varien_Object
     */
    public function prepareAcimDataFromPost(Mage_Customer_Model_Customer $customer)
    {
        // getting post data
        $data           = Mage::app()->getRequest()->getPost();

        // prepare basic information, X chars might be used to keep old value
        $card = [
            'number'        => $data['number'],
            'exp_date'      => $data['exp_date'],
        ];

        // CVV code will appears in this array only in case if there is no X chars because this chars being used to not
        // update this field
        if (strpos($data['cvv'], 'X') === false) {
            $card['cvv'] = $data['cvv'];
        }
        // CC type isn't mandatory, will be determined by Auth.Net automatically
        if (!empty($data['cc_type'])) {
            $card['type'] = $data['cc_type'];
        }

        return new Varien_Object([
            'card'      => new Varien_Object($card),
            'customer'  => $customer,
            'bill_to'   => new Varien_Object([
                'firstname'         => $data['firstname'],
                'lastname'          => $data['lastname'],
                'company'           => $data['company'],
                'address'           => $data['address'],
                'city'              => $data['city'],
                'region'            => empty($data['region']) ? $data['region_id'] : $data['region'],
                'zip'               => $data['zip'],
                'country'           => $data['country'],
                'phone'             => $data['phone'],
                'fax'               => $data['fax'],
            ]),
        ]);
    }

    /**
     * Prepare Profiles object from $payment object
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Varien_Object
     */
    public function prepareAcimDataFromPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        /* @var $billingAddress Mage_Sales_Model_Order_Address */
        $billingAddress = $payment->getOrder()->getBillingAddress();

        $region = '';
        if (!empty($billingAddress->getRegion())) {
            $region = $billingAddress->getRegion();
        } elseif (!empty($billingAddress->getRegionId())) {
            $region = $billingAddress->getRegionId();
        }

        return new Varien_Object([
            'card'      => new Varien_Object([
                'number'            => $payment->getCcNumber(),
                'exp_date'          => $payment->getCcExpYear() . '-' . str_pad($payment->getCcExpMonth(), 2, '0', STR_PAD_LEFT),
                'cvv'               => $payment->getCcCid(),
                'type'              => $payment->getCcType(),
            ]),
            'customer'  => $payment->getOrder()->getCustomer(),
            'bill_to'   => new Varien_Object([
                'firstname'         => $billingAddress->getFirstname(),
                'lastname'          => $billingAddress->getLastname(),
                'company'           => $billingAddress->getCompany(),
                'address'           => $billingAddress->getStreetFull(),
                'city'              => $billingAddress->getCity(),
                'region'            => $region,
                'zip'               => $billingAddress->getPostcode(),
                'country'           => $billingAddress->getCountry(),
                'phone'             => $billingAddress->getTelephone(),
                'fax'               => $billingAddress->getFax(),
            ]),
        ]);
    }

    /**
     * Preparing data for credit card add/edit form in Magento admin interface
     *
     * @param int $customerId
     * @param int $paymentId
     * @return bool
     */
    public function prepareFormData($customerId, $paymentId)
    {
        /* @var $customer Mage_Customer_Model_Customer */
        $customer       = Mage::getModel('customer/customer')->load($customerId);
        /* @var $paymentProfile CoreValue_Acim_Model_Profile_Payment */
        $paymentProfile = Mage::getModel('corevalue_acim/profile_payment')->load($paymentId);

        if (!$customer->getId() && !$paymentProfile->getPaymentId()) {
            return false;
        }

        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');

        if ($paymentProfile->getPaymentId()) {
            $resource = $helperProfile->processGetCustomerPaymentProfileRequest(
                $paymentProfile->getProfileId(),
                $paymentProfile->getPaymentId()
            );
        } else {
            $resource = new Varien_Object([
                'credit_card'   => new Varien_Object([]),
                'bill_to'       => new Varien_Object([]),
            ]);
        }
        $resource->setPaymentProfile($paymentProfile);
        $resource->setCustomere($customer);

        Mage::register('form_data', $resource);

        return true;
    }

    public function getStatesArray($countryCode)
    {
        $array      = [];
        $collection = Mage::getResourceModel('directory/region_collection')->addCountryFilter($countryCode);
        foreach ($collection as $state) {
            //$array[] = ['value' => $state->getCode(), 'label' => $state->getDefaultName()];
            $array[] = ['value' => $state->getDefaultName(), 'label' => $state->getDefaultName()];
        }
        return $array;
    }
}