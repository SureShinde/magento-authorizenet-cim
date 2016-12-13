<?php
/**
 * User: vpetlya@corevalue.net
 * Date: 29.11.16
 * Time: 17:56
 */

class CoreValue_Acim_Model_PaymentMethod extends Mage_Payment_Model_Method_Cc
{
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'corevalue_acim';

    const ACTION_AUTHORIZE         = 'authorize';
    const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';


    const RESPONSE_APPROVED = 1;
    const RESPONSE_DECLINED = 2;
    const RESPONSE_ERROR = 3;
    const RESPONSE_HELD_FOR_REVIEW = 4;

    /**
     * @var bool
     */
    protected $_isGateway               = true;
    /**
     * @var bool
     */
    protected $_canAuthorize            = true;
    /**
     * @var bool
     */
    protected $_canCapture              = true;
    /**
     * @var bool
     */
    protected $_canCapturePartial       = true;
    /**
     * @var bool
     */
    protected $_canRefund               = true;
    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    /**
     * @var bool
     */
    protected $_canVoid                 = true;
    /**
     * @var bool
     */
    protected $_canUseInternal          = true;
    /**
     * @var bool
     */
    protected $_canUseCheckout          = true;
    /**
     * @var bool
     */
    protected $_canUseForMultishipping  = true;
    /**
     * @var bool
     */
    protected $_canSaveCc = false;

    /**
     * Block that will be used for enter cc data
     * @var string
     */
    protected $_formBlockType           = 'corevalue_acim/form';
    /**
     * Block that will be used for show cc data
     * @var string
     */
    protected $_infoBlockType           = 'corevalue_acim/info';


    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
            ->setCcOwner($data->getCcOwner())
            ->setCcLast4(substr($data->getCcNumber(), -4))
            ->setCcNumber($data->getCcNumber())
            ->setCcCid($data->getCcCid())
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
            ->setCcSsIssue($data->getCcSsIssue())
            ->setCcSsStartMonth($data->getCcSsStartMonth())
            ->setCcSsStartYear($data->getCcSsStartYear())
        ;

        $params = Mage::app()->getRequest()->getParams();
        if (!empty($params['payment']['payment_id']) && $params['payment']['payment_id']) {
            $info->setAdditionalInformation('payment_id', $params['payment']['payment_id']);
        }

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @return array
     */
    public function getProfileAndPaymentIds(Varien_Object $payment)
    {
        $profileId = $payment->getAdditionalInformation('profile_id');
        $paymentId = $payment->getAdditionalInformation('payment_id');

        if (empty($profileId) || empty($paymentId)) {
            $order = $payment->getOrder();
            $customerId = $order->getCustomerId();
            $email = $order->getCustomerEmail();
            /* @var $helper CoreValue_Acim_Helper_Data */
            $helper = Mage::helper('corevalue_acim');

            $paymentProfileCollection = $helper->getPaymentCollection($customerId, $email);
        }

        return [$profileId, $paymentId];
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return $this
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        /* @var $payment Mage_Sales_Model_Order_Payment */
        parent::authorize($payment, $amount);

        /* @var $helperTransactions CoreValue_Acim_Helper_PaymentTransactions*/
        $helperTransactions     = Mage::helper('corevalue_acim/paymentTransactions');
        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');
        /* @var $order Mage_Sales_Model_Order */
        $order                  = $payment->getOrder();

        $isGuest = $order->getCustomerIsGuest();

        if (!$isGuest) {
            list($profileId, $paymentId) = $this->getProfileAndPaymentIds($payment);

            if ($profileId) {
                if (empty($paymentId)) {
                    $response = $helperProfile->processCreatePaymentProfileRequest($profileId, $payment);
                    $paymentId = $response->getCustomerPaymentProfileId();
                }

                $response = $helperTransactions->processChargeCustomerProfileRequest(
                    $order,
                    $profileId,
                    $paymentId,
                    $amount
                );
            } else {
                $response = $helperTransactions->processChargeCreditCardRequest(
                    $payment,
                    $order,
                    $amount
                );

                $transactionId  = $response->getTransactionResponse()->getTransId();
                $customerId     = $order->getCustomerId();
                $customerEmail  = $order->getCustomerEmail();

                $responseProfile = $helperProfile->processCreateCustomerProfileFromTransactionRequest(
                    $transactionId,
                    $customerId,
                    $customerEmail
                );

                $profileCustomer = Mage::getModel('corevalue_acim/profile_customer')
                    ->load($responseProfile->getCustomerProfileId(), 'profile_id');

                $profileCustomer
                    ->setProfileId($responseProfile->getCustomerProfileId())
                    ->setCustomerId($customerId)
                    ->setEmail($customerEmail)
                    ->save()
                ;

                foreach ($responseProfile->getCustomerPaymentProfileIdList() as $paymentId) {
                    $paymentProfile = Mage::getModel('corevalue_acim/profile_payment')->load($paymentId, 'payment_id');

                    $paymentProfile
                        ->setProfileId($responseProfile->getCustomerProfileId())
                        ->setPaymentId($paymentId)
                        ->setCustomerId($customerId)
                        ->setEmail($customerEmail)
                        ->setCcLast4($payment->getCcLast4())
                        ->setExpirationDate($payment->getCcExpYear() . '-' . $payment->getCcExpMonth())
                        ->save()
                    ;
                }
            }
        } else {
            $response = $helperTransactions->processChargeCreditCardRequest($payment->getOrder(), $amount);
        }

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        return parent::refund($payment, $amount);
    }

    /**
     * @param Varien_Object $payment
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment)
    {
        return parent::void($payment);
    }

    /**
     * @return $this|Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        $info = $this->getInfoInstance();

        if ($info->getAdditionalInformation('payment_id')) {
            return $this;
        }
        return parent::validate();
    }
}