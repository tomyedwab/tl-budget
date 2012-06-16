<?php

include('tl_budget.php');

$data_upload = file_get_contents("php://input");
$xml = simplexml_load_string($data_upload);

$database = $xml->database;

function get_table_data($database, $name) {
    foreach ($database->table_data as $table_data) {
        if ($name == $table_data["name"]) {
            return $table_data->children();
        }
    }
    return Null;
}
function get_field($row, $field_name) {
    foreach ($row->field as $field) {
        if ($field["name"] == $field_name) {
            return $field;
        }
    }
    return Null;
}
function get_by_field($database, $table_name, $field, $id) {
    $ret = Array();
    $rows = get_table_data($database, $table_name);
    foreach ($rows as $row) {
        if (get_field($row, $field) == $id) {
            $ret[] = $row;
        }
    }
    return $ret;
}

function make_key($domain, $prefix, $id) {
    return sprintf("%s:%s%05d", $domain, $prefix, $id);
}

$store->pushTriggerState();

$store->setDomainOverride("admin");
$store->clearObjects("user");

$store->startChangeset();

$new_user = Array();
$new_user["login"] = "tom";
$new_user["realname"] = "Tom Worthington Yedwab";
$new_user["password"] = "31c63d9174b17e995d372858eddd4bf8";
$new_user["domain"] = "t&l";
$new_user_json = json_encode($new_user);
if (!$store->storeObject("user", make_key("admin", "U", 1), $new_user_json, "admin")) {
    echo "Failed to store user.\n";
    exit(0);
}

$new_user = Array();
$new_user["login"] = "leslie";
$new_user["realname"] = "Leslie Worthington Yedwab";
$new_user["password"] = "31c63d9174b17e995d372858eddd4bf8";
$new_user["domain"] = "t&l";
$new_user_json = json_encode($new_user);
if (!$store->storeObject("user", make_key("admin", "U", 2), $new_user_json, "admin")) {
    echo "Failed to store user.\n";
    exit(0);
}

$new_user = Array();
$new_user["login"] = "test";
$new_user["realname"] = "Test Testerson";
$new_user["password"] = "cc03e747a6afbbcbf8be7668acfebee5";
$new_user["domain"] = "test";
$new_user_json = json_encode($new_user);
if (!$store->storeObject("user", make_key("admin", "U", 3), $new_user_json, "admin")) {
    echo "Failed to store user.\n";
    exit(0);
}

$store->endChangeset();

$store->setDomainOverride("t&l");
$store->clearObjects("account");
$store->clearObjects("transaction");

$store->clearObjects("cash_account");
$store->clearObjects("cash_transfer");
$store->clearObjects("expense");
$store->clearObjects("income");
$store->clearObjects("withdrawal");

$store->clearObjects("allocation");

$store->startChangeset();

$accounts = get_table_data($database, "accounts");
foreach ($accounts as $account) {

    $type = (string)get_field($account, "type");

    if ($type == "cash" || $type == "loan") {

        $new_account = Array();
        $new_account["name"] = (string)get_field($account, "name");
        $new_account["category"] = (string)get_field($account, "category");

        $new_key = make_key("t&l", "CASH_", (int)get_field($account, "id"));
        $new_account_json = json_encode($new_account);
        // echo "$new_key: $new_account_json\n";

        if (!$store->storeObject("cash_account", $new_key, $new_account_json)) {
            echo "Failed to store account.\n";
            exit(0);
        }

    }

//    $new_account["type"] = (string)get_field($account, "type");
//    $new_account["closed"] = (boolean)get_field($account, "comments");

//    $fund = get_by_field($database, "funds", "id", (string)get_field($account, "fund_id"));
//    $new_account["fund"] = (string)get_field($fund[0], "name");

}

$fund_allocations = Array();
$fund_allocations[0] = Array();

