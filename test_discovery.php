<?php
require 'src/bootstrap.php';
$svc = new SLC\Services\AI\LeadDiscoveryService();
$res = $svc->discover(['industry' => 'Software', 'count' => 1]);
print_r($res);
