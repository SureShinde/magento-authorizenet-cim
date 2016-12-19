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

    /**
     * Updating CC info
     */
    public function updateAction()
    {
        if (!Mage::helper('customer')->isLoggedIn()) {
            return $this->_redirect('customer/account/login');
        }

        if (!$this->_validateFormKey()) {
            Mage::getSingleton('core/session')->addError($this->__('Please, try again to re-fill and submit the form'));
            return $this->_redirect('acimprofiles/index/edit', ['id' => (int) Mage::app()->getRequest()->getParam('id')]);
        }

        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');
        /* @var $customer Mage_Customer_Model_Customer */
        $customer               = Mage::helper('customer')->getCustomer();

        try {
            $profileId = $paymentId = false;

            // trying to load related profile
            $paymentProfile = Mage::getModel('corevalue_acim/profile_payment')->load((int) Mage::app()->getRequest()->getParam('id'));

            if ($paymentProfile && $paymentProfile->getProfileId() && $paymentProfile->getPaymentId()) {
                $profileId = $paymentProfile->getProfileId();
                $paymentId = $paymentProfile->getPaymentId();
            } else {
                $customerProfile = $helper->getProfile($customer->getId());
                if ($customerProfile->getProfileId()) {
                    $profileId = $customerProfile->getProfileId();
                }
            }

            $data = $helper->prepareAcimDataFromPost($customer);

            if (!$profileId) {
                $helperProfile->processCreateCustomerProfileRequest($data);
            }

            if ($profileId && !$paymentId) {
                // new payment
            } elseif ($profileId && $paymentId) {
                // update existing
            }


            $helperProfile->processUpdateCustomerPaymentProfileRequest();
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            return $this->_redirect('acimprofiles/index/edit', ['id' => $paymentProfile->getId()]);
        }

        return $this->_redirect('acimprofiles');
    }

}