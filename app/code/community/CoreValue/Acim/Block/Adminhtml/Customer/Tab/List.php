<?php

class CoreValue_Acim_Block_Adminhtml_Customer_Tab_List
    extends Mage_Adminhtml_Block_Widget_Grid
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected $_customer = null;

    public function _construct()
    {
        parent::_construct();

        /* @var $this->_customer Mage_Customer_Model_Customer */
        $this->_customer = Mage::registry('current_customer');

        $this->setId('customer_acim_list');
        $this->setUseAjax(true);
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);

        $this->setCollection(Mage::helper('corevalue_acim')->getPaymentCollection($this->_customer->getId()));
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Credit Cards');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Credit Cards');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return (bool) $this->_customer->getId();
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return (!Mage::getSingleton('admin/session')->isAllowed('customer/corevalue_acim') || $this->getCollection()->count()) ? false : true;
    }

    /**
     * Defines after which tab, this tab should be rendered
     *
     * @return string
     */
    public function getAfter()
    {
        return 'orders';
    }

    protected function _prepareColumns()
    {
        $this->addColumn('profile_id', array(
            'header'            => $this->__('Customer Profile Id'),
            'index'             => 'profile_id',
            'width'             => 1,
        ));

        $this->addColumn('payment_id', array(
            'header'            => $this->__('Payment Profile Id'),
            'index'             => 'payment_id',
            'width'             => 1,
        ));

        $this->addColumn('cc_type', array(
            'header'            => $this->__('Credit Card Type'),
            'index'             => 'cc_type',
            'width'             => 1,
        ));

        $this->addColumn('cc_last4', array(
            'header'            => $this->__('Credit Card Number'),
            'index'             => 'cc_last4',
            'filter'            => false,
            'sortable'          => false,
            'renderer'          => 'CoreValue_Acim_Block_Adminhtml_Customer_Tab_Renderer_Number'
        ));

        $this->addColumn('expiration_date', array(
            'header'            => $this->__('Expiration Date'),
            'index'             => 'expiration_date',
            'width'             => 1,
            'filter'            => false,
            'sortable'          => false
        ));

        $this->addColumn('created_at', array(
            'header'            => $this->__('Added'),
            'index'             => 'created_at',
            'type'              => 'datetime',
            'width'             => 1,
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

    public function getRowUrl($row)
    {
        return $this->getUrl('*/acim/edit', ['id' => $row->getId(), 'customer_id' => $row->getCustomerId()]);
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/acim/grid', ['_current' => true]);
    }
}