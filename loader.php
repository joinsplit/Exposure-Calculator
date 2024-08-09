<?php
/*

Use this script at your own risk. You are always responsible for your own trading due dillegence. Use this as an guide to manage your risk

(c) 2021 - MileCrypto (Lemmod)
*/

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

ini_set('display_errors', 1);
error_reporting(E_ERROR);

include (__DIR__.'/app/binance/Connector.php');
include (__DIR__.'/app/binance/Wrapper.php');
include (__DIR__.'/app/bybit/Connector.php');
include (__DIR__.'/app/bybit/Wrapper.php');
include (__DIR__.'/app/ftx/Connector.php');
include (__DIR__.'/app/ftx/Wrapper.php');
include (__DIR__.'/app/Config.php');
include (__DIR__.'/app/Core.php');
include (__DIR__.'/app/DataReader.php');
include (__DIR__.'/app/StyleFunctions.php');
include (__DIR__.'/app/Table.php');

function pr($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
}

// Init datareader
$dataReader = new DataReader();

// Get all registered accounts
$accounts = $dataReader->get_accounts();

// If no account is selected choose first one
$selected_account = $_GET['account'];
if(!isset($selected_account)) {
  $selected_account = $accounts[0]['account_id'];
}

$lookback_period = $_GET['lookback'];
if(!isset($lookback_period)) {
    $lookback_period = 3; // Days
}

// Get desired selected account settings
foreach ($accounts as $account) {
    if($account['account_id'] == $selected_account) {
        $selected_account_name = $account['account_name'];
        $exchange_name = $account['exchange'];
        $user_api_key = $account['api_key']; 
        $user_api_secret = $account['api_secret']; 
        $user_sub_account = $account['subaccount'];
    }
}


// Bybit information
if ($exchange_name == 'bybit') {

    $bybit = new BybitConnector($user_api_key , $user_api_secret);

    $wallet_info = $bybit->wallet_info();

	//print_r(json_encode($wallet_info));
	
    $positions_info = $bybit->get_positions();
	//print_r(json_encode($positions_info));
    $total_wallet = $wallet_info['result']['list'][0]['totalEquity'] - $wallet_info['result']['list'][0]['totalPerpUPL'];
	//print_r($total_wallet);
    $total_unrealized = $wallet_info['result']['list'][0]['totalPerpUPL'];
    //$total_margin_balance = $wallet_info['result']['USDT']['position_margin'];

    $total_margin_balance = $wallet_info['result']['list'][0]['totalInitialMargin'];
    $total_maintainance_margin = $wallet_info['result']['list'][0]['totalPositionMM'];

    $realized_pnl_daily = $wallet_info['result']['USDT']['realised_pnl'];
    $realized_pnl_total = $wallet_info['result']['USDT']['cum_realised_pnl'];

    $bybit_wrapper = new BybitWrapper();

    $all_positions = array();
    $nextPageCursor = null;

    do {
        $positions_info = $bybit->get_positions($nextPageCursor);

        // Check for API error
        if ($positions_info['retCode'] !== 0) {
            throw new Exception('API Error: ' . $positions_info['retMsg']);
        }

        // Merge current page positions with all positions
        $all_positions = array_merge($all_positions, $positions_info['result']['list']);

        // Check for the nextPageCursor for pagination
        $nextPageCursor = $positions_info['result']['nextPageCursor'] ?? null;

    } while ($nextPageCursor);

    $open_positions = $bybit_wrapper->load_open_positions($all_positions);
    //print_r($open_positions);
	$invested = $bybit_wrapper->load_totals('invested', $open_positions);
	$current_worth = $bybit_wrapper->load_totals('current_worth', $open_positions);

    $live_exposure = ($invested) / $total_wallet ;
    $margin_ratio =  "#N/A";
    //$max_drop = number_format ( ( ($total_margin_balance - $total_maintainance_margin) / ($current_worth)) * 100 , 2).'%';
}

/**
 * 
 * KPI Colors
 * 
 */
$kpi_pnl_color = $total_unrealized < 0 ? 'border-danger' : 'border-success';

$kpi_exposure_color = 'border-success';

if ($exposure > 2) $kpi_exposure_color = 'border-danger';
if ($exposure >= 1.5) $kpi_exposure_color = 'border-warning';

/**
 * 
 * Trades Table
 * 
 */
$trades_table =  new STable();
$trades_table->class = 'table table-dark table-sm';
$trades_table->id = 'trades';

$trades_table->thead()
->th('Symbol')
->th('Total asset')
->th('Side')
->th('Entry price')
->th('Current price')
->th('Liq. price')
->th('Invested')
->th('Current worth')
->th('Profit')
->th('PnL');

