<?php

class CoreValue_Acim_Model_Resource_Profile_Payment_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('corevalue_acim/profile_payment');
    }
}