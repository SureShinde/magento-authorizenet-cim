<?php

/**
 * Payment method form base block
 */
class CoreValue_Acim_Block_Form extends Mage_Payment_Block_Form
{
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('corevalue/acim/form.phtml');
    }

    /**
     * Retreive payment method form html
     *
     * @return string
     */
    public function getMethodFormBlock()
    {
        return $this->getLayout()->createBlock('payment/form_cc')
            ->setMethod($this->getMethod());
    }

    /**
     * Cards info block
     *
     * @return string
     */
    public function getCardsBlock()
    {
        return $this->getLayout()->createBlock('corevalue_acim/info')
            ->setMethod($this->getMethod())
            ->setInfo($this->getMethod()->getInfoInstance())
            ->setCheckoutProgressBlock(false)
            ->setHideTitle(true);
    }

    public function getPaymentProfiles()
    {
        return ;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        //$this->setChild('cards', $this->getCardsBlock());
        $this->setChild('method_form_block', $this->getMethodFormBlock());
        return parent::_toHtml();
    }
}
