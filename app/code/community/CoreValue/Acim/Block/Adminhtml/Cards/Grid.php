<?php

/**
 * Class CoreValue_Acim_Block_Adminhtml_Cards_Grid
 */
class CoreValue_Acim_Block_Adminhtml_Cards_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    /**
     * CoreValue_Acim_Block_Adminhtml_Cards_Grid constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('corevalue_credit_cards_acim_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * @return mixed
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('corevalue_acim/profile_payment_collection');
        $collection->addCustomerNameToSelect();
        $collection->setOrder('id', 'DESC');

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return mixed
     */
    protected function _prepareColumns()
    {
        $this->addColumn('profile_id', array(
            'header'            => $this->__('Profile Id'),
            'index'             => 'profile_id',
            'width'             => 1,
        ));

        $this->addColumn('payment_id', array(
            'header'            => $this->__('Payment Id'),
            'index'             => 'payment_id',
            'width'             => 1,
        ));

        $this->addColumn('email', array(
            'header'            => $this->__('Email'),
            'index'             => 'email',
        ));

        $this->addColumn('fullname', array(
            'header'            => $this->__('Name'),
            'index'             => 'fullname',
            'filter'            => false,
            'sortable'          => false
        ));

        $this->addColumn('cc_type', array(
            'header'            => $this->__('CC Type'),
            'index'             => 'cc_type',
            'type'              => 'options',
            'options'           => Mage::getBlockSingleton('corevalue_acim/card')->getCcAvailableTypes()
        ));

        $this->addColumn('cc_last4', array(
            'header'            => $this->__('CC Number'),
            'index'             => 'cc_last4',
            'renderer'          => 'CoreValue_Acim_Block_Adminhtml_Customer_Tab_Renderer_Number'
        ));

        $this->addColumn('expiration_date', array(
            'header'            => $this->__('Exp. Date'),
            'index'             => 'expiration_date',
        ));

        $this->addColumn('created_at', array(
            'header'            => $this->__('Added'),
            'index'             => 'created_at',
            'width'             => '180px',
            'type'              => 'datetime',
        ));

        $this->addColumn('action', array(
            'header'            => $this->__('Action'),
            'width'             => '50px',
            'type'              => 'action',
            'getter'            => 'getId',
            'actions'           => array(
                array(
                    'caption'       => $this->__('Edit'),
                    'url'           => array('base'=>'*/acim/view'),
                    'field'         => 'id'
                )
            ),
            'filter'            => false,
            'sortable'          => false,
            'is_system'         => true,
        ));

        return parent::_prepareColumns();
    }

    /**
     * @return mixed
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    /**
     * @param $row
     * @return mixed
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('id' => $row->getId(), 'customer_id' => $row->getCustomerId()));
    }
}
