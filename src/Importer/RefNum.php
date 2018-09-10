<?php

namespace Inteleon\BGMAXtools\Importer;

class RefNum
{
    public $payer_bankgiro;
    public $reference;
    public $amount;
    public $reference_code;
    public $channel_code;
    public $BGC_number;
    public $avi_image;
    public $deduction_code; // only for trans = 21
}
