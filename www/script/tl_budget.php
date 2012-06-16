<?php

session_start();

include('simplestore.php');

/*
class TL_Transaction_Trigger extends SimpleStoreTrigger
{
	function __construct() {
		parent::__construct('transaction_trigger', 'transaction');

        $this->monthsToUpdate = Array();
	}
	function postUpdateCallback($store, $type, $key, $data)
	{
        $month = $data->month;
        $this->monthsToUpdate[$month] = 1;

		error_log("Received transaction trigger: $key ($month)");
    }

    function transactionCompleteCallback($store)
    {
        $found_change = False;
        foreach ($this->monthsToUpdate as $month => $true)
        {
            error_log("Calculating month totals for $month.");

            $account_totals = array();
            $fund_totals = array();

            $transactions = $store->fetchFromIndex('trans_month', $month);
            foreach ($transactions["transaction"] as $key => $transaction_json)
            {
                $transaction = json_decode($transaction_json[0]);

                $transaction_net = 0;

                foreach ($transaction->entries as $entry)
                {
                    $credit = 0;
                    $debit = 0;
                    if (isset($entry->credit))
                        $credit = $entry->credit;
                    if (isset($entry->debit))
                        $debit = $entry->debit;

                    if (isset($entry->account))
                    {
                        if (!isset($account_totals[$entry->account]))
                            $account_totals[$entry->account] = 0;

                        $account_totals[$entry->account] += $credit - $debit;
                        $account_totals[$entry->account] = round($account_totals[$entry->account], 2);
                        $transaction_net += $credit - $debit;
                    }

                    if (isset($entry->fund))
                    {
                        if (!isset($fund_totals[$entry->fund]))
                            $fund_totals[$entry->fund] = 0;
                        $fund_totals[$entry->fund] += $credit - $debit;
                        $fund_totals[$entry->fund] = round($fund_totals[$entry->fund], 2);
                    }
                }
                foreach ($transaction->allocs as $alloc)
                {
                    if (isset($alloc->fund))
                    {
                        if (!isset($fund_totals[$entry->fund]))
                            $fund_totals[$entry->fund] = 0;
                        $fund_totals[$entry->fund] += $alloc->alloc;
                        $fund_totals[$entry->fund] = round($fund_totals[$entry->fund], 2);
                    }
                }

                $transaction_net = round($transaction_net, 2);
                if ($transaction_net != 0)
                    error_log("Unbalanced transaction $key: $transaction->description on $transaction->date: $transaction_net");
            }

            foreach ($account_totals as $account => $amount)
            {
                $key = $store->domain . ":MT" . $month . substr($account, strlen($store->domain)+2);
                $month_total = array();
                $month_total['month'] = $month;
                $month_total['account'] = $account;
                $month_total['total'] = $amount;
                $month_total['running_total'] = 0;

                $total_json = json_encode($month_total);
                $existing_total = $store->fetchObject($key);
                if ($existing_total != '') {
                    $decoded_total = json_decode($existing_total);
                    if ($decoded_total->total != $amount)
                    {
                        $store->updateObject('account_total', $key, $total_json);
                        $found_change = True;
                    }
                } else {
                    $store->storeObject('account_total', $key, $total_json);
                    $found_change = True;
                }
            }

            foreach ($fund_totals as $fund => $amount)
            {
                $key = $store->domain . ":FT" . $month . substr(hash('crc32',$fund,FALSE), 4, 4);
                $month_total = array();
                $month_total['month'] = $month;
                $month_total['fund'] = $fund;
                $month_total['total'] = $amount;
                $month_total['running_total'] = 0;

                $total_json = json_encode($month_total);
                $existing_total = $store->fetchObject($key);
                if ($existing_total != '') {
                    $decoded_total = json_decode($existing_total);
                    if ($decoded_total->total != $amount)
                    {
                        $store->updateObject('fund_total', $key, $total_json);
                        $found_change = True;
                    }
                } else {
                    $store->storeObject('fund_total', $key, $total_json);
                    $found_change = True;
                }
            }
        }

        if ($found_change)
        {
            $account_totals = array();
            $month_totals = $store->fetchFromIndex('account_total', '*');
            foreach ($month_totals["account_total"] as $key => $total_json)
            {
                $month_total = json_decode($total_json[0]);
                $month = $month_total->month;
                $account = $month_total->account;
                $total = $month_total->total;

                if (!isset($account_totals[$account]))
                    $account_totals[$account] = 0;
                $account_totals[$account] += $total;
                $account_totals[$account] = round($account_totals[$account], 2);

                if (((float)$month_total->running_total) != $account_totals[$account])
                {
                    $month_total->running_total = $account_totals[$account];

                    error_log("Updating total for account $account for month $month.");
                    $store->updateObject('account_total', $key, json_encode($month_total));
                }
            }

            $fund_totals = array();
            $month_totals = $store->fetchFromIndex('fund_total', '*');
            foreach ($month_totals["fund_total"] as $key => $total_json)
            {
                $month_total = json_decode($total_json[0]);
                $month = $month_total->month;
                $fund = $month_total->fund;
                $total = $month_total->total;

                if (!isset($fund_totals[$fund]))
                    $fund_totals[$fund] = 0;
                $fund_totals[$fund] += $total;
                $fund_totals[$fund] = round($fund_totals[$fund], 2);

                if (((float)$month_total->running_total) != $fund_totals[$fund])
                {
                    $month_total->running_total = $fund_totals[$fund];

                    error_log("Updating total for fund $fund for month $month.");
                    $store->updateObject('fund_total', $key, json_encode($month_total));
                }
            }
        }
        $this->monthsToUpdate = Array();
    }
}
*/

