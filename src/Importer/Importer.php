<?php

namespace Inteleon\BGMAXtools\Importer;

use Exception;

class Importer
{
    private $rows;
    private $current_row;
    private $first_account;
    private $first_transaction;

    public function parse($string)
    {
        $this->rows = explode("\n", str_replace("\n\r", "\n", $string));
        $this->current_row = 0;

        return $this->parseFile();
    }

    public function verify(File $BGMaxFile)
    {
        if ($BGMaxFile->layout_name != 'BGMAX') {
            return 'Wrong layout name. Expected: BGMAX, Got: ' . $BGMaxFile->layout_name;
        }
        if ($BGMaxFile->layout_version != 1) {
            return 'Wrong layout version. Expected: 1, Got: ' . $BGMaxFile->layout_version;
        }

        $total_transaction_count = 0;
        $total_deduction_count = 0;
        $total_extra_ref_count = 0;
        $total_account_count = 0;
        foreach ($BGMaxFile->accounts as $account) {
            $total_account_count += 1;
            $account_transaction_count = 0;
            $account_transaction_amount = 0;

            foreach ($account->transactions as $transaction) {
                $total_extra_ref_count += count($transaction->extra_ref_num);

                $account_transaction_count   += 1;
                $account_transaction_amount  += $transaction->deduction
                    ? -$transaction->amount
                    :  $transaction->amount;

                if ($transaction->deduction) {
                    $total_deduction_count   += 1;
                } else {
                    $total_transaction_count += 1;
                }
            }

            if (!$this->almostEquals($account_transaction_count, $account->num_transactions)) {
                return 'Wrong number of transactions on account. Expected: ' . $account->num_transactions . ', Got: ' . $account_transaction_count;
            }
            if (!$this->almostEquals($account_transaction_amount, $account->amount)) {
                return 'Wrong sum of transactions on account. Expected: ' . $account->amount . ', Got: ' . $account_transaction_amount;
            }
        }

        if (!$this->almostEquals($total_transaction_count, $BGMaxFile->num_transactions)) {
            return 'Wrong number of transactions. Expected: ' . $BGMaxFile->num_transactions . ', Got: ' . $total_transaction_count;
        }
        if (!$this->almostEquals($total_deduction_count, $BGMaxFile->num_deductions)) {
            return 'Wrong number of deductions. Expected: ' . $BGMaxFile->num_deductions . ', Got: ' . $total_deduction_count;
        }
        if (!$this->almostEquals($total_extra_ref_count, $BGMaxFile->num_extra_ref)) {
            return 'Wrong number of extrarefs. Expected: ' . $BGMaxFile->num_deductions . ', Got: ' . $total_extra_ref_count;
        }
        if (!$this->almostEquals($total_account_count, $BGMaxFile->num_accounts)) {
            return 'Wrong number of accounts. Expected: ' . $BGMaxFile->num_accounts . ', Got: ' . $total_account_count;
        }

        return true;
    }

    // Privates
    private function parseFile()
    {
        $result = new File();

        if ($this->getData(1, 2) == '01') { // Starpost
            $datetime_str = $this->getData(25, 44);
            $result->layout_name = trim($this->getData(3, 22));
            $result->layout_version = intval($this->getData(23, 24));
            $result->datetime = substr($datetime_str, 0, 4).'-'.substr($datetime_str, 4, 2).'-'.substr($datetime_str, 6, 2).' '.substr($datetime_str, 8, 2).':'.substr($datetime_str, 10, 2).':'.substr($datetime_str, 12, 2);
            $result->test_marker = $this->getData(45, 45);
            $this->nextRow();
        } else {
            throw new Exception('Expected 01, got '.$this->getData(1, 2).' row: '.($this->current_row + 1));
        }

        $this->first_account = true;
        while ($this->parseAccounts($result)) {
        }

        if ($this->getData(1, 2) == '70') { // Slutpost
            $result->num_transactions = intval($this->getData(3, 10));
            $result->num_deductions = intval($this->getData(11, 18));
            $result->num_extra_ref = intval($this->getData(19, 26));
            $result->num_accounts = intval($this->getData(27, 34));
            $this->nextRow();
        } else {
            throw new Exception('Expected 70, got '.$this->getData(1, 2).' row: '.($this->current_row + 1));
        }

        return $result;
    }

