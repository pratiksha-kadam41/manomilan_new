<?php
require "Manomilan_Backend/vendor/autoload.php";
$app = require_once "Manomilan_Backend/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = App\Profile::with(["user","preference","familyDetails","educations","employments","religionDetails","profileDocuments","partnerPreferences"])->find(10);
$religion = $p->religionDetails;
$family = $p->familyDetails;
$education = $p->educations?->sortByDesc("id")->first();
$employment = $p->employments?->sortByDesc("id")->first();
$docs = $p->profileDocuments;
$photos = is_array($p->photos)?$p->photos:json_decode($p->photos,true);
$addresses = is_array($p->addresses)?$p->addresses:json_decode($p->addresses,true);
var_dump(!empty($p->full_name), !empty($p->groomorbride), !empty($religion->religion), !empty($religion->caste));
var_dump($photos["profile_photo"], $docs->aadhaar_card);