$domain = "__NOT_LOGGED_IN__";
if (isset($_SESSION['db_login_domain']) && $_SESSION['db_login_domain']) {
    $domain = $_SESSION['db_login_domain'];
}

$store = new SimpleStore('tomyedwa_dbcom1', '3tL4Us2F,-mw', 'tomyedwa_tlbudget2012', $domain);

// Without this, login doesn't work
$store->addObjectIndex(new SimpleStoreObjectIndex('user_login', array('user'), '.login'));

// Withdrawals
$store->addObjectIndex(new SimpleStoreObjectIndex('withdrawal/name', array('withdrawal'), '.name'));
$store->addObjectIndex($month_idx = new SimpleStoreObjectIndex('withdrawal/month', array('withdrawal'), '.month'));
$store->addObjectIndex($account_out_idx = new SimpleStoreObjectIndex('withdrawal/account_out', array('withdrawal'), '.account'));
$store->addObjectIndex($amount_out_idx = new SimpleStoreObjectIndex('withdrawal/amount_out', array('withdrawal'), '.amount'));
$store->addObjectIndex($account_in_idx = new SimpleStoreObjectIndex('withdrawal/account_in', array('withdrawal'), '.transfers[*].account'));
$store->addObjectIndex($amount_in_idx = new SimpleStoreObjectIndex('withdrawal/amount_in', array('withdrawal'), '.transfers[*].account'));
//$store->addObjectIndex($expense_idx = new SimpleStoreObjectIndex('withdrawal/expense', array('withdrawal'), '.expenses[*].tags.expense'));
//$store->addObjectIndex($expense_amount_idx = new SimpleStoreObjectIndex('withdrawal/expense_amount', array('withdrawal'), '.expenses[*].amount'));

$store->addSumIndex(new SimpleStoreSumIndex('withdrawal/act_out_total', $account_out_idx, null, $amount_out_idx));
$store->addSumIndex(new SimpleStoreSumIndex('withdrawal/act_out_total/month', $month_idx, $account_out_idx, $amount_out_idx));
$store->addSumIndex(new SimpleStoreSumIndex('withdrawal/act_in_total', $account_in_idx, null, $amount_in_idx));
$store->addSumIndex(new SimpleStoreSumIndex('withdrawal/act_in_total/month', $month_idx, $account_in_idx, $amount_in_idx));
//$store->addSumIndex(new SimpleStoreSumIndex('withdrawal/expense_total/month', $month_idx, $expense_idx, $expense_amount_idx));

// Incomes
$store->addObjectIndex($month_idx = new SimpleStoreObjectIndex('income/month', array('income'), '.month'));
$store->addObjectIndex($account_idx = new SimpleStoreObjectIndex('income/account', array('income'), '.account'));
$store->addObjectIndex($amount_idx = new SimpleStoreObjectIndex('income/amount', array('income'), '.amount'));

$store->addSumIndex(new SimpleStoreSumIndex('income/act_total', $account_idx, null, $amount_idx));
$store->addSumIndex(new SimpleStoreSumIndex('income/act_total/month', $month_idx, $account_idx, $amount_idx));

// Allocations
$store->addObjectIndex(new SimpleStoreObjectIndex('allocation/month', array('allocation'), '.month'));
$store->addObjectIndex(new SimpleStoreObjectIndex('allocation/fund', array('allocation'), '.funds[*].fund'));

// SELECT s1.sum, s2.sum, s3.sum, s4.sum FROM `sums` s1 INNER JOIN (`sums` s2, `sums` s3, `sums` s4) ON (s1.value=s2.value AND s2.value=s3.value AND s3.value=s4.value) WHERE s1.`index`='cash_tfer/from_act_total_4c395e0' AND s2.`index`='cash_tfer/to_act_total_c0b85e06e' AND s3.`index`='expense/act_total_ff2a5e0689f4' AND s4.`index`='income/act_total_baa65e06b146';

?>
