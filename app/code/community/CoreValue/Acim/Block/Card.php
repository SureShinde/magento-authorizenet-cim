<?php

/**
 * Class CoreValue_Acim_Block_Card
 */
class CoreValue_Acim_Block_Card extends Mage_Core_Block_Template
{
    protected $_paymentProfile = null;

    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('corevalue/acim/card.phtml');
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    protected function getPaymentProfile()
    {
        return Mage::getSingleton('corevalue_acim/profile_payment');
    }

    /**
     * @return Varien_Object
     */
    protected function retrievePaymentProfile()
    {
        if ($this->_paymentProfile) {
            return $this->_paymentProfile;
        }

        /* @var $helperProfile CoreValue_Acim_Helper_CustomerProfiles */
        $helperProfile          = Mage::helper('corevalue_acim/customerProfiles');

        if ($this->getPaymentProfile()->getProfileId() && $this->getPaymentProfile()->getPaymentId()) {
            $this->_paymentProfile = $helperProfile->processGetCustomerPaymentProfileRequest(
                $this->getPaymentProfile()->getProfileId(),
                $this->getPaymentProfile()->getPaymentId()
            );

            /* @var $region Mage_Directory_Model_Region */
            $region = Mage::getModel('directory/region');
            $this->_paymentProfile->getBillTo()->setRegionId($region->loadByName(
                $this->_paymentProfile->getBillTo()->getRegion(),
                $this->_paymentProfile->getBillTo()->getCountry()
            )->getRegionId());
        } else {
            $this->_paymentProfile = new Varien_Object([
                'credit_card'   => new Varien_Object([]),
                'bill_to'       => new Varien_Object(['country' => Mage::helper('core')->getDefaultCountry()]),
            ]);
        }

        return $this->_paymentProfile;
    }

    public function getCountryCollection()
    {
        $collection = Mage::getModel('directory/country')->getResourceCollection()
            ->loadByStore();
        $this->setData('country_collection', $collection);

        return $collection;
    }

    public function getCountryHtmlSelect($defValue = null, $name = 'country', $id = 'country', $title = 'Country')
    {
        Varien_Profiler::start('TEST: '.__METHOD__);
        if (is_null($defValue)) {
            $defValue = $this->_paymentProfile->getBillTo()->getCountry();
        }
        $cacheKey = 'DIRECTORY_COUNTRY_SELECT_STORE_'.Mage::app()->getStore()->getCode();
        if (Mage::app()->useCache('config') && $cache = Mage::app()->loadCache($cacheKey)) {
            $options = unserialize($cache);
        } else {
            $options = $this->getCountryCollection()->toOptionArray();
            if (Mage::app()->useCache('config')) {
                Mage::app()->saveCache(serialize($options), $cacheKey, array('config'));
            }
        }
        $html = $this->getLayout()->createBlock('core/html_select')
            ->setName($name)
            ->setId($id)
            ->setTitle(Mage::helper('directory')->__($title))
            ->setClass('validate-select')
            ->setValue($defValue)
            ->setOptions($options)
            ->getHtml();

        Varien_Profiler::stop('TEST: '.__METHOD__);
        return $html;
    }

    /**
     * Retrieve availables credit card types
     *
     * @return array
     */
    public function getCcAvailableTypes()
    {
        $types = Mage::getSingleton('payment/config')->getCcTypes();
        if ($method = Mage::getModel('corevalue_acim/paymentMethod')) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code=>$name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
    }
}