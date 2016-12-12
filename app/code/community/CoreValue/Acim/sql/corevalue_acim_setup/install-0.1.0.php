<?php

$this->startSetup();

$table = $this->getConnection()
    ->newTable($this->getTable('corevalue_acim/customer_profile'))
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
    ->addColumn('creation_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Creation Time')
    ->addColumn('update_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Modification Time')

    ->addIndex($this->getIdxName('corevalue_acim/customer_profile', array('profile_id')), array('profile_id'))
    ->addIndex($this->getIdxName('corevalue_acim/customer_profile', 'customer_id'), 'customer_id')
    ->addIndex($this->getIdxName('corevalue_acim/customer_profile', 'email'), 'email')
;
$this->getConnection()->createTable($table);


$table = $this->getConnection()
    ->newTable($this->getTable('corevalue_acim/payment_profile'))
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
    ->addColumn('cc_status', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'default'   => '0',
    ), 'Status of credit card')
    ->addColumn('expiration_date', Varien_Db_Ddl_Table::TYPE_DATE, null, array(), 'Date of expiration credit card')
    ->addColumn('creation_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Creation Time')
    ->addColumn('update_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(), 'Profile Modification Time')

    ->addIndex($this->getIdxName('corevalue_acim/payment_profile', array('profile_id')), array('profile_id'))
    ->addIndex($this->getIdxName('corevalue_acim/payment_profile', 'customer_id'), 'customer_id')
    ->addIndex($this->getIdxName('corevalue_acim/payment_profile', 'email'), 'email')
    ->addForeignKey($this->getFkName('corevalue_acim/payment_profile', 'profile_id', 'corevalue_acim/customer_profile', 'profile_id'),
        'profile_id', $this->getTable('corevalue_acim/customer_profile'), 'profile_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
;
$this->getConnection()->createTable($table);

$this->endSetup();