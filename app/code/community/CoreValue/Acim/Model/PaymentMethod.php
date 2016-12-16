<?php
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
     * Defines if this Payment Method uses external payment gateway
     * @var bool
     */
    protected $_isGateway               = true;
    /**
     * Defines if this Payment Method can perform payment authorization
     * @var bool
     */
    protected $_canAuthorize            = true;
    /**
     * Defines if this Payment Method can perform payment capturing
     * @var bool
     */
    protected $_canCapture              = true;
    /**
     * Defines if this Payment Method can perform payment partial capturing
     * @var bool
     */
    protected $_canCapturePartial       = true;
    /**
     * Defines if this Payment Method can perform refunds
     * @var bool
     */
    protected $_canRefund               = true;
    /**
     * Defines if this Payment Method can perform partial refunds
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    /**
     * Defines if this Payment Method can perform payment voiding
     * @var bool
     */
    protected $_canVoid                 = true;
    /**
     * Defines if this Payment Method can be used in admin interface for order creation
     * @var bool
     */
    protected $_canUseInternal          = true;
    /**
     * Defines if this Payment Method can be used for checkout proccess
     * @var bool
     */
    protected $_canUseCheckout          = true;
    /**
     * Defines if this Payment Method can be used for multishipping
     * @var bool
     */
    protected $_canUseForMultishipping  = true;
    /**
     * Defines if this Payment Method can store CC in DB in encrypted way
     * @var bool
     */
    protected $_canSaveCc               = false;

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

    // STATUSES
    const STATUS_APPROVED               = 'Approved';
    const STATUS_SUCCESS                = 'Complete';
    const PAYMENT_ACTION_AUTH_CAPTURE   = 'authorize_capture';
    const PAYMENT_ACTION_AUTH           = 'authorize';
    const STATUS_COMPLETED              = 'Completed';
    const STATUS_DENIED                 = 'Denied';
    const STATUS_FAILED                 = 'Failed';
    const STATUS_REFUNDED               = 'Refunded';
    const STATUS_VOIDED                 = 'Voided';

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

        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');
        /* @var $info Mage_Payment_Model_Info */
        $info                   = $this->getInfoInstance();
        /* @var $customer Mage_Customer_Model_Customer */
        $customer               = $info->getQuote()->getCustomer();

        // setting payment information
        $info
            ->setCcType($data->getCcType())
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

        // trying to get Customer Profile
        $profileCustomer = $helper->getProfileModel($customer->getId(), $customer->getEmail());

        if ($profileCustomer && !empty($profileId = $profileCustomer->getProfileId())) {
            $info->setAdditionalInformation('profile_id', $profileId);

            // populating payment info with payment profile id in case of selection of saved CC(tokenized)
            $params = Mage::app()->getRequest()->getParams();
            $paymentId = (!empty($params['payment']['payment_id']) && $params['payment']['payment_id'])
                ? $params['payment']['payment_id']
                : false;
            $info->setAdditionalInformation('payment_id', $paymentId);

            if ($paymentId) {
                // checking if there is such payment profile, payment profile should belongs to exactly this user
                $paymentModel = $helper->getPaymentModel($profileId, $paymentId);
                if (!$paymentModel && !$paymentModel->getId()) {
                    Mage::throwException($helper->__('Could not find requested payment profile'));
                } else {
                    list($expYear, $expMonth) = explode('-', $paymentModel->getExpirationDate());
                    $info
                        ->setCcLast4($paymentModel->getCcLast4())
                        ->setCcType($paymentModel->getCcType())
                        ->setCcExpMonth($expMonth)
                        ->setCcExpYear($expYear)
                    ;
                }
            }
        }

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment, $amount);

        $this->initialTransaction($payment, $amount, 'authOnlyTransaction');

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        /* @var $helperTransactions CoreValue_Acim_Helper_PaymentTransactions*/
        $helperTransactions     = Mage::helper('corevalue_acim/paymentTransactions');

        // checking if this capture request might be performed using initial auth request
        if (
            $payment->getAuthorizationTransaction()
            && $amount <= ($payment->getBaseAmountAuthorized() - $payment->getBaseAmountPaid())
        ) {
            // trying to capture existing auth transaction for requested amount
            // once this transaction will be captured system will need to create new auth && capture transaction
            // in case of partial payment even if we have captured only part of authorized amount
            $helperTransactions->processCaptureAuthorizedAmountRequest($payment, $amount);
            return $this;
        }

        // otherwise trying to create new auth && capture transaction
        return $this->initialTransaction($payment, $amount, 'authCaptureTransaction');
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);

        /* @var $helperTransactions CoreValue_Acim_Helper_PaymentTransactions*/
        $helperTransactions     = Mage::helper('corevalue_acim/paymentTransactions');

        $transaction = $payment->getTransactionForVoid();

        if (empty($transaction)) {
            $transaction = Mage::getModel('sales/order_payment_transaction')
                ->setOrderPaymentObject($payment)
                ->loadByTxnId($payment->getLastTransId());
        }

        if ($transaction->getTxnId()) {
            $result = $helperTransactions->processRefundTransactionRequest($transaction->getTxnId(), $amount, $payment);

            $payment
                ->setCcApproval($result->getResultCode())
                ->setTransactionId($result->getTransId())
                ->setCcTransId($result->getTransId())
                ->setCcAvsStatus($result->getResultCode())
                ->setCcCidStatus($result->getResultCode())
                ->setStatus(self::STATUS_VOIDED)
                ->setIsTransactionClosed();

            $transaction
                ->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID)
                ->setIsClosed(true)
                ->save();
        }

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment)
    {
        parent::void($payment);

        /* @var $helperTransactions CoreValue_Acim_Helper_PaymentTransactions*/
        $helperTransactions     = Mage::helper('corevalue_acim/paymentTransactions');

        $transaction            = $payment->getTransactionForVoid();

        if (empty($transaction)) {
            $transaction = Mage::getModel('sales/order_payment_transaction')
                ->setOrderPaymentObject($payment)
                ->loadByTxnId($payment->getLastTransId());
        }

        if ($transaction->getTxnId()) {
            $result = $helperTransactions->processVoidTransactionRequest($transaction->getTxnId());

            $payment
                ->setCcApproval($result->getAuthCode())
                ->setTransactionId($result->getTransId())
                ->setCcTransId($result->getTransId())
                ->setCcAvsStatus($result->getAuthCode())
                ->setCcCidStatus($result->getAuthCode())
                ->setStatus(self::STATUS_VOIDED)
                ->setIsTransactionClosed();

            $transaction
                ->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID)
                ->setIsClosed(true)
                ->save();
        }

        return $this;
    }

    /**
     * @return $this|Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        $info = $this->getInfoInstance();

        $payment_id = $info->getAdditionalInformation('payment_id');

        if ($payment_id) {
            return $this;
        }

        return parent::validate();
    }

    /**
     * Method which takes care about initial transaction on order placement(main functionality).
     * Also might be used to create new payment(auth/capture) transaction for existing order.
     *
     * @param Varien_Object $payment
     * @param $amount
     * @param $action
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function initialTransaction(Varien_Object $payment, $amount, $action)
    {
        /* @var $helperTransactions CoreValue_Acim_Helper_PaymentTransactions*/
        $helperTransactions     = Mage::helper('corevalue_acim/paymentTransactions');
        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');
        /* @var $order Mage_Sales_Model_Order */
        $order                  = $payment->getOrder();

        if (!$order->getCustomerIsGuest()) {
            // getting customer profile id and customer payment profile id
            $profileId = $payment->getAdditionalInformation('profile_id');
            $paymentId = $payment->getAdditionalInformation('payment_id');

            // in case if there is valid payment profile will try to perform transaction using it
            if (!$profileId) {
                list($profileId, $paymentId) = $helperProfile->processCreateCustomerProfileRequest($payment);
                $payment->setAdditionalInformation('profile_id', $profileId);
                $payment->setAdditionalInformation('payment_id', $paymentId);
            }

            if ($profileId) {
                if (empty($paymentId)) {
                    $response = $helperProfile->processCreatePaymentProfileRequest($payment);
                    $paymentId = $response->getCustomerPaymentProfileId();
                    $payment->setAdditionalInformation('payment_id', $paymentId);
                }

                // perform payment transaction
                $helperTransactions->processChargeCustomerProfileRequest(
                    $payment,
                    $profileId,
                    $paymentId,
                    $amount,
                    $action
                );
            } else {
                Mage::throwException($helperTransactions->__('Nothing to charge, there is no valid payment information'));
            }
        } else {
            // perform payment transaction
            $helperTransactions->processChargeCreditCardRequest($payment, $amount, $action);
        }

        return $this;
    }
}