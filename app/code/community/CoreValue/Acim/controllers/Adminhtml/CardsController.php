<?php

/**
 * Class CoreValue_Acim_Adminhtml_CardsController
 */
class CoreValue_Acim_Adminhtml_CardsController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Displaying list of Credit Cards
     */
    public function indexAction()
    {
        $this
            ->loadLayout()
            ->_setActiveMenu('customer/corevalue_acim')
            ->_addBreadcrumb(Mage::helper('corevalue_acim')->__('Credit Cards'), Mage::helper('corevalue_acim')->__('List'))
            ->renderLayout()
        ;
    }

    /**
     * Credit Cards grid.
     */
    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('corevalue_acim/adminhtml_cards_grid')->toHtml()
        );
    }
}