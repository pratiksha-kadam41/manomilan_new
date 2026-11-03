<?php
require "Manomilan_Backend/vendor/autoload.php";
$app = require_once "Manomilan_Backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(1);
auth()->setUser($user);
$c = new App\Http\Controllers\Api\ProfileController();
$req = Illuminate\Http\Request::create("/api/profiles/allProfiles?per_page=10", "GET");
$req->setUserResolver(function() use ($user){ return $user; });
$res = $c->allProfiles($req);
echo $res->getContent();

