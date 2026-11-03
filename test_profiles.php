<?php
require __DIR__.'/Manomilan_Backend/vendor/autoload.php';
\ = require_once __DIR__.'/Manomilan_Backend/bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;

\ = new ProfileController();
\ = Request::create('/api/profiles/allProfiles?per_page=2', 'GET');
\ = \->allProfiles(\);
echo \->getContent();