foreach($open_positions as $open_position) {

    $pnl_color = $open_position['pnl'] < 0 ? 'red' : 'green';
    $liq_price = $open_position['liqPrice'] > 0 ? number_format($open_position['liqPrice'] , 6 , '.' , '') : '-';
    $liq_color =  $liq_price > 0 ? 'orange' : '';

    $trades_table->tr()
    ->td($open_position['symbol'])
    ->td($open_position['totalAsset'])
    ->td($open_position['side']);
    if ($exchange_name == 'ftx' && $open_position['entryPrice'] != $open_position['avgEntryPrice']) {
        $trades_table->td(number_format($open_position['entryPrice'] , 6 , '.' , '').' / ('.number_format($open_position['avgEntryPrice'] , 6 , '.' , '').')');
    } else {
        $trades_table->td(number_format($open_position['entryPrice'] , 6 , '.' , ''));
    }
    $trades_table->td(number_format($open_position['currentPrice'] , 6 , '.' , ''))
    ->td($liq_price , $liq_color)
    ->td(number_format($open_position['investedWorth'] , 6))
    ->td(number_format($open_position['currentWorth'] , 6))
    ->td(number_format($open_position['profitPercentage_min'] , 2).'%' , $pnl_color)
    ->td(number_format( $open_position['pnl'] , 2) , $pnl_color);
}

/**
 * 
 * Daily PnL Table
 * 
 */

$pnl_records = $dataReader->get_pnl_records($selected_account);

$result = [];
$x = 0;
foreach($pnl_records as $key => $pnl_record) {

    $day = date('Y-m-d 00:00:00', $pnl_record['start_time'] / 1000);
    $days[] = $day;
    $result[$day][$x] = array_merge(['day' => $day] , $pnl_record);

    $x++;
}


$daily_table =  new STable();
$daily_table->class = 'table table-dark table-sm';
$daily_table->id = 'realized_pnl_daily';

$daily_table->thead()
->th('Start')
->th('End')
->th('Balance start')
->th('Balance end')
->th('Unrealized PnL')
->th('Transfer in')
->th('Transfer out')
->th('Profit')
->th('Profit %');

foreach ($result as $key => $res) {

    $normal = $result[$key];
    $reverse = array_reverse($result[$key]);

    $transfer_in = 0;
    $transfer_out = 0;

    foreach($normal as $i => $norm) {
    	if (!isset($exposure) || $exposure == 0) {
            $exposure = $norm['exposure'];
        }
        $transfer_in += $norm['transfer_in'];
        $transfer_out += $norm['transfer_out'];
        $wallet_balance_end = $norm['wallet_balance'];
        $unrealized_pnl = $norm['unrealized_pnl'];
        $end_time = date('Y-m-d H:i:s', $norm['end_time'] / 1000);
        if ($unrealized_pnl == 0) {
            $unrealized_pnl = $normal[$i-1]['unrealized_pnl'];
        }
        if ($wallet_balance_end == 0) {
            $wallet_balance_end = $normal[$i-1]['wallet_balance'];
        }
    }

    foreach($reverse as $rev) {
        $wallet_balance_start = $rev['wallet_balance'];
        $unrealized_pnl_start = $rev['unrealized_pnl'];
        $start_time = date('Y-m-d H:i:s', $rev['start_time'] / 1000);
    }

    if($exchange_name == 'ftx') {
        $wallet_balance_end = ($wallet_balance_end + ($unrealized_pnl * -1));
        $wallet_balance_start = ($wallet_balance_start + ($unrealized_pnl_start * -1));
    }

    $profit = $wallet_balance_end - $wallet_balance_start;
    $profit+= $transfer_out;
    $profit-= $transfer_in;

    $daily_table->tr()
    ->td($start_time)
    ->td($end_time)
    ->td(number_format( $wallet_balance_start , 2))
    ->td(number_format( $wallet_balance_end , 2))
    ->td(number_format( $unrealized_pnl , 2))
    ->td(number_format( $transfer_in , 2))
    ->td(number_format( $transfer_out , 2))
    ->td(number_format( $profit , 2))
    ->td( number_format( (($profit / $wallet_balance_start) * 100 ) , 2).'%');
}

$pnl_records = $dataReader->get_pnl_records($selected_account);

$result_monthly = [];
$x = 0;
foreach($pnl_records as $key => $pnl_record) {

    // Group by month and year
    $month = date('Y-m-01 00:00:00', $pnl_record['start_time'] / 1000);
    $result_monthly[$month][$x] = array_merge(['month' => $month] , $pnl_record);

    $x++;
}

$monthly_table = new STable();
$monthly_table->class = 'table table-dark table-sm';
$monthly_table->id = 'realized_pnl_monthly';

$monthly_table->thead()
->th('Start')
->th('End')
->th('Balance start')
->th('Balance end')
->th('Unrealized PnL')
->th('Transfer in')
->th('Transfer out')
->th('Profit')
->th('Profit %');

