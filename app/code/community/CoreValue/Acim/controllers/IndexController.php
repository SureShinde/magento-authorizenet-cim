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

            // trying to find customer profile id and payment profile id
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

            // All errors will be thrown in helpers
            // Duplicates will be handled by Authorize.Net CIM API, our helpers will also handle such situations.
            // If there is no customer profile will try to create it and to create payment profile,
            // If payment profile will fail, we will try once more in different way in few next steps.
            if (!$profileId) {
                list($profileId, $paymentId) = $helperProfile->processCreateCustomerProfileRequest($data);
            }

            // trying to create payment profile
            if ($profileId && !$paymentId) {
                $helperProfile->processCreatePaymentProfileRequest($profileId, $data);
            }
            // or to update existing payment profile
            elseif ($profileId && $paymentId) {
                $helperProfile->processUpdateCustomerPaymentProfileRequest($profileId, $paymentId, $data);
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
            return $this->_redirect('acimprofiles/index/edit', ['id' => $paymentProfile->getId()]);
        }

        return $this->_redirect('acimprofiles');
    }

}