<?php

namespace Inteleon\Bankgirot\Importer;

class File
{
    public $layout_name;
    public $layout_version;
    public $datetime;
    public $test_marker;

    public $accounts = array();

    public $num_transactions;
    public $num_deductions;
    public $num_extra_ref;
    public $num_accounts;
}
