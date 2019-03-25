<?php
require 'vendor/autoload.php';
use carbon\carbon;
use Phpml\Classification\KNearestNeighbors;

$class = new KNearestNeighbors();

function get($url){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

$error = [];
$curr = ['USD','GBP','JPY','EUR', 'AUD', 'CAD'];

function check(){
    return isset($_GET['base']) && isset($_GET['symbol']) && isset($_GET['predict']) && isset($_GET['days']);
}
if (check()) {

    $base = $_GET['base'];
    $symbol = $_GET['symbol'];
    $currency_predict = $_GET['predict'];
    $days = $_GET['days'];

    if (!is_numeric($currency_predict) || $currency_predict < 0 ) {
        array_push($error, 'Predict Rate should be greater than zero and should be numeric');
    }

    if (!is_numeric($days) || $days < 0 ) {
        array_push($error, 'Data days should be greater than zero and should be numeric');
    }

    if ($days % 2 == 0) {
        array_push($error, 'Data days must be odd number');
    }

    if(count($error) == 0){
        $result = get('https://api.openrates.io/latest?base='.$base.'&symbols='.$symbol);
        $result = json_decode($result,true);

        $from = Carbon::createFromFormat('Y-m-d',$result['date']);
        $start = Carbon::createFromFormat('Y-m-d',$result['date'])->subDays($days);


        $currency = [];

        for($i=1;$i<=$days;$i++){
            $start->addDays(1);
            $response = json_decode(get('https://api.openrates.io/'.$start->format('Y-m-d').'?base='.$base.'&symbols='.$symbol),true);
            array_push($currency, $response['rates'][$symbol]);
        }

        $bs=[];
        $currencys = [];

        for($i=0;$i<=$days-2;$i++){
            array_push($currencys, [$currency[$i],$currency[$i+1]]);
            $decide = $currency[$i] < $currency[$i+1] ? 'Buy' : 'Sell';
            array_push($bs, $decide);
        }
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
        <form action="" method="get" accept-charset="utf-8">

            <div>
                <div class="form-group">
                    <label>Base</label>
                    <select name="base" class="form-control" required>
                        <option value=''>Choose</option>
                        <?php
                        foreach ($curr as $cur) {
                            $check = $cur == "GBP" ? "selected" : "";
                            echo "<option ".$check." value=".$cur.">".$cur."</option>";
                        }
                        ?>
                    </select>
                </div>


                <div class="form-group">
                    <label>Symbol</label>
                    <select name="symbol" class="form-control" required>
                        <option value='' >Choose</option>
                        <?php
                        foreach ($curr as $cur) {
                            $check = $cur == "USD" ? "selected" : "";
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
            $class->train($currencys, $bs);
            echo 'Forex data feched from https://api.openrates.io/ for <strong>'.$base.'/'.$symbol.'</strong> pair from '.$from->format('Y-m-d').' to '.$start->format('Y-m-d').' ('.$days.' Days) <br>';
            echo 'Last close rate is '.$currencys[count($currencys)-1][1].'<br>';
            echo '<strong>'.$class->predict([$currencys[count($currencys)-1][1], $currency_predict]).' '.$base.'/'.$symbol.' @ '.$currency_predict.'</strong><br>';
        //echo json_encode($bs,true).'<br>';
        echo "</div>";
        }
        ?>
    </div>
</body>
</html>
