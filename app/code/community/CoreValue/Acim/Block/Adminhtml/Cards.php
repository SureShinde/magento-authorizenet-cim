<?php

/**
 * Class CoreValue_Acim_Block_Adminhtml_Cards
 */
class CoreValue_Acim_Block_Adminhtml_Cards extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * CoreValue_Acim_Block_Adminhtml_Cards constructor.
     */
    public function __construct()
    {
        $this->_controller      = 'adminhtml_cards';
        $this->_blockGroup      = 'corevalue_acim';
        $this->_headerText      = Mage::helper('corevalue_acim')->__('Manage Credit Cards');
        parent::__construct();
        $this->_removeButton('add');
    }
}