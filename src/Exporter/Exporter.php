<?php

namespace Inteleon\Bankgirot\Exporter;

use Exception;

class Exporter
{
    private $newLineChars = "\r\n";
    /* encodeSupplier is an implementation of Bankgiro Leverantörsfaktura to
     * be able to return an array of lines to be saved as a file to send to
     * the bank.
     *
     * @param array $pd Payment data to send as encoded lines.
     *
     * @return array A string array of lines.
     */
    public function encodeSupplier(PaymentData $pd)
    {
        $total_sum = 0;
        $records = [ $this->encodeOpening($pd->from_bankgiro, $pd->creation_date, $pd->payment_date) ];
        foreach ($pd->payments as $payment) {
            // Raise exception if the payment fails.
            $this->validateFields($payment);
            // Only if "Kontantutbetalning eller kontoinsättning med avisering".
            if ($payment->is_deposit) {
                $records[] = $this->encodeDeposit($payment);
            }
            if (is_numeric($payment->plusgiro)) {
                $records[] = $this->encodePlusgiro($payment);
            }
            if (is_numeric($payment->bankgiro)) {
                $records[] = $this->encodePaymentPost($payment);
            }
            $total_sum += $payment->amount;
        }
        $records[] = $this->encodeSummaryPost($pd->from_bankgiro, count($pd->payments), $total_sum);
        return $records;
    }

    private function encodeOpening($from_bankgiro, $creation_date, $payment_date)
    {
        return sprintf(
            "11%010d%6d%22s%6s%13s%3s%18s%s",
            $from_bankgiro,
            $creation_date,
            "LEVERANTÖRSBETALNINGAR", // XXX: This is the only valid value for this field.
            $payment_date,
            "",
            "",
            "",
            $this->newLineChars
        );
    }

    private function encodePaymentPost($payment)
    {
        if (mb_strlen($payment->reference) > 25) {
            $payment->reference = mb_substr($payment->reference, 0, 25);
        }
        if (mb_strlen($payment->sender_reference) > 20) {
            $payment->sender_reference = mb_substr($payment->sender_reference, 0, 20);
        }
        if ($payment->is_deposit) {
            return sprintf(
                "14%09d %-25s%012d%6s%5s%-20s%s",
                $payment->payment_number,
                $payment->reference,
                $payment->amount,
                $payment->payment_date,
                "",
                $payment->sender_reference,
                $this->newLineChars
            );
        }
        return sprintf(
            "14%010d%-25s%012d%6s%5s%-20s%s",
            $payment->bankgiro,
            $payment->reference,
            $payment->amount,
            $payment->payment_date,
            "",
            $payment->sender_reference,
            $this->newLineChars
        );
    }

    private function encodeDeposit($payment)
    {
        if (mb_strlen($payment->reference) > 12) {
            $payment->reference = mb_substr($payment->reference, 0, 12);
        }
        return sprintf(
            "40%04d%05d %4d%012d%-12s%1s%39s%s",
            0,
            $payment->payment_number, // XXX: This is a made up number but unique to the receiver.
            $payment->clearing_number,
            $payment->bankgiro,
            $payment->reference,
            " ", // XXX: extra for salary, not used.
            "",
            $this->newLineChars
        );
    }

    private function encodePlusgiro($payment)
    {
        if (mb_strlen($payment->reference) > 25) {
            $payment->reference = mb_substr($payment->reference, 0, 25);
        }
        if (mb_strlen($payment->sender_reference) > 20) {
            $payment->sender_reference = mb_substr($payment->sender_reference, 0, 20);
        }
        return sprintf(
            "54%010d%-25s%012d%6s%5s%-20s%s",
            $payment->plusgiro,
            $payment->reference,
            $payment->amount,
            $payment->payment_date,
            "",
            $payment->sender_reference,
            $this->newLineChars
        );
    }

    private function encodeSummaryPost($bankgiro, $num_payments, $total_sum, $is_negative = false)
    {
        return sprintf(
            "29%010d%08d%012d%s%47s%s",
            $bankgiro, // sender bankgiro
            $num_payments,
            $total_sum,
            $is_negative ? "-" : " ",
            "",
            $this->newLineChars
        );
    }

    private function validateFields($payment)
    {
        if (!is_numeric($payment->plusgiro) && !is_numeric($payment->bankgiro)) {
            throw new Exception(sprintf("No valid payment method for plusgiro '%s', bankgiro '%s'", $payment->plusgiro, $payment->bankgiro));
        }
        if (is_numeric($payment->plusgiro) && strlen($payment->plusgiro) > 10) {
            throw new Exception(sprintf("Payment number (%s) for method plusgiro in invalid format", $payment->plusgiro));
        }
        if (is_numeric($payment->bankgiro) && strlen($payment->bankgiro) > 12) {
            throw new Exception(sprintf("Payment number (%s) for method bankgiro in invalid format", $payment->bankgiro));
        }
        if ($payment->is_deposit && strlen($payment->clearing_number) != 4) {
            throw new Exception(sprintf("Clearing number (%s) for method bank transfer in invalid format", $payment->bankgiro));
        }
        if (strlen($payment->sender_bankgiro) > 12) {
            throw new Exception(sprintf("Sender bankgiro (%s) too long", $payment->sender_bankgiro));
        }
        if (strlen($payment->amount) > 12) {
            throw new Exception(sprintf("Amount (%s) too long", $payment->amount));
        }
        if (strlen($payment->payment_date) != 6) {
            throw new Exception(sprintf("Payment date (%s) should be exactly 6 characters", $payment->payment_date));
        }
    }
}
