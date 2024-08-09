<?php

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

ini_set('display_errors', 1);
error_reporting(E_ERROR);

date_default_timezone_set('Europe/Amsterdam');

include (__DIR__.'/app/binance/Connector.php');
include (__DIR__.'/app/binance/Wrapper.php');
include (__DIR__.'/app/bybit/Connector.php');
include (__DIR__.'/app/bybit/Wrapper.php');
include (__DIR__.'/app/ftx/Connector.php');
include (__DIR__.'/app/ftx/Wrapper.php');
include (__DIR__.'/app/Config.php');
include (__DIR__.'/app/Core.php');
include (__DIR__.'/app/DataReader.php');
include (__DIR__.'/app/DataMapper.php');

$dataReader = new DataReader();
$dataMapper = new DataMapper();
$accounts = $dataReader->get_accounts();

foreach ($accounts as $account) {

    $start_time = $dataReader->get_end_time_pnl($account['account_id']);
    
    if(is_null($start_time['time'])) {
        $start_time['time'] = round( (microtime(true) - (60 * 60)) * 1000);
    }

    //echo '<h1>'.$account['account_name'].'</h1>';


    

    if ($account['exchange'] == 'bybit') {

        $bybit = new BybitConnector($account['api_key'] , $account['api_secret']);
        $bybit_wrapper = new BybitWrapper();

        $wallet_info = $bybit->wallet_info();
        $positions_info = $bybit->get_positions();

        $total_wallet = $wallet_info['result']['list'][0]['totalEquity']-$wallet_info['result']['list'][0]['totalPerpUPL'];
        $total_unrealized = $wallet_info['result']['list'][0]['totalPerpUPL'];

        //echo 'wallet_balance = '.$total_wallet.'<br />';
        //echo 'unrealized_pnl = '.$total_unrealized.'<br />';

        $invested = $bybit_wrapper->load_totals('invested', $open_positions);
        $total_maintainance_margin = $wallet_info['result']['list'][0]['totalPositionMM'];

        $exposure = number_format(  ($invested) / $total_wallet , 2);

        $transfer_in = 0;
        $transfer_out = 0;

        //echo 'transfer_in = '.$transfer_in.'<br />';
        //echo 'transfer_out = '.$transfer_out.'<br />'; 

    }

    

    $dataMapper->insert_pnl_record($account['account_id'] , $start_time['time'] , round( (microtime(true) ) * 1000)  , $total_wallet , $total_unrealized , $transfer_in , $transfer_out , $exposure);
}
