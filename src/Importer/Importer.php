<?php

namespace Inteleon\BGMAXtools\Importer;

use Exception;

class Importer
{
    private static $rows;
    private static $current_row;
    private static $first_account;
    private static $first_transaction;

    public static function parse($string)
    {
        self::$rows = explode("\n", str_replace("\n\r", "\n", $string));
        self::$current_row = 0;

        return self::parseFile();
    }

    public static function verify(File $BGMaxFile)
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

            if (!self::almostEquals($account_transaction_count, $account->num_transactions)) {
                return 'Wrong number of transactions on account. Expected: ' . $account->num_transactions . ', Got: ' . $account_transaction_count;
            }
            if (!self::almostEquals($account_transaction_amount, $account->amount)) {
                return 'Wrong sum of transactions on account. Expected: ' . $account->amount . ', Got: ' . $account_transaction_amount;
            }
        }

        if (!self::almostEquals($total_transaction_count, $BGMaxFile->num_transactions)) {
            return 'Wrong number of transactions. Expected: ' . $BGMaxFile->num_transactions . ', Got: ' . $total_transaction_count;
        }
        if (!self::almostEquals($total_deduction_count, $BGMaxFile->num_deductions)) {
            return 'Wrong number of deductions. Expected: ' . $BGMaxFile->num_deductions . ', Got: ' . $total_deduction_count;
        }
        if (!self::almostEquals($total_extra_ref_count, $BGMaxFile->num_extra_ref)) {
            return 'Wrong number of extrarefs. Expected: ' . $BGMaxFile->num_deductions . ', Got: ' . $total_extra_ref_count;
        }
        if (!self::almostEquals($total_account_count, $BGMaxFile->num_accounts)) {
            return 'Wrong number of accounts. Expected: ' . $BGMaxFile->num_accounts . ', Got: ' . $total_account_count;
        }

        return true;
    }

    // Privates
    private static function parseFile()
    {
        $result = new File();

        if (self::getData(1, 2) == '01') { // Starpost
            $datetime_str = self::getData(25, 44);
            $result->layout_name = trim(self::getData(3, 22));
            $result->layout_version = intval(self::getData(23, 24));
            $result->datetime = substr($datetime_str, 0, 4).'-'.substr($datetime_str, 4, 2).'-'.substr($datetime_str, 6, 2).' '.substr($datetime_str, 8, 2).':'.substr($datetime_str, 10, 2).':'.substr($datetime_str, 12, 2);
            $result->test_marker = self::getData(45, 45);
            self::nextRow();
        } else {
            throw new Exception('Expected 01, got '.self::getData(1, 2).' row: '.(self::$current_row + 1));
        }

        self::$first_account = true;
        while (self::parseAccounts($result)) {
        }

        if (self::getData(1, 2) == '70') { // Slutpost
            $result->num_transactions = intval(self::getData(3, 10));
            $result->num_deductions = intval(self::getData(11, 18));
            $result->num_extra_ref = intval(self::getData(19, 26));
            $result->num_accounts = intval(self::getData(27, 34));
            self::nextRow();
        } else {
            throw new Exception('Expected 70, got '.self::getData(1, 2).' row: '.(self::$current_row + 1));
        }

        return $result;
    }

    public static function parseAccounts(File &$result)
    {
        $account = new Account();

        if (self::getData(1, 2) == '05') { // Öppningspost
            $account->bankgiro = self::getData(3, 12);
            $account->plusgiro = self::getData(13, 22);
            $account->currency = trim(self::getData(23, 25));
            self::nextRow();
        } else {
            if (self::$first_account) {
                throw new Exception('Expected 05, got '.self::getData(1, 2).' row: '.(self::$current_row + 1));
            } else {
                return false;
            }
        }

        self::$first_transaction = true;
        while (self::parseTransaction($account)) {
        }

        if (self::getData(1, 2) == '15') { // Insättningspost
            $date_str = self::getData(38, 45);
            $account->bank_account = self::getData(3, 37);
            $account->date = substr($date_str, 0, 4).'-'.substr($date_str, 4, 2).'-'.substr($date_str, 6, 2);
            $account->serial_number = intval(self::getData(46, 50));
            $account->amount = intval(self::getData(51, 68)) / 100;
            $account->currency2 = trim(self::getData(69, 71));
            $account->num_transactions = intval(self::getData(72, 79));
            $account->type = self::getData(80, 80);
            self::nextRow();
        } else {
            throw new Exception('Expected 15, got '.self::getData(1, 2).' row: '.(self::$current_row + 1));
        }

        $result->accounts[] = $account;
        self::$first_account = false;

        return true;
    }

    public static function parseTransaction(Account &$account)
    {
        $transaction = new Transaction();

        if (self::getData(1, 2) == '20' || self::getData(1, 2) == '21') { // Betalningspost / Avdragspost
            self::parseRefNum($transaction);
            if (self::getData(1, 2) == '21') {
                $transaction->deduction = true;
                $transaction->deduction_code = self::getData(71, 71);
            }
            self::nextRow();
        } else {
            if (self::$first_transaction) {
                throw new Exception('Expected 20|21, got '.self::getData(1, 2).' row: '.(self::$current_row + 1));
            } else {
                return false;
            }
        }

        while (self::getData(1, 2) == '22' || self::getData(1, 2) == '23') { // Extra referensnummerpost
            $ref_num = new RefNum();
            self::parseRefNum($ref_num);
            if (self::getData(1, 2) == '23') {
                $ref_num->amount = -$ref_num->amount;
            }
            $transaction->extra_ref_num[] = $ref_num;
            self::nextRow();
        }

        while (self::getData(1, 2) == '25') { // Informationspost
            $transaction->information[] = trim(self::getData(3, 52));
            self::nextRow();
        }

        if (self::getData(1, 2) == '26') { // Namnpost
            $transaction->name = trim(self::getData(3, 37));
            $transaction->extra_name = trim(self::getData(38, 72));
            self::nextRow();
        }

        if (self::getData(1, 2) == '27') { // Adresspost 1
            $transaction->address = trim(self::getData(3, 37));
            $transaction->postal_number = trim(self::getData(38, 46));
            self::nextRow();
        }

        if (self::getData(1, 2) == '28') { // Addresspost 2
            $transaction->city = trim(self::getData(3, 37));
            $transaction->country = trim(self::getData(38, 72));
            $transaction->country_code = trim(self::getData(73, 74));
            self::nextRow();
        }

        if (self::getData(1, 2) == '29') { // Organisationsnummberpost
            $transaction->org_num = self::getData(3, 14); // PI!
            self::nextRow();
        }

        $account->transactions[] = $transaction;
        self::$first_transaction = false;

        return true;
    }

    private static function parseRefNum(RefNum $ref_num)
    {
        $ref_num->payer_bankgiro = self::getData(3, 12);
        $ref_num->reference = trim(self::getData(13, 37)); // LEET!
        $ref_num->amount = intval(self::getData(38, 55)) / 100;
        $ref_num->reference_code = self::getData(56, 56);
        $ref_num->channel_code = self::getData(57, 57);
        $ref_num->BGC_number = self::getData(58, 69);
        $ref_num->avi_image = self::getData(70, 70);
    }

    /////////////////////
    // Helper Function //
    /////////////////////
    private static function nextRow()
    {
        self::$current_row++;
    }

    // This is to make the numbers correspond to the bankgiro-documentation.
    private static function getData($start, $end)
    {
        $s = $start - 1;
        $e = $end - $s;

        return substr(self::$rows[self::$current_row], $s, $e);
    }

    public static function almostEquals($a, $b, $epsilon = 0.0001)
    {
        return abs($a - $b) < $epsilon;
    }
}