    public function parseAccounts(File &$result)
    {
        $account = new Account();

        if ($this->getData(1, 2) == '05') { // Öppningspost
            $account->bankgiro = $this->getData(3, 12);
            $account->plusgiro = $this->getData(13, 22);
            $account->currency = trim($this->getData(23, 25));
            $this->nextRow();
        } else {
            if ($this->first_account) {
                throw new Exception('Expected 05, got '.$this->getData(1, 2).' row: '.($this->current_row + 1));
            } else {
                return false;
            }
        }

        $this->first_transaction = true;
        while ($this->parseTransaction($account)) {
        }

        if ($this->getData(1, 2) == '15') { // Insättningspost
            $date_str = $this->getData(38, 45);
            $account->bank_account = $this->getData(3, 37);
            $account->date = substr($date_str, 0, 4).'-'.substr($date_str, 4, 2).'-'.substr($date_str, 6, 2);
            $account->serial_number = intval($this->getData(46, 50));
            $account->amount = intval($this->getData(51, 68)) / 100;
            $account->currency2 = trim($this->getData(69, 71));
            $account->num_transactions = intval($this->getData(72, 79));
            $account->type = $this->getData(80, 80);
            $this->nextRow();
        } else {
            throw new Exception('Expected 15, got '.$this->getData(1, 2).' row: '.($this->current_row + 1));
        }

        $result->accounts[] = $account;
        $this->first_account = false;

        return true;
    }

    public function parseTransaction(Account &$account)
    {
        $transaction = new Transaction();

        if ($this->getData(1, 2) == '20' || $this->getData(1, 2) == '21') { // Betalningspost / Avdragspost
            $this->parseRefNum($transaction);
            if ($this->getData(1, 2) == '21') {
                $transaction->deduction = true;
                $transaction->deduction_code = $this->getData(71, 71);
            }
            $this->nextRow();
        } else {
            if ($this->first_transaction) {
                throw new Exception('Expected 20|21, got '.$this->getData(1, 2).' row: '.($this->current_row + 1));
            } else {
                return false;
            }
        }

        while ($this->getData(1, 2) == '22' || $this->getData(1, 2) == '23') { // Extra referensnummerpost
            $ref_num = new RefNum();
            $this->parseRefNum($ref_num);
            if ($this->getData(1, 2) == '23') {
                $ref_num->amount = -$ref_num->amount;
            }
            $transaction->extra_ref_num[] = $ref_num;
            $this->nextRow();
        }

        while ($this->getData(1, 2) == '25') { // Informationspost
            $transaction->information[] = trim($this->getData(3, 52));
            $this->nextRow();
        }

        if ($this->getData(1, 2) == '26') { // Namnpost
            $transaction->name = trim($this->getData(3, 37));
            $transaction->extra_name = trim($this->getData(38, 72));
            $this->nextRow();
        }

        if ($this->getData(1, 2) == '27') { // Adresspost 1
            $transaction->address = trim($this->getData(3, 37));
            $transaction->postal_number = trim($this->getData(38, 46));
            $this->nextRow();
        }

        if ($this->getData(1, 2) == '28') { // Addresspost 2
            $transaction->city = trim($this->getData(3, 37));
            $transaction->country = trim($this->getData(38, 72));
            $transaction->country_code = trim($this->getData(73, 74));
            $this->nextRow();
        }

        if ($this->getData(1, 2) == '29') { // Organisationsnummberpost
            $transaction->org_num = $this->getData(3, 14); // PI!
            $this->nextRow();
        }

        $account->transactions[] = $transaction;
        $this->first_transaction = false;

        return true;
    }

    private function parseRefNum(RefNum $ref_num)
    {
        $ref_num->payer_bankgiro = $this->getData(3, 12);
        $ref_num->reference = trim($this->getData(13, 37)); // LEET!
        $ref_num->amount = intval($this->getData(38, 55)) / 100;
        $ref_num->reference_code = $this->getData(56, 56);
        $ref_num->channel_code = $this->getData(57, 57);
        $ref_num->BGC_number = $this->getData(58, 69);
        $ref_num->avi_image = $this->getData(70, 70);
    }

    /////////////////////
    // Helper Function //
    /////////////////////
    private function nextRow()
    {
        $this->current_row++;
    }

    // This is to make the numbers correspond to the bankgiro-documentation.
    private function getData($start, $end)
    {
        $s = $start - 1;
        $e = $end - $s;

        return substr($this->rows[$this->current_row], $s, $e);
    }

    public function almostEquals($a, $b, $epsilon = 0.0001)
    {
        return abs($a - $b) < $epsilon;
    }
}
