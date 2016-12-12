<?php
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
        $collection = Mage::getResourceModel('corevalue_acim/profile_collection')
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
        $collection = Mage::getResourceModel('corevalue_acim/paymentProfile_collection')
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
        return Mage::getResourceModel('corevalue_acim/paymentProfile_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('email', $email);
    }

    /**
     * @param $profile_id
     * @return mixed
     */
    public function getPaymentCollection2($profile_id)
    {
        return Mage::getResourceModel('corevalue_acim/paymentProfile_collection')
            ->addFieldToFilter('profile_id', $profile_id);
    }
}