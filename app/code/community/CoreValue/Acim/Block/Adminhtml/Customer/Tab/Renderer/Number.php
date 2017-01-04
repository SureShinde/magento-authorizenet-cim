<?php

class CoreValue_Acim_Block_Adminhtml_Customer_Tab_Renderer_Number extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $value =  $row->getData($this->getColumn()->getIndex());
        return 'XXXX-XXXX-XXXX-' . $value;
    }
}