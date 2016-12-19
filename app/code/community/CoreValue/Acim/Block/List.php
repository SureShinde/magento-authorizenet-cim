<?php
class CoreValue_Acim_Block_List extends Mage_Core_Block_Template
{

    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('corevalue/acim/list.phtml');
    }

    protected function getProfiles()
    {
        return Mage::helper('corevalue_acim')->getPaymentCollection(Mage::helper('customer')->getCustomer()->getId());
    }
}