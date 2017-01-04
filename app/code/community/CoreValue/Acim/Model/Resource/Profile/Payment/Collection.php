<?php

class CoreValue_Acim_Model_Resource_Profile_Payment_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('corevalue_acim/profile_payment');
    }

    public function addCustomerNameToSelect()
    {
        $firstnameAttr = Mage::getModel('eav/entity_attribute')->loadByCode('1', 'firstname');
        $lastnameAttr = Mage::getModel('eav/entity_attribute')->loadByCode('1', 'lastname');

        $this->getSelect()
            ->join(array('ce1' => 'customer_entity_varchar'), 'ce1.entity_id=main_table.customer_id', array('firstname' => 'value'))
            ->where('ce1.attribute_id='.$firstnameAttr->getAttributeId()) // Attribute code for firstname.
            ->join(array('ce2' => 'customer_entity_varchar'), 'ce2.entity_id=main_table.customer_id', array('lastname' => 'value'))
            ->where('ce2.attribute_id='.$lastnameAttr->getAttributeId()) // Attribute code for lastname.
            ->columns(new Zend_Db_Expr("CONCAT(`ce1`.`value`, ' ',`ce2`.`value`) AS fullname"));

        return $this;
    }
}