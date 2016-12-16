<?php

/**
 * Class CoreValue_Acim_Block_Card
 */
class CoreValue_Acim_Block_Card extends Mage_Core_Block_Template
{
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('corevalue/acim/card.phtml');
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function getPaymentProfile()
    {
        return Mage::getSingleton('corevalue_acim/profile_payment');
    }

    /**
     * @return Varien_Object
     */
    protected function retrievePaymentProfile()
    {
        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');

        return $helperProfile->processGetCustomerPaymentProfileRequest(
            $this->getPaymentProfile()->getProfileId(),
            $this->getPaymentProfile()->getPaymentId()
        );
    }
}