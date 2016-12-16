<?php
$this->startSetup();

$this->getConnection()
    ->addColumn(
        $this->getTable('corevalue_acim/profile_payment'),
        'cc_type',
        [
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'nullable'  => true,
            'length'    => 2,
            'after'     => 'cc_last4',
            'default'   => NULL,
            'comment'   => 'Credit Card Type',
        ]
    );

$this->endSetup();