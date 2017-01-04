<?php
$this->startSetup();

$table = $this->getConnection()
    ->newTable($this->getTable('corevalue_acim/profile_customer'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
    ), 'Record Id')
    ->addColumn('profile_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
    ), 'Authorize.net customer profile')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
    ), 'Magento customer')
    ->addColumn('email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), 'Customer Email')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Creation Time')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Modification Time')

    ->addIndex($this->getIdxName('corevalue_acim/profile_customer', array('profile_id')), array('profile_id'))
    ->addIndex($this->getIdxName('corevalue_acim/profile_customer', 'customer_id'), 'customer_id')
    ->addIndex($this->getIdxName('corevalue_acim/profile_customer', 'email'), 'email')
;
$this->getConnection()->createTable($table);


$table = $this->getConnection()
    ->newTable($this->getTable('corevalue_acim/profile_payment'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
    ), 'Record Id')
    ->addColumn('profile_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
    ), 'Authorize.net customer profile')
    ->addColumn('payment_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
    ), 'Authorize.net payment profile')
    ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
    ), 'Magento customer')
    ->addColumn('email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable' => false,
    ), 'Customer Email')
    ->addColumn('cc_last4', Varien_Db_Ddl_Table::TYPE_VARCHAR, 4, array(
        'nullable' => false,
    ), 'Last 4 digit of credit card')
    ->addColumn('expiration_date', Varien_Db_Ddl_Table::TYPE_VARCHAR, 7, array(), 'Date of expiration credit card')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Creation Time')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Modification Time')

    ->addIndex($this->getIdxName('corevalue_acim/profile_payment', array('profile_id')), array('profile_id'))
    ->addIndex($this->getIdxName('corevalue_acim/profile_payment', 'customer_id'), 'customer_id')
    ->addIndex($this->getIdxName('corevalue_acim/profile_payment', 'email'), 'email')
    ->addForeignKey(
        $this->getFkName('corevalue_acim/profile_payment', 'profile_id', 'corevalue_acim/profile_payment', 'profile_id'),
        'profile_id',
        $this->getTable('corevalue_acim/profile_customer'),
        'profile_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
;
$this->getConnection()->createTable($table);

$this->endSetup();