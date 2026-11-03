<?php
require "Manomilan_Backend/vendor/autoload.php";
$app = require_once "Manomilan_Backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Profile::with(["user","preference"])->find(10);
if($p){
    $missing = $p->missingMandatoryFields();
    var_dump($missing);
}

