<?php

namespace Inteleon\Bankgirot\Importer;

class Account
{
    public $bankgiro;
    public $plusgiro;
    public $currency;

    public $transactions = array();

    public $bank_account;
    public $date;
    public $serial_number;
    public $amount;
    public $currency2;
    public $num_transactions;
    public $type;
}