$transactions = get_table_data($database, "transactions");
foreach ($transactions as $transaction) {
    $trans_id = (string)get_field($transaction, "id");
    
    $new_transaction = Array();

    $date = strtotime((string)get_field($transaction, "date"));
    $new_transaction["date"] = date('Ymd', $date);
    $new_transaction["month"] = date('Ym', $date);

    $week_start_date = $date - ((date('N', $date) - 1) * 60*60*24);
    $new_transaction["week"] = date('Ymd', $week_start_date);

    $new_transaction["description"] = (string)get_field($transaction, "description");
    if ((string)get_field($transaction, "comments")) {
        $new_transaction["comments"] = (string)get_field($transaction, "comments");
    }
    $new_transaction["edit_by"] = (string)get_field($transaction, "edit_by");
    $new_transaction["edit_date"] = (string)get_field($transaction, "edit_date");
    
    $month = $new_transaction["month"];
    if (!isset($fund_allocations[$month])) {
        $fund_allocations[$month] = Array();
    }

    $can_be_expense = true;
    $can_be_income = true;
    $can_be_transfer = true;
    $can_be_fund_transfer = true;

    $cash_accounts = Array();
    $expense_entries = Array();

    $entries = get_by_field($database, "entries", "transaction", $trans_id);
    foreach ($entries as $entry) {
        $fund = null;
        $amount = (float)get_field($entry, "amount");
        //if ((string)get_field($entry, "rec_date") && substr((string)get_field($entry, "rec_date"), 0, 10) != "0000-00-00") {
        //    $new_entry["rec_date"] = substr((string)get_field($entry, "rec_date"), 0, 10);
        //}
        
        $funds = get_by_field($database, "funds", "id", (string)get_field($entry, "fund_id"));
        if (count($funds) > 0 && (string)get_field($funds[0], "name")) {
            $fund = (string)get_field($funds[0], "name");
            if ($fund == "Retirement Savings Fund") {
                $fund = "Retirement";
            } else if ($fund == "Charity Fund") {
                $fund = "Charity";
            } else if ($fund == "Living Expenses Fund" || $fund == "Rent Fund" || $fund == "Annual Expenses Fund" ||
                       $fund == "Tom Discretionary Fund" || $fund == "Leslie Discretionary Fund") {
                $fund = "Living Expenses";
            } else if ($fund == "House Equity Fund") {
                $fund = "House Equity";
            } else {
                $fund = "Savings";
            }
        }

        $account_id = (string)get_field($entry, "account_id");
        $accounts = get_by_field($database, "accounts", "id", $account_id);
        if (count($accounts) > 0) {
            if ((string)get_field($accounts[0], "name") == "ALLOCATE") {
                if ($new_transaction["description"] == "INITIAL BALANCES" || $new_transaction["description"] == "INITIAL BALANCE") {
                    if (!isset($fund_allocations[0][$fund])) {
                        $fund_allocations[0][$fund] = Array();
                        $fund_allocations[0][$fund]['initial_balance'] = 0;
                    }
                    $fund_allocations[0][$fund]['initial_balance'] += $amount;
                } else {
                    if (!isset($fund_allocations[$month][$fund])) {
                        $fund_allocations[$month][$fund] = Array();
                        $fund_allocations[$month][$fund]['allocs'] = 0;
                        $fund_allocations[$month][$fund]['expenses'] = 0;
                    }
                    $fund_allocations[$month][$fund]['allocs'] += $amount;
                }
            } else {
                /*if ((int)get_field($accounts[0], "is_private") == 1) {
                    if ((int)get_field($accounts[0], "private_to") == 1) {
                        $new_transaction["private_to"] = "tom";
                    } else {
                        $new_transaction["private_to"] = "leslie";
                    }
                }*/

                $account_type = (string)get_field($accounts[0], "type");

                if ($account_type == "cash") {
                    $cash_accounts[] = Array($account_id, $amount);
                }
                if ($account_type == "loan") {
                    $cash_accounts[] = Array($account_id, -1*$amount);
                }
                if ($account_type == "expense" && $amount != 0) {
                    if ($new_transaction["description"] == "CREDIT CARD BALANCES") {
                        if (!isset($fund_allocations[0][$fund])) {
                            $fund_allocations[0][$fund] = Array();
                            $fund_allocations[0][$fund]['initial_balance'] = 0;
                        }
                        $fund_allocations[0][$fund]['initial_balance'] -= $amount;
                    } else {
                        if (!isset($fund_allocations[$month][$fund])) {
                            $fund_allocations[$month][$fund] = Array();
                            $fund_allocations[$month][$fund]['allocs'] = 0;
                            $fund_allocations[$month][$fund]['expenses'] = 0;
                        }
                        $fund_allocations[$month][$fund]['expenses'] += $amount;
                    }

                    $new_expense_entry = Array();
                    $new_expense_entry["amount"] = $amount;

                    $tags = Array();
                    $tags["expense"] = (string)get_field($accounts[0], "name");
                    if ($fund != "Living Expenses") {
                        $tags["fund"] = $fund;
                    }
                    $new_expense_entry["tags"] = $tags;

                    $expense_entries[] = $new_expense_entry;
                }

                // Infer possible type

                if ($account_type != "loan" && $account_type != "cash" && $account_type != "expense") {
                    $can_be_expense = false;
                }
                if ($account_type != "cash" && $account_type != "loan") {
                    $can_be_transfer = false;
                }
                if ($account_type != "income" && $account_type != "cash" && $account_type != "loan") {
                    $can_be_income = false;
                }
                $can_be_fund_transfer = false;
            }
        } else {
            echo "Unknown account: $account_id\n";
            exit(0);
        }
    }

    if ($can_be_fund_transfer) {
        //echo "Fund transfer: " . $new_transaction["description"] . " (" . $new_transaction["date"] . ")\n";
        //$new_transaction["type"] = "fund_transfer";
    } else if ($can_be_transfer || $can_be_expense) {
        //echo "Transfer: " . $new_transaction["description"] . " (" . $new_transaction["date"] . ")\n";
        $in_accounts = Array();
        $out_accounts = Array();

        if (count($cash_accounts) == 1) {
            $out_accounts[] = $cash_accounts[0];
        } else {
            foreach ($cash_accounts as $account) {
                if ($account[1] < 0) {
                    $in_accounts[] = $account;
                } else {
                    $out_accounts[] = $account;
                }
            }
        }

        if (count($out_accounts) != 1 && count($expense_entries) != 1) {
            error_log("BAD WITHDRAWAL: " . $new_transaction["description"] . " (" . $new_transaction["date"] . ")");
            foreach ($in_accounts as $account) {
                error_log("IN ACCOUNT: " . $account[0]);
            }
            foreach ($out_accounts as $account) {
                error_log("OUT ACCOUNT: " . $account[0]);
            }
            foreach ($expense_entries as $expense_entry) {
                error_log("EXPENSE: " . print_r($expense_entry["tags"], true));
            }
        } else {
            $idx = 0;

            if ((string)get_field($transaction, "location")) {
                $new_transaction["location"] = (string)get_field($transaction, "location");
            }

            if (count($expense_entries) > 0) {
                $new_transaction["expenses"] = $expense_entries;
            }

            if (count($in_accounts) > 0) {
                $new_transaction["transfers"] = Array();
                foreach ($in_accounts as $account) {
                    $new_transfer = Array();
                    $new_transfer["account"] = make_key("t&l", "CASH_", $account[0]);
                    $new_transfer["amount"] = $account[1] * -1;
                    $new_transaction["transfers"][] = $new_transfer;
                }
            }

            $idx = 0;

            foreach ($out_accounts as $out_account) {
                $account_key = make_key("t&l", "CASH_", $out_account[0]);
                if ($new_transaction["description"] == "CREDIT CARD BALANCES") {
                    $account_object = $store->fetchObject($account_key);
                    if ($account_object == '') {
                        echo "Failed to set initial balance on invalid account $account_key";
                        exit(0);
                    } else {
                        $account_object = json_decode($account_object);
                        $account_object->initial_balance = $account[1]*-1;
                        $store->updateObject("cash_account", $account_key, json_encode($account_object));
                    }
                } else {
                    $new_transaction["account"] = $account_key;
                    $new_transaction["amount"] = $out_account[1];

                    $new_key = make_key("t&l", "WDL_", ((int)get_field($transaction, "id"))*10+$idx);
                    $idx++;
                    $new_transaction_json = json_encode($new_transaction);
                    //echo "$new_key: $new_transaction_json\n";

                    if (!$store->storeObject("withdrawal", $new_key, $new_transaction_json)) {
                        echo "Failed to store transaction.\n";
                        exit(0);
                    }
                }
            }
        }
    } else if ($can_be_income) {
        foreach ($cash_accounts as $account) {
            if ($new_transaction["description"] == "INITIAL BALANCES" || $new_transaction["description"] == "INITIAL BALANCE") {
                $account_key = make_key("t&l", "CASH_", $account[0]);
                $account_object = $store->fetchObject($account_key);
                if ($account_object == '') {
                    echo "Failed to set initial balance on invalid account $account_key";
                    exit(0);
                } else {
                    $account_object = json_decode($account_object);
                    $account_object->initial_balance = $account[1]*-1;
                    $store->updateObject("cash_account", $account_key, json_encode($account_object));
                }
            } else {
                $new_transaction["account"] = make_key("t&l", "CASH_", $account[0]);
                $new_transaction["amount"] = $account[1]*-1;

                $new_key = make_key("t&l", "INC_", ((int)get_field($transaction, "id"))*10+$idx);
                $idx++;
                $new_transaction_json = json_encode($new_transaction);
                // echo "$new_key: $new_transaction_json\n";

                if (!$store->storeObject("income", $new_key, $new_transaction_json)) {
                    echo "Failed to store transaction.\n";
                    exit(0);
                }
            }
        }
        //$new_transaction["type"] = "income";
        // echo "Income: " . $new_transaction["description"] . " (" . $new_transaction["date"] . ")\n";
    } else {
        //$new_transaction["type"] = "custom";
        echo "Transaction not handled: " . $new_transaction["description"] . " (" . $new_transaction["date"] . ")\n";
    }
}

