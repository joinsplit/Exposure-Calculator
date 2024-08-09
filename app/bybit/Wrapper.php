<?php

class BybitWrapper
{

     /**
     * Create an overview of total open positions
     */
public function load_open_positions($open_positions) {

    $result = array();
    $i = 0;

    foreach ($open_positions as $single_position) {
        
            // Check if 'positionValue' is greater than 0
            if ($single_position['positionValue'] > 0) {

                //print_r($single_position);

                // Determine the factor based on the 'side' of the position
                if ($single_position['side'] == 'Sell') {
                    $factor = -1;
                } else {
                    $factor = 1;
                }
				$result[$i] = array( 
                    'symbol' => $single_position['symbol'], 
                    'totalAsset' => $single_position['size'],
                    'entryPrice' => $single_position['avgPrice'], 
                    'currentPrice' => $single_position['markPrice'],
                    'profitPercentage' => (($single_position['markPrice'] * $single_position['size']) / ($single_position['avgPrice'] * $single_position['size']) - 1) * 100 * $factor,
                    'profitPercentage_min' => (($single_position['markPrice'] * $single_position['size']) / ($single_position['avgPrice'] * $single_position['size']) - 1) * 100 * $factor,
                    'investedWorth' => ($single_position['avgPrice'] * $single_position['size']),
                    'currentWorth' => ($single_position['markPrice'] * $single_position['size']),
                    'pnl' => (($single_position['markPrice'] * $single_position['size']) - ($single_position['avgPrice'] * $single_position['size'])) * $factor,
                    'side' => $single_position['side'],
                    'liqPrice' => $single_position['liqPrice']
                );
                $i++;
            }
		}
        
		
	array_multisort(array_column($result, 'profitPercentage'), SORT_DESC, $result);
    return $result;

    }


     /**
     * Get totals from the open positions
     */
    public function load_totals($type , $open_positions) {

        if($type == 'invested') {
            $invested = 0;

            foreach($open_positions as $position) {
                $invested += $position['investedWorth'];
            }

            return $invested;
        }

        if($type == 'current_worth') {
            $current_worth = 0;

            foreach($open_positions as $position) {
                $current_worth += $position['currentWorth'];
            }

            return $current_worth;
        }
    }
}

