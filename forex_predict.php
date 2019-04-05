<?php
ini_set('max_execution_time', 120);
require 'vendor/autoload.php';
use carbon\carbon;
use Phpml\Classification\KNearestNeighbors;
use Phpml\Classification\SVC;
use Phpml\Classification\NaiveBayes;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Metric\Accuracy;

$knn = new KNearestNeighbors();
$nb = new NaiveBayes();

function get($url){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function store($file, $content){
    file_put_contents($file, json_encode($content));
}

function fetch($file){
    if (file_exists($file)) {
        return file_get_contents($file);
    }
    return false;
}

$error = [];
$curr = ['USD','GBP','JPY','EUR', 'AUD', 'CAD', 'CHF'];

function check(){
    return isset($_GET['base']) && isset($_GET['symbol']) && isset($_GET['predict']) && isset($_GET['days']);
}
if (check()) {

    $base = $_GET['base'];
    $symbol = $_GET['symbol'];
    $currency_predict = $_GET['predict'];
    $days = $_GET['days'];
    $filename = "data.json";

    if ($base == $symbol) {
        array_push($error, 'Base and symbol cant be the same');
    }

    if (!is_numeric($currency_predict) || $currency_predict < 0 ) {
        array_push($error, 'Predict Rate should be greater than zero and should be numeric');
    }

    if (!is_numeric($days) || $days < 0 ) {
        array_push($error, 'Data days should be greater than zero and should be numeric');
    }

    if ($days % 2 == 0) {
        array_push($error, 'Data days must be odd number');
    }

    if ($days < 3) {
        array_push($error, 'Data days must not be lesser than 3');
    }


     /*if ($days > 200) {
        array_push($error, 'Data days must not be greater than 200');
    }*/

    if(count($error) == 0){
        $data = json_decode(fetch($filename),true);
        $data_source = "Cached Data saved in data.json";
        if (!$data || (isset($data['date']) && $data['date'] < Carbon::now()->subDays(1)) || !isset($data['history'])) {
        $api = fetch("api.txt");
        $domain = 'https://www.worldtradingdata.com/api/v1/forex_history';
        $url = $domain.'?api_token='.$api.'&base='.$base.'&convert_to='.$symbol;
        $result = get($url);
        $result = json_decode($result,true);
        $result['date'] = Carbon::now()->format('Y-m-d');
        store($filename, $result);

        $data = json_decode(fetch($filename),true);
        $data_source = $domain;
         }

        if($days > count($data['history'])){
            array_push($error, 'Data days must not be greater than '.count($data['history']));
        }

        if (isset($data['Message'])) {
            array_push($error, $data['Message']);
        }

        if(count($error) == 0){
        $history = $data['history'];
        $from = Carbon::now()->startOfDay();
        $start = Carbon::now()->startOfDay();

        $currency = [];
        
        for ($i=1; $i < $days ; $i++) {
            $start->subDays(1);
            if (!isset($history[$start->format('Y-m-d')])) {
                $i--;
                continue;
            }
            
            array_push($currency, $history[$start->format('Y-m-d')]);

        }

        $bs=[];
        $currencys = [];

        for($i=$days-2;$i> 0;$i--){
            array_push($currencys, [$currency[$i],$currency[$i-1]]);
            $decide = $currency[$i] < $currency[$i-1] ? 'Buy' : 'Sell';
            array_push($bs, $decide);
        }
    }
        //exit();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Forex Predict</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style type="text/css" media="screen">
        .response {

            margin-top: 30px;

            border-radius: 5px;

            color: white;

            padding: 10px;

            margin-bottom: 30px;

        }
    </style>
</head>
<body>
    <div class="container mt-5 col-10 mx-auto">
        <h3>Forex Prediction</h3>
        <p>Register @ <a href="https://www.worldtradingdata.com/register"> World Trading Data</a> to get your request api (Free acccount has 250 request per day limit)</p>
        <p>Create a api.txt file exactly where forex_predict.php is and paste your api key on the first line.</p>
        <form action="" method="get" accept-charset="utf-8">

            <div>
                <div class="form-group">
                    <label>Base</label>
                    <select name="base" class="form-control" required>
                        <option value='' <?php !isset($base)? "selected" : ""?> >Choose</option>
                        <?php
                        foreach ($curr as $cur) {
                            $check = isset($base) && $base == $cur ? "selected"  : "";
                            echo "<option ".$check." value=".$cur.">".$cur."</option>";
                        }
                        ?>
                    </select>
                </div>


                <div class="form-group">
                    <label>Symbol</label>
                    <select name="symbol" class="form-control" required>
                        <option value='' <?php !isset($base)? "selected" : ""?> >Choose</option>
                        <?php
                        foreach ($curr as $cur) {
                            $check = isset($symbol) && $symbol == $cur ? "selected" : "";
                            echo "<option ".$check." value=".$cur.">".$cur."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Predict Rate</label>
                    <input class="form-control" name="predict" value="<?php echo isset($currency_predict) ? $currency_predict : ''?>" required>
                </div>

                <div class="form-group">
                    <label>Data Days</label>
                    <input class="form-control"  name="days" value="<?php echo isset($days) ? $days : ''?>" required>
                </div>

                <button class="btn btn-success" type="submit">Predict</button>

            </div>

        </form>
        <?php
        if (count($error) > 0) {
            echo '<div class="response bg-danger"><ul>';

            foreach ($error as $err) {
                echo "<li>".$err."</li>";
            }
            echo '</ul></div>';
        }

        if (check() && count($error) == 0) {
            echo '<div class="response bg-success">';
            $knn->train($currencys, $bs);
            $nb->train($currencys, $bs);
            echo 'Forex data feched from '.$data_source.' for <strong>'.$base.'/'.$symbol.'</strong> pair from '.$start->format('Y-m-d').' to '.$from->format('Y-m-d').' ('.$days.' Days) <br>';
            echo 'Last close rate is '.$currencys[count($currencys)-1][1].'<br>';
            echo '<strong>'.$knn->predict([$currencys[count($currencys)-1][1], $currency_predict]).' '.$data['symbol'].' @ '.$currency_predict.' for KNearestNeighbors. </strong><br>';

            echo '<strong>'.$nb->predict([$currencys[count($currencys)-1][1], $currency_predict]).' '.$data['symbol'].' @ '.$currency_predict.' for NaiveBayes. </strong><br>';
            echo "</div>";
        }
        ?>
    </div>
</body>
</html>
