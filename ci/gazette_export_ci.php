<?php
/** Runs inside GitHub Actions (see .github/workflows/gazette-feed.yml). Builds and signs the Resmî Gazete feed.
 *  Same job as tools/gazette_export.php on the owner's PC, but the signing key comes from the GAZETTE_SIGNING_KEY secret
 *  and the feed is written into the repository root (the checked-out repository). The key is never written to disk or printed.
 *  Exit codes: 0 every day is in the feed, 2 some days could not be read (feed is still written), 1 or more: failure.
 *  The code in ci/ is a copy of the project files gazette_feed.php, official_http.php and scraper/rg_gazette_lib.php; keep them identical. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/gazette_feed.php';
date_default_timezone_set('Europe/Istanbul');

function ci_fail(string $message,int $code=1): never {fwrite(STDERR,$message.PHP_EOL);exit($code);}

$private=(string)getenv('GAZETTE_SIGNING_KEY');
if(trim($private)==='')ci_fail('GAZETTE_SIGNING_KEY gizli değişkeni boş. GitHub > Settings > Secrets and variables > Actions altında tanımlayın.');
$privateKey=openssl_pkey_get_private($private);
if(!$privateKey)ci_fail('İmza anahtarı okunamadı (PEM biçimi bozuk).');
$public=(string)(openssl_pkey_get_details($privateKey)['key']??'');
if($public==='')ci_fail('Açık anahtar üretilemedi.');

$feedDir=dirname(__DIR__);
$days=max(10,min(120,(int)(getenv('FEED_DAYS')?:60)));
$zone=new DateTimeZone('Europe/Istanbul');$now=time();
// Today is included: the site scans "yesterday" on the next night.
$last=(new DateTimeImmutable('@'.$now))->setTimezone($zone)->format('Ymd');
$first=DateTimeImmutable::createFromFormat('!Ymd',$last,$zone)->modify('-'.($days-1).' days')->format('Ymd');
if($first<'20260101')$first='20260101';

$jsonPath=$feedDir.'/'.TF_GAZETTE_FEED_JSON;$sigPath=$feedDir.'/'.TF_GAZETTE_FEED_SIG;
$previous=[];
if(is_file($jsonPath)&&is_file($sigPath)){
    try{$previous=tf_gazette_feed_open((string)file_get_contents($jsonPath),(string)file_get_contents($sigPath),$public,$now)['responses'];}
    catch(Throwable $e){echo 'Önceki paket bu anahtarla açılamadı (başka imzalayıcı olabilir), baştan okunacak: '.$e->getMessage()."\n";}
}
$previous=array_map(static fn($r)=>['code'=>$r['code'],'body'=>$r['body']],$previous);

$fetch=static function(array $jobs): array {
    $responses=[];
    foreach(array_chunk($jobs,5) as $chunk){$responses+=tf_rg_fetch($chunk,microtime(true)+30);usleep(200000);}
    return $responses;
};
echo "Resmî Gazete okunuyor: $first - $last\n";
$result=tf_gazette_feed_collect($previous,$first,$last,$fetch);
$missing=[];
for($day=DateTimeImmutable::createFromFormat('!Ymd',$first,$zone),$end=DateTimeImmutable::createFromFormat('!Ymd',$last,$zone);$day<=$end;$day=$day->modify('+1 day'))
    if(!isset($result['items'][$day->format('Ymd').':0'])&&$day->format('Ymd')<$last)$missing[]=$day->format('Ymd');

// Never publish a feed that lost days compared with the one already in the repository: keep the older file instead.
if(count($result['items'])<count($previous)*0.9)ci_fail('Yeni paket öncekinden belirgin küçük ('.count($result['items']).' < '.count($previous).'); yayımlanmadı.',3);

$json=tf_gazette_feed_build($result['items'],$now,$first,$last);
$signature=tf_gazette_feed_sign($json,$private);
if(tf_gazette_feed_open($json,$signature,$public,$now)['created_at']!==$now)ci_fail('Üretilen paket doğrulanamadı.',4);
foreach([[$jsonPath,$json],[$sigPath,$signature]] as [$path,$bytes]){
    $temp=$path.'.tmp';
    if(file_put_contents($temp,$bytes,LOCK_EX)!==strlen($bytes)||!rename($temp,$path))ci_fail('Paket dosyası yazılamadı: '.$path,5);
}
echo count($result['items'])." sayfa pakete yazıldı; bu çalışmada okunan gün: ".count($result['fetched_days']).".\n";
echo 'Paket boyutu: '.number_format(strlen($json)/1024,0,',','.')." KB\n";
foreach(array_slice($result['failures'],0,8,true) as $key=>$why)echo "  okunamadı $key: $why\n";
if($missing){echo 'EKSİK günler: '.implode(', ',array_slice($missing,0,12)).(count($missing)>12?' ...':'')."\n";exit(2);}
echo "Tüm günler pakette.\n";
