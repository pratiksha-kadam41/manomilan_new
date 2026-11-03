<?php
require "Manomilan_Backend/vendor/autoload.php";
$app = require_once "Manomilan_Backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$c = new App\Http\Controllers\Api\ProfileController();
$req = Illuminate\Http\Request::create("/api/profiles/allProfiles?per_page=2", "GET");
$res = $c->allProfiles($req);
echo $res->getContent();

