<?php

class CoreValue_Acim_Block_Checkout_Info extends Mage_Payment_Block_Info_Cc
{
    /**
     * Checkout progress information block flag
     *
     * @var bool
     */
    protected $_isCheckoutProgressBlockFlag = true;
    /**
     * Set block template
     */
    protected function _construct()
    {
        parent::_construct();
    }

    /**
     * Retrieve card info object
     *
     * @return mixed
     */
    public function getInfo()
    {
        if ($this->hasCardInfoObject()) {
            return $this->getCardInfoObject();
        }
        return parent::getInfo();
    }

    /**
     * Set checkout progress information block flag
     * to avoid showing credit card information from payment quote
     * in Previously used card information block
     *
     * @param bool $flag
     * @return Mage_Paygate_Block_Authorizenet_Info_Cc
     */
    public function setCheckoutProgressBlock($flag)
    {
        $this->_isCheckoutProgressBlockFlag = $flag;
        return $this;
    }
}
