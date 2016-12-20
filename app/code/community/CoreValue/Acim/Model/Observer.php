<?php

class CoreValue_Acim_Model_Observer
{
    public function customerSaveBefore(Varien_Event_Observer $observer)
    {
        /* @var $customer Mage_Customer_Model_Customer */
        $customer = $observer->getCustomer();

        // checking if email has been changed
        if ($customer->getOrigData('email') != $customer->getEmail()) {
            try {
                /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
                $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');
                /* @var $helper CoreValue_Acim_Helper_Data */
                $helper                 = Mage::helper('corevalue_acim');

                /* @var $customerProfile CoreValue_Acim_Model_Profile_Customer */
                $customerProfile = $helper->getProfile($customer->getId());

                // in case if customer profile exists
                if ($customerProfile->getProfileId()) {
                    // trying to update customer profile in Authorize.Net
                    $helperProfile->processUpdateCustomerProfileRequest(
                        $customerProfile->getProfileId(),
                        $customer->getId(),
                        $customer->getEmail()
                    );

                    // updating the same information on our end, this step will not be executed in case if
                    // has previous step failed
                    $customerProfile
                        ->setEmail($customer->getEmail())
                        ->save()
                    ;
                }
            } catch (Exception $e) {
                Mage::log(
                    'Unable to update customer profile in Auth.Net',
                    Zend_Log::DEBUG,
                    'cv_acim.log'
                );
            }
        }

        return $this;
    }
}