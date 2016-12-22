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

    public function editAction()
    {
        // getting get params from request object
        $paymentId      = $this->getRequest()->getParam('id');
        $customerId     = $this->getRequest()->getParam('customer_id');
        /* @var $helperProfile CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');

        // checking parameters, at least one should be present
        if ((!$paymentId && !$customerId) || !$helper->prepareFormData($customerId, $paymentId)) {
            Mage::getSingleton('core/session')->addError('Wrong parameters passed');
            return $this->_redirect('*/cards/index');
        }

        $this
            ->loadLayout()
            ->_setActiveMenu('customer/corevalue_acim')
            ->_addBreadcrumb(Mage::helper('corevalue_acim')->__('Credit Cards'), Mage::helper('corevalue_acim')->__('Edit'))
            ->renderLayout()
        ;
    }

    public function stateAction()
    {
        /* @var $helperProfile CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');

        $countryCode            = $this->getRequest()->getParam('country');

        $states = '';
        if ($countryCode != '') {
            foreach ($helper->getStatesArray($countryCode) as $state) {
                $states .= '<option value="' . $state['value'] . '">' .  $state['label'] . '</option>';
            }
        }
        echo $states;
    }

    public function chooseAction()
    {
        $this
            ->loadLayout()
            ->_setActiveMenu('customer/corevalue_acim')
            ->_addBreadcrumb(Mage::helper('corevalue_acim')->__('Credit Cards'), Mage::helper('corevalue_acim')->__('Choose Customer'))
            ->renderLayout()
        ;
    }

    public function saveAction()
    {
        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');
        /* @var $helper CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');
        /* @var $customer Mage_Customer_Model_Customer */
        $customer               = Mage::getModel('customer/customer')->load((int) Mage::app()->getRequest()->getParam('customer_id'));

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
            return $this->_redirect('*/*/edit', [
                'id'            => (int) Mage::app()->getRequest()->getParam('id'),
                'customer_id'   => (int) Mage::app()->getRequest()->getParam('customer_id'),
            ]);
        }

        Mage::getSingleton('core/session')->addSuccess($this->__(
            Mage::app()->getRequest()->getParam('id')
                ? 'The Credit Card has been updated'
                : 'The Credit Card has been added'
        ));
        return $this->_redirect('*/*/index');
    }



    /**
     * Delete Credit Card
     */
    public function deleteAction()
    {
        // trying to load related profile
        $paymentProfile = Mage::getModel('corevalue_acim/profile_payment')->load((int) Mage::app()->getRequest()->getParam('id'));

        if (!$paymentProfile->getId()) {
            Mage::getSingleton('core/session')->addError($this->__('There is no such credit card'));
            return $this->_redirect('*/*/index');
        }

        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');

        try {
            $helperProfile->processDeletePaymentProfileRequest($paymentProfile->getProfileId(), $paymentProfile->getPaymentId());
            $paymentProfile->delete();
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($this->__('Error occurs while trying to delete the credit card'));
        }

        Mage::getSingleton('core/session')->addSuccess($this->__('The Credit Card has been updated'));
        return $this->_redirect('*/*/index');
    }

    /**
     * Check the permission to run it
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/corevalue_acim');
    }
}