foreach ($result_monthly as $key => $res) {

    $normal = $result_monthly[$key];
    $reverse = array_reverse($result_monthly[$key]);

    $transfer_in = 0;
    $transfer_out = 0;

    foreach($normal as $i => $norm) {
        $transfer_in += $norm['transfer_in'];
        $transfer_out += $norm['transfer_out'];
        $wallet_balance_end = $norm['wallet_balance'];
        $unrealized_pnl = $norm['unrealized_pnl'];
        $end_time = date('Y-m-d H:i:s', $norm['end_time'] / 1000);
        if ($unrealized_pnl == 0) {
            $unrealized_pnl = $normal[$i-1]['unrealized_pnl'];
        }
        if ($wallet_balance_end == 0) {
            $wallet_balance_end = $normal[$i-1]['wallet_balance'];
        }
    }

    foreach($reverse as $rev) {
        $wallet_balance_start = $rev['wallet_balance'];
        $unrealized_pnl_start = $rev['unrealized_pnl'];
        $start_time = date('Y-m-d H:i:s', $rev['start_time'] / 1000);
    }

    if($exchange_name == 'ftx') {
        $wallet_balance_end = ($wallet_balance_end + ($unrealized_pnl * -1));
        $wallet_balance_start = ($wallet_balance_start + ($unrealized_pnl_start * -1));
    }

    $profit = $wallet_balance_end - $wallet_balance_start;
    $profit += $transfer_out;
    $profit -= $transfer_in;

    $monthly_table->tr()
    ->td($start_time)
    ->td($end_time)
    ->td(number_format( $wallet_balance_start , 2))
    ->td(number_format( $wallet_balance_end , 2))
    ->td(number_format( $unrealized_pnl , 2))
    ->td(number_format( $transfer_in , 2))
    ->td(number_format( $transfer_out , 2))
    ->td(number_format( $profit , 2))
    ->td(number_format( (($profit / $wallet_balance_start) * 100 ) , 2).'%');
}

/**
 * 
 * Unrealized PnL Chart
 * 
 */

$max = -10000000;
$min = 10000000;
foreach($pnl_records as $key => $pnl_record) {

    $day = date('Y-m-d H:M:00', $pnl_record['start_time'] / 1000);
    $days[] = $day;
    $result_1[$day][$x] = array_merge(['day' => $day] , $pnl_record);

    $x++;
}

$result_1 = array_slice($result_1 , (-$lookback_period*24)-1);

foreach ($result_1 as $key => $res) { 

    $normal = $result_1[$key];

    foreach($normal as $i => $norm) {
        $unrealized_pnl = $norm['unrealized_pnl'];
        // Sometimes the wallet balance isn't filled so we use the previous version
        if ($exposure == 0) {
            $exposure = $normal[$i-1]['exposure'];
        }
        // Sometimes the unrealized PnL isn't filled so we use the previous version
        if ($unrealized_pnl == 0) {
            $unrealized_pnl = $normal[$i-1]['unrealized_pnl'];
        }
        $end_time = date('Y-m-d H:i:s', $norm['end_time'] / 1000);  
        if ($unrealized_pnl < $min) {
            $min = $unrealized_pnl;
        }      
        if ($unrealized_pnl > $max) {
            $max = $unrealized_pnl;
        }
    }

    $end_time = date('m-d @ H:i ', $norm['end_time'] / 1000);
    
    $x_axis_unrealized[] = $end_time;
    $y_axis_unrealized[] = number_format( $unrealized_pnl , 2  , "." , "");
}




 /**
 * 
 * Exposure chart
 * 
 */
foreach ($result_1 as $key => $res) { 

    $normal = $result_1[$key];

    foreach($normal as $i => $norm) {
        $exposure = $norm['exposure'];
        // Sometimes the wallet balance isn't filled so we use the previous version
        if ($exposure == 0) {
            $exposure = $normal[$i-1]['exposure'];
        }
        $end_time = date('Y-m-d H:i:s', $norm['end_time'] / 1000);  
        if ($exposure < $min) {
            $min = $exposure;
        }      
        if ($exposure > $max) {
            $max = $exposure;
        }
    }

    $end_time = date('m-d @ H:i ', $norm['end_time'] / 1000);

    $x_axis_exposure[] = $end_time;
    $y_axis_exposure[] = number_format( $exposure , 2  , "." , "");
}

/**
 * 
 * Wallet balance chart
 * 
 */
foreach ($result_1 as $key => $res) { 

    $normal = $result_1[$key];

    $i = 0;
    foreach($normal as $i => $norm) {
        $wallet_balance_end = $norm['wallet_balance'];
        // Sometimes the wallet balance isn't filled so we use the previous version
        if ($wallet_balance_end == 0) {
            $wallet_balance_end = $normal[$i-1]['wallet_balance'];
        }
        $unrealized_pnl = $norm['unrealized_pnl'];
        $end_time = date('Y-m-d H:i:s', $norm['end_time'] / 1000);  
        if ($wallet_balance_end < $min) {
            $min = $wallet_balance_end;
        }      
        if ($wallet_balance_end > $max) {
            $max = $wallet_balance_end;
        }
        $i++;
    }

    $x_axis_wallet[] = $end_time;
   

}
?>

