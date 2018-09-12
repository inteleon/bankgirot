<?php

namespace Inteleon\Bankgirot\Importer;

class Transaction extends RefNum
{
    public $deduction = false;
    public $extra_ref_num = array();
    public $information = array();

    public $name;
    public $extra_name;

    public $address;
    public $postal_number;

    public $city;
    public $country;
    public $country_code;

    public $org_num;
}
