<?php

class CoreValue_Acim_Model_Resource_PaymentProfile_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('corevalue_acim/paymentProfile');
    }
}