$idx = 0;

foreach ($fund_allocations as $month => $funds) {
    $allocation_entry = Array();
    $allocation_entry["month"] = $month;
    $allocation_entry["funds"] = Array();

    foreach ($funds as $fund => $amounts) {
        if ($amounts['allocs'] != 0 || $amounts['expenses']) {
            $fund_entry = Array();
            $fund_entry["fund"] = $fund;
            $fund_entry["alloc"] = $amounts['allocs'];
            $fund_entry["expenses"] = $amounts['expenses'];
            $allocation_entry["funds"][] = $fund_entry;
        } else if ($amounts['initial_balance']) {
            $fund_entry = Array();
            $fund_entry["fund"] = $fund;
            $fund_entry["initial_balance"] = $amounts['initial_balance'];
            $allocation_entry["funds"][] = $fund_entry;
        }
    }

    $new_key = make_key("t&l", "ALLOC_", $idx);
    $idx++;
    $new_allocation_json = json_encode($allocation_entry);
    // echo "$new_key: $new_transaction_json\n";

    if (!$store->storeObject("allocation", $new_key, $new_allocation_json)) {
        echo "Failed to store allocation.\n";
        exit(0);
    }
}

$store->endChangeset();

$store->popTriggerState();

echo "Migration succeeded!";

?>

