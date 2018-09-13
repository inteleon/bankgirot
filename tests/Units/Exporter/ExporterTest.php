<?php

namespace Tests\Units\Exporter;

use Inteleon\Bankgirot\Exporter\Payment;
use Inteleon\Bankgirot\Exporter\PaymentData;
use Inteleon\Bankgirot\Exporter\Exporter;
use PHPUnit\Framework\TestCase;

class ExporterTest extends TestCase
{
    private $exporter;
    /**
     * Setup.
     */
    public function setUp()
    {
        parent::setUp();
        $this->exporter = new Exporter();
    }
    /**
     * @test
     *
     * @covers Inteleon\Bankgirot\Exporter\Exporter::encodeSupplier
     *
     */
    public function encodeSupplier()
    {
        $p = new Payment();
        $p->bankgiro = "9901";
        $p->reference = "56897456986";
        $p->amount = 98000;
        $p->payment_date = "060330";
        $p->sender_reference = "VERIF 12";
        $p->clearing_number = "8440";
        $p->sender_bankgiro = "2391076";
        $p->payment_number = "1";
        $p->is_deposit = true;

        $pd = new PaymentData();
        $pd->from_bankgiro = "2391076";
        $pd->creation_date = "171223";
        $pd->payment_date = "";
        $pd->payments = [$p];

        $v = $this->exporter->encodeSupplier($pd);

        $this->assertEquals(
            4,
            count($v)
        );
        $this->assertEquals(
            $v[0],
            "110002391076171223LEVERANTÖRSBETALNINGAR                                        \r\n"
        );
        $this->assertEquals(
            $v[1],
            "40000000001 844000000000990156897456986                                         \r\n"
        );
        $this->assertEquals(
            $v[2],
            "14000000001 56897456986              000000098000060330     VERIF 12            \r\n"
        );
        $this->assertEquals(
            $v[3],
            "29000239107600000001000000098000                                                \r\n"
        );
    }
    /**
     * @test
     *
     * @covers Inteleon\Bankgirot\Exporter\Exporter::encodeSupplier
     *
     */
    public function encodeSupplierWithoutPayment()
    {
        $p = new Payment();
        $p->bankgiro = "9901";
        $p->reference = "56897456986";
        $p->amount = 98000;
        $p->payment_date = "060330";
        $p->sender_reference = "VERIF 12";
        $p->clearing_number = "8440";
        $p->sender_bankgiro = "2391076";
        $p->identification = "RESEERS";
        $p->is_deposit = false;

        $pd = new PaymentData();
        $pd->from_bankgiro =  "2391076";
        $pd->creation_date =  "171223";
        $pd->payment_date =  "";
        $pd->payments =  [$p];
        
        $v = $this->exporter->encodeSupplier($pd);

        $this->assertEquals(
            3,
            count($v)
        );
        $this->assertEquals(
            $v[0],
            "110002391076171223LEVERANTÖRSBETALNINGAR                                        \r\n"
        );
        $this->assertEquals(
            $v[1],
            "14000000990156897456986              000000098000060330     VERIF 12            \r\n"
        );
        $this->assertEquals(
            $v[2],
            "29000239107600000001000000098000                                                \r\n"
        );
    }
    /**
     * @test
     *
     * @covers Inteleon\Bankgirot\Exporter\Exporter::encodeSupplier
     *
     */
    public function encodeSupplierWithPlusgiro()
    {
        $p = new Payment();
        $p->plusgiro = "099123123";
        $p->reference = "FAKTURA 1";
        $p->amount = 9000;
        $p->payment_date = "GENAST";
        $p->sender_reference = "VERIF 12";
        $p->clearing_number = "8440";
        $p->sender_bankgiro = "2391076";
        $p->identification = "RESEERS";
        $p->is_deposit = false;

        $pd = new PaymentData();
        $pd->from_bankgiro = "2391076";
        $pd->creation_date = "171223";
        $pd->payment_date = "";
        $pd->payments = [$p];

        $v = $this->exporter->encodeSupplier($pd);

        $this->assertEquals(
            3,
            count($v)
        );
        $this->assertEquals(
            $v[1],
            "540099123123FAKTURA 1                000000009000GENAST     VERIF 12            \r\n"
        );
        $this->assertEquals(
            $v[2],
            "29000239107600000001000000009000                                                \r\n"
        );
    }
    /**
     * @test
     *
     * @covers Inteleon\Bankgirot\Exporter\Exporter::encodeSupplier
     *
     */
    public function encodeSupplierWithLongReferences()
    {
        $p = new Payment();
        $p->bankgiro =  "9901";
        $p->reference =  "this_is_a_very_long_reference_that_should_be_truncated";
        $p->amount =  98000;
        $p->payment_date =  "060330";
        $p->sender_reference =  "this_is_a_very_long_reference_that_should_be_truncated";
        $p->clearing_number =  "8440";
        $p->sender_bankgiro =  "2391076";
        $p->is_deposit =  false;

        $pd = new PaymentData();
        $pd->from_bankgiro = "2391076";
        $pd->creation_date = "171223";
        $pd->payment_date = "";
        $pd->payments = [$p];

        $v = $this->exporter->encodeSupplier($pd);

        $this->assertEquals(
            3,
            count($v)
        );
        $this->assertEquals(
            $v[0],
            "110002391076171223LEVERANTÖRSBETALNINGAR                                        \r\n"
        );
        $this->assertEquals(
            $v[1],
            "140000009901this_is_a_very_long_refer000000098000060330     this_is_a_very_long_\r\n"
        );
        $this->assertEquals(
            $v[2],
            "29000239107600000001000000098000                                                \r\n"
        );
    }
    /**
     * @test
     *
     * @covers Inteleon\Bankgirot\Exporter\Exporter::encodeSupplier
     *
     */
    public function encodeSupplierWithBankTxAndClearingNum()
    {
        $p = new Payment();
        $p->bankgiro = "9901";
        $p->reference = "this_is_a_very_long_reference_that_should_be_truncated";
        $p->amount = 98000;
        $p->payment_date = "060330";
        $p->sender_reference = "åäös_is_a_very_long_reference_that_should_be_truncated";
        $p->clearing_number = "8440";
        $p->sender_bankgiro = "2391076";
        $p->payment_number = "2001";
        $p->is_deposit = true;

         $pd = new PaymentData();
         $pd->from_bankgiro = "2391076";
         $pd->creation_date = "171223";
         $pd->payment_date = "";
         $pd->payments = [$p];

        $v = $this->exporter->encodeSupplier($pd);

        $this->assertEquals(
            4,
            count($v)
        );
        $this->assertEquals(
            $v[0],
            "110002391076171223LEVERANTÖRSBETALNINGAR                                        \r\n"
        );
        $this->assertEquals(
            $v[1],
            "40000002001 8440000000009901this_is_a_ve                                        \r\n"
        );
        $this->assertEquals(
            $v[2],
            "14000002001 this_is_a_very_long_refer000000098000060330     åäös_is_a_very_long_\r\n"
        );
        $this->assertEquals(
            $v[3],
            "29000239107600000001000000098000                                                \r\n"
        );
    }
}
