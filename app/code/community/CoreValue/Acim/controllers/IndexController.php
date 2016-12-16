<?php

/**
 * Class CoreValue_Acim_IndexController
 */
class CoreValue_Acim_IndexController extends Mage_Core_Controller_Front_Action
{

    /**
     * List of User's Credit Cards
     */
    public function indexAction()
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Add new Credit Card
     */
    public function addAction()
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Edit existing Credit Card
     */
    public function editAction()
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        // trying to load related profile
        $paymentProfile = Mage::getSingleton('corevalue_acim/profile_payment')->load((int) Mage::app()->getRequest()->getParam('id'));

        if (!$paymentProfile->getId()) {
            Mage::getSingleton('core/session')->addError($this->__('There is no such credit card'));
            return $this->_redirect('acimprofiles');
        }

        $this->addAction();
    }

    /**
     * Delete Credit Card
     */
    public function deleteAction()
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        // trying to load related profile
        $paymentProfile = Mage::getModel('corevalue_acim/profile_payment')->load((int) Mage::app()->getRequest()->getParam('id'));

        if (!$paymentProfile->getId()) {
            Mage::getSingleton('core/session')->addError($this->__('There is no such credit card'));
            return $this->_redirect('acimprofiles');
        }

        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');

        try {
            $helperProfile->processDeletePaymentProfileRequest($paymentProfile->getProfileId(), $paymentProfile->getPaymentId());
            $paymentProfile->delete();
            Mage::getSingleton('core/session')->addSuccess($this->__('The Credit Card has been deleted'));
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($this->__('Error occurs while trying to delete the credit card'));
        }

        return $this->_redirect('acimprofiles');
    }

}