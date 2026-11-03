<?php
require "Manomilan_Backend/vendor/autoload.php";
$app = require_once "Manomilan_Backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Profile::with(["user","preference","familyDetails","educations","employments","religionDetails","profileDocuments","partnerPreferences"])->find(10);
if($p){
    $p->calculateCompletionPercentage();
    $p->refresh();
    var_dump($p->profile_completion);
}

