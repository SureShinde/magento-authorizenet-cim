<?php

class CoreValue_Acim_Block_Adminhtml_Cards extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller      = 'adminhtml_cards';
        $this->_blockGroup      = 'corevalue_acim';
        $this->_headerText      = Mage::helper('corevalue_acim')->__('Manage Credit Cards');
        parent::__construct();
        $this->_removeButton('add');
    }
}