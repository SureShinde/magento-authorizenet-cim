<?php

/**
 *
 * Authorize.net Payment Action Dropdown source
 *
 */
class CoreValue_Acim_Model_Authorizenet_Source_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => CoreValue_Acim_Model_PaymentMethod::ACTION_AUTHORIZE,
                'label' => Mage::helper('corevalue_acim')->__('Authorize Only')
            ),
            array(
                'value' => CoreValue_Acim_Model_PaymentMethod::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('corevalue_acim')->__('Authorize and Capture')
            ),
        );
    }
}