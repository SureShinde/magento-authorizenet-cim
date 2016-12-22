<?php

class CoreValue_Acim_Block_Adminhtml_Cards_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        /* @var $formData Varien_Object */
        $formData = Mage::registry('form_data');
        /* @var $helperProfile CoreValue_Acim_Helper_Data */
        $helper                 = Mage::helper('corevalue_acim');

        // Instantiate a new form to display our brand for editing.
        $form = new Varien_Data_Form([
            'id'        => 'edit_form',
            'action'    => $this->getUrl('*/*/save', ['_current' => true]),
            'method'    => 'post',
        ]);

        $generalFieldSet = $form->addFieldset('General', ['legend' => $helper->__('Credit Card Details')]);

        $generalFieldSet->addField('cc_type', 'select', array(
            'label'                 => $helper->__('Credit Card Type'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'cc_type',
            'value'                 => $formData->getPaymentProfile()->getCcType(),
            'values'                => Mage::getBlockSingleton('corevalue_acim/card')->getCcAvailableTypes(),
            'disabled'              => false,
        ));

        $generalFieldSet->addField('number', 'text', array(
            'label'                 => $helper->__('Credit Card Number'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'number',
            'value'                 => $formData->getCreditCard()->getNumber(),
            'disabled'              => false,
            'after_element_html'    => '<small>' . $helper->__('Keep it at it is if you do not want to change credit card number') . '</small>',
        ));

        $generalFieldSet->addField('exp_date', 'text', array(
            'label'                 => $helper->__('Expiration Date'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'exp_date',
            'value'                 => $formData->getCreditCard()->getExpDate(),
            'disabled'              => false,
            'after_element_html'    => '<small>' . $helper->__('Keep it at it is if you do not want to change credit card expiration date') . '</small>',
        ));

        $generalFieldSet->addField('cvv', 'text', array(
            'label'                 => $helper->__('Credit Card CVV/CID'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'cvv',
            'value'                 => 'XXX',
            'disabled'              => false,
            'after_element_html'    => '<small>' . $helper->__('Keep it at it is if you do not want to change credit card CVV/CID') . '</small>',
        ));

        $billToFieldSet = $form->addFieldset('Billing Address', ['legend' => $this->__('Billing Address Details')]);

        $billToFieldSet->addField('firstname', 'text', array(
            'label'                 => $helper->__('Firstname'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'firstname',
            'value'                 => $formData->getBillTo()->getFirstname(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('lastname', 'text', array(
            'label'                 => $helper->__('Lastname'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'lastname',
            'value'                 => $formData->getBillTo()->getLastname(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('company', 'text', array(
            'label'                 => $helper->__('Company'),
            'required'              => false,
            'name'                  => 'company',
            'value'                 => $formData->getBillTo()->getCompany(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('address', 'text', array(
            'label'                 => $helper->__('Address'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'address',
            'value'                 => $formData->getBillTo()->getAddress(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('city', 'text', array(
            'label'                 => $helper->__('Address'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'city',
            'value'                 => $formData->getBillTo()->getCity(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('zip', 'text', array(
            'label'                 => $helper->__('Zip'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'zip',
            'value'                 => $formData->getBillTo()->getZip(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('phone', 'text', array(
            'label'                 => $helper->__('Phone'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'phone',
            'value'                 => $formData->getBillTo()->getPhone(),
            'disabled'              => false,
        ));

        $billToFieldSet->addField('fax', 'text', array(
            'label'                 => $helper->__('Fax'),
            'class'                 => 'required-entry',
            'required'              => true,
            'name'                  => 'fax',
            'value'                 => $formData->getBillTo()->getFax(),
            'disabled'              => false,
        ));

        $country = $billToFieldSet->addField('country', 'select', array(
            'name'                  => 'country',
            'label'                 => $helper->__('Country'),
            'values'                => Mage::getModel('adminhtml/system_config_source_country') ->toOptionArray(),
            'value'                 => $formData->getBillTo()->getCountry(),
            'onchange'              => 'getstate(this)',
            'class'                 => 'required-entry',
            'required'              => true,
        ));

        $states = $helper->getStatesArray($formData->getBillTo()->getCountry());
        if ($states && count($states)) {
            $billToFieldSet->addField('region', 'select', array(
                'name'                  => 'region',
                'label'                 => $helper->__('State'),
                'values'                => $states,
                'value'                 => $formData->getBillTo()->getRegion(),
                'class'                 => 'required-entry',
                'required'              => true,
            ));
        } else {
            $billToFieldSet->addField('region', 'text', array(
                'name'                  => 'region',
                'label'                 => $helper->__('State'),
                'values'                => $states,
                'value'                 => $formData->getBillTo()->getRegion(),
                'class'                 => 'required-entry',
                'required'              => true,
            ));
        }

        $country->setAfterElementHtml("<script type=\"text/javascript\">
            function getstate(selectElement) {
                var reloadurl = '". $this->getUrl('*/*/state') . "country/' + selectElement.value;
                new Ajax.Request(reloadurl, {
                    method: 'get',
                    onLoading: function (stateform) {
                        $('region').update('Searching...');
                    },
                    onComplete: function(stateform) {
                        if (stateform.responseText != '') {
                            $('region').replace('<select id=\"region\" name=\"region\" class=\"required-entry select\"></select>');
                            $('region').update('<option>" . $helper->__('--Select State--') . "</option>' + stateform.responseText);
                        } else {
                            $('region').replace('<input type=\"text\" name=\"region\" id=\"region\" class=\"input-text required-entry\" \\/>');
                        }
                    }
                });
            }
        </script>");

        // ToDO: adjust validations

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}