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
     * @param $customerId
     * @param $email
     * @return mixed
     */
    public function getProfileModel($customerId, $email)
    {
        $collection = Mage::getResourceModel('corevalue_acim/profile_customer_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('email', $email);

        return $collection->getFirstItem();
    }

    /**
     * @param $profileId
     * @param $paymentId
     * @return bool|object
     */
    public function getPaymentModel($profileId, $paymentId)
    {
        $collection = Mage::getResourceModel('corevalue_acim/profile_payment_collection')
            ->addFieldToFilter('profile_id', $profileId)
            ->addFieldToFilter('payment_id', $paymentId);

        return $collection->getFirstItem();
    }

    /**
     * @param $customerId
     * @param $email
     * @return mixed
     */
    public function getPaymentCollection($customerId, $email)
    {
        return Mage::getResourceModel('corevalue_acim/profile_payment_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('email', $email);
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
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return AnetAPI\CreditCardType
     */
    public function getCreditCardObject(Mage_Sales_Model_Order_Payment $payment)
    {
        $creditCard = new AnetAPI\CreditCardType();

        $creditCard->setCardNumber($payment->getCcNumber());
        $creditCard->setExpirationDate(
            $payment->getCcExpYear() . '-' . str_pad($payment->getCcExpMonth(), 2, '0', STR_PAD_LEFT)
        );
        $creditCard->setCardCode($payment->getCcCid());

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
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return AnetAPI\CustomerAddressType
     */
    public function getBillingAddressObject(Mage_Sales_Model_Order_Payment $payment)
    {
        $billTo = new AnetAPI\CustomerAddressType();

        /* @var $billingAddress Mage_Sales_Model_Order_Address */
        $billingAddress = $payment->getOrder()->getBillingAddress();

        // Create the Bill To info
        $region = '';
        if (!empty($billingAddress->getRegion())) {
            $region = $billingAddress->getRegion();
        } elseif (!empty($billingAddress->getRegionId())) {
            $region = $billingAddress->getRegionId();
        }

        $billTo
            ->setFirstName($billingAddress->getFirstname())
            ->setLastName($billingAddress->getLastname())
            ->setCompany($billingAddress->getCompany())
            ->setAddress($billingAddress->getStreetFull())
            ->setCity($billingAddress->getCity())
            ->setState($region)
            ->setZip($billingAddress->getPostcode())
            ->setCountry($billingAddress->getCountry())
            ->setPhoneNumber($billingAddress->getTelephone())
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
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param AnetAPI\CustomerPaymentProfileType $paymentProfile
     * @return AnetAPI\CustomerProfileType
     */
    public function getCustomerProfileTypeObject(
        Mage_Sales_Model_Order_Payment $payment,
        AnetAPI\CustomerPaymentProfileType $paymentProfile
    )
    {
        $customerProfile = new AnetAPI\CustomerProfileType();

        $customerProfile->setMerchantCustomerId($payment->getOrder()->getCustomer()->getId());
        $customerProfile->setEmail($payment->getOrder()->getCustomer()->getEmail());
        $customerProfile->setPaymentProfiles([$paymentProfile]);

        return $customerProfile;
    }

    /**
     * Trying to update customer profile if exists, and adding new one if aren't.
     *
     * @param $profileId
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Mage_Core_Model_Abstract
     */
    public function saveCustomerProfile($profileId, Mage_Sales_Model_Order_Payment $payment)
    {
        /* @var $profile CoreValue_Acim_Model_Profile_Customer */
        $profile = Mage::getModel('corevalue_acim/profile_customer')->load($profileId, 'profile_id');

        $profile
            ->setProfileId($profileId)
            ->setCustomerId($payment->getOrder()->getCustomer()->getId())
            ->setEmail($payment->getOrder()->getCustomer()->getEmail())
            ->save()
        ;

        return $profile;
    }

    /**
     * Trying to update customer payment profile if exists, and adding new one if aren't.
     *
     * @param $paymentProfileId
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return CoreValue_Acim_Model_Profile_Payment
     */
    public function saveCustomerPaymentProfile($paymentProfileId, Mage_Sales_Model_Order_Payment $payment)
    {
        /* @var $profile CoreValue_Acim_Model_Profile_Payment */
        $profile = Mage::getModel('corevalue_acim/profile_payment')->load($paymentProfileId, 'payment_id');

        $profile
            ->setProfileId($payment->getAdditionalInformation('profile_id'))
            ->setPaymentId($paymentProfileId)
            ->setCustomerId($payment->getOrder()->getCustomer()->getId())
            ->setEmail($payment->getOrder()->getCustomer()->getEmail())
            ->setCcLast4($payment->getCcLast4())
            ->setCcType($payment->getCcType())
            ->setExpirationDate($this->formatExpDate($payment))
            ->save()
        ;

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
        //$tOrder->setDescription("Product Description");

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
            //->setStatus(CoreValue_Acim_Model_PaymentMethod::STATUS_APPROVED)
            ->setTransactionId($response->getTransId())
            ->setIsTransactionClosed(0)
            ->setTxnId($response->getTransId())
            //->setParentTxnId($response->getTransId())
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
}