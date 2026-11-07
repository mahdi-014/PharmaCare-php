<?php
declare(strict_types=1);
// Run: php tests/smoke.php (does not require MySQL)
require dirname(__DIR__) . '/includes/functions.php';
$passed = 0;
function check(bool $ok, string $message): void {
    global $passed;
    if (!$ok) { fwrite(STDERR,"FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
    $passed++;
}
$basic = ['active'=>1,'quantity'=>30,'minimum_stock_level'=>10,'expiry_date'=>date('Y-m-d',strtotime('+100 days'))];
check(medicine_status($basic)==='AVAILABLE','normal inventory status');
check(medicine_status(array_replace($basic,['active'=>0,'quantity'=>0]))==='INACTIVE','inactive takes priority');
check(medicine_status(array_replace($basic,['expiry_date'=>date('Y-m-d',strtotime('-1 day'))]))==='EXPIRED','expired status');
check(medicine_status(array_replace($basic,['quantity'=>0]))==='OUT_OF_STOCK','out of stock status');
check(medicine_status(array_replace($basic,['expiry_date'=>date('Y-m-d',strtotime('+30 days'))]))==='EXPIRING_SOON','30-day inclusive status');
check(medicine_status(array_replace($basic,['quantity'=>10]))==='LOW_STOCK','low-stock status');
check(e('<script>')==='&lt;script&gt;','HTML escaping');
check(price_value('1.25','Price')==='1.25','valid decimal price');
check(price_value('0','Discount',true)==='0.00','zero discount');
try { price_value('-1','Price'); check(false,'negative price rejected'); } catch (DomainException $ex) { check(true,'negative price rejected'); }
try { positive_int(0,'Quantity'); check(false,'zero quantity rejected'); } catch (DomainException $ex) { check(true,'zero quantity rejected'); }
check(positive_int(0,'Stock',true)===0,'zero inventory accepted');
check(array_key_exists('INHALER',medicine_types()),'medicine types included');
check(str_contains(url('medicines',['status'=>'EXPIRED']),'status=EXPIRED'),'query URL routing');
$source = file_get_contents(dirname(__DIR__).'/database/pharmacare.sql');
check(substr_count($source, "'DEMO-")===120,'original 120 demo batch records');
check(str_contains($source,'FOREIGN KEY'),'foreign keys in schema');
echo "\nAll $passed smoke tests passed.\n";
