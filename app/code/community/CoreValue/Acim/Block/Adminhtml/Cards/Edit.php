<?php

/**
 * Class CoreValue_Acim_Block_Adminhtml_Cards_Edit
 */
class CoreValue_Acim_Block_Adminhtml_Cards_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{

    /**
     * CoreValue_Acim_Block_Adminhtml_Cards_Edit constructor.
     */
    public function __construct()
    {
        $this->_objectId        = 'id';
        $this->_blockGroup      = 'corevalue_acim';
        $this->_controller      = 'adminhtml_cards';
        $this->_mode            = 'edit';

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return $this->__($this->getRequest()->getParam('id') ? 'Edit Credit Card' : 'New Credit Card');
    }
}