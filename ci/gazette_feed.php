<?php
/** Signed Resmî Gazete feed.
 * resmigazete.gov.tr refuses the hosting IP, so a PC inside Turkey fetches the official
 * daily indexes, signs them and publishes them to a public GitHub repository. The host
 * verifies the signature and hands the pages to the normal Gazette scan, which still
 * validates every page itself (date, issue number, official links). Without a valid
 * feed the scan falls back to direct requests exactly as before.
 */
require_once __DIR__.'/scraper/rg_gazette_lib.php';

// Raw base URL of the public feed repository, e.g. https://raw.githubusercontent.com/USER/REPO/main/
const TF_GAZETTE_FEED_BASE='https://raw.githubusercontent.com/zahek/resmi_gazete/main/';
const TF_GAZETTE_FEED_JSON='gazette-feed.json';
const TF_GAZETTE_FEED_SIG='gazette-feed.sig';

function tf_gazette_feed_url(string $base, string $name): string {
    if(!preg_match('~^https://raw\.githubusercontent\.com/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+/[A-Za-z0-9._/-]+/$~D',$base))
        throw new RuntimeException('Gazete paketi adresi izinli değil.');
    return $base.$name;
}

/** Local PC only. Returns the JSON bytes that the signature covers. */
function tf_gazette_feed_build(array $items, int $now, string $from, string $to): string {
    $encoded=[];
    foreach($items as $key=>$response){
        $entry=['c'=>(int)$response['code']];
        if($entry['c']===200)$entry['b']=base64_encode(gzdeflate((string)$response['body'],9));
        $encoded[$key]=$entry;
    }
    ksort($encoded);
    return json_encode(['format'=>'tariflex-gazette-v1','created_at'=>$now,'from'=>$from,'to'=>$to,'items'=>$encoded],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

function tf_gazette_feed_sign(string $json, string $privatePem): string {
    if(!openssl_sign($json,$signature,$privatePem,OPENSSL_ALGO_SHA256))throw new RuntimeException('Gazete paketi imzalanamadı.');
    return base64_encode($signature);
}

/** Verifies the signature first; nothing inside an unsigned or altered file is parsed. */
function tf_gazette_feed_open(string $json, string $signature, string $publicPem, int $now): array {
    if(strlen($json)>4194304)throw new RuntimeException('Gazete paketi çok büyük.');
    $publicKey=openssl_pkey_get_public($publicPem);
    if(!$publicKey)throw new RuntimeException('Gazete paketi açık anahtarı kullanılamıyor.');
    $raw=base64_decode(trim($signature),true);
    if($raw===false||$raw===''||openssl_verify($json,$raw,$publicKey,OPENSSL_ALGO_SHA256)!==1)throw new RuntimeException('Gazete paketi imzası doğrulanamadı.');
    $data=json_decode($json,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($data)||($data['format']??'')!=='tariflex-gazette-v1'||!is_int($data['created_at']??null)||!is_array($data['items']??null))
        throw new RuntimeException('Gazete paketi biçimi geçersiz.');
    if($data['created_at']>$now+300)throw new RuntimeException('Gazete paketi gelecek tarihli.');
    if($data['created_at']<$now-90*86400)throw new RuntimeException('Gazete paketi 90 günden eski; yerel işi çalıştırın.');
    if(count($data['items'])>4000)throw new RuntimeException('Gazete paketi kayıt sınırını aşıyor.');
    $responses=[];
    foreach($data['items'] as $key=>$item){
        if(!is_string($key)||!preg_match('/^20[0-9]{6}:([0-9]{1,2})$/D',$key,$m)||!is_array($item))throw new RuntimeException('Gazete paketinde geçersiz kayıt.');
        $code=$item['c']??null;
        if($code===404&&(int)$m[1]>0){$responses[$key]=['code'=>404,'body'=>'','error'=>''];continue;}
        if($code!==200||!is_string($item['b']??null))throw new RuntimeException('Gazete paketinde geçersiz yanıt kodu.');
        $packed=base64_decode($item['b'],true);
        $body=$packed===false?false:@gzinflate($packed,1048576);
        if($body===false||$body==='')throw new RuntimeException('Gazete paketindeki sayfa açılamadı veya sınırı aşıyor.');
        $responses[$key]=['code'=>200,'body'=>$body,'error'=>''];
    }
    return ['created_at'=>$data['created_at'],'from'=>(string)($data['from']??''),'to'=>(string)($data['to']??''),'responses'=>$responses];
}

function tf_gazette_feed_download(string $url): string {
    if(!str_starts_with($url,'https://raw.githubusercontent.com/'))throw new RuntimeException('Gazete paketi adresi izinli değil.');
    $body='';$c=curl_init($url);
    curl_setopt_array($c,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Tariflex-GazetteFeed/1.0',
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>4194304)return 0;$body.=$chunk;return strlen($chunk);}]);
    if(PHP_OS_FAMILY==='Windows'&&defined('CURLSSLOPT_NATIVE_CA'))curl_setopt($c,CURLOPT_SSL_OPTIONS,CURLSSLOPT_NATIVE_CA);
    $ok=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if($ok===false||$status!==200)throw new RuntimeException('Gazete paketi alınamadı: HTTP '.$status);
    return $body;
}

/** null when no feed is configured; throws when a configured feed is unreachable or invalid. */
function tf_gazette_feed_load(string $root, int $now, ?callable $download=null, ?string $base=null): ?array {
    $base=$base??TF_GAZETTE_FEED_BASE;
    if($base==='')return null;
    $download=$download??'tf_gazette_feed_download';
    // Two signers may publish the feed: the owner's PC (gazette_feed_public.pem) and the GitHub Actions job (gazette_feed_public_actions.pem).
    // A feed is accepted when it verifies with either key; a file signed by neither is rejected.
    $keys=[];
    foreach(['gazette_feed_public.pem','gazette_feed_public_actions.pem'] as $file){
        $pem=@file_get_contents($root.'/'.$file);
        if(is_string($pem)&&$pem!=='')$keys[]=$pem;
    }
    if(!$keys)throw new RuntimeException('gazette_feed_public.pem bulunamadı.');
    $json=$download(tf_gazette_feed_url($base,TF_GAZETTE_FEED_JSON));
    $signature=$download(tf_gazette_feed_url($base,TF_GAZETTE_FEED_SIG));
    $failure=null;
    foreach($keys as $publicKey){
        try{return tf_gazette_feed_open($json,$signature,$publicKey,$now);}
        catch(Throwable $error){$failure=$error;}
    }
    throw $failure;
}

/** Pages the feed covers are served from it; anything else goes to the normal direct fetch. */
function tf_gazette_feed_fetch(array $feed, ?callable $direct=null): callable {
    $direct=$direct??'tf_rg_fetch';
    return static function(array $jobs, float $deadline) use($feed,$direct): array {
        $served=[];$missing=[];
        foreach($jobs as $job){
            $key=$job['day'].':'.$job['edition'];
            if(isset($feed['responses'][$key]))$served[$key]=$feed['responses'][$key];else $missing[]=$job;
        }
        return $missing?$served+$direct($missing,$deadline):$served;
    };
}

/** Local PC only. Keeps older days frozen, re-reads the last 8 days, validates every page. */
function tf_gazette_feed_collect(array $previous, string $first, string $last, callable $fetch): array {
    $zone=new DateTimeZone('Europe/Istanbul');
    $recentFrom=DateTimeImmutable::createFromFormat('!Ymd',$last,$zone)->modify('-7 days')->format('Ymd');
    $items=[];
    foreach($previous as $key=>$response){$day=substr((string)$key,0,8);if($day>=$first&&$day<=$last)$items[$key]=$response;}
    $need=[];
    for($day=DateTimeImmutable::createFromFormat('!Ymd',$first,$zone),$end=DateTimeImmutable::createFromFormat('!Ymd',$last,$zone);$day<=$end;$day=$day->modify('+1 day')){
        $d=$day->format('Ymd');
        if(!isset($items[$d.':0'])||$d>=$recentFrom)$need[]=$d;
    }
    $failures=[];
    foreach($need as $day){
        $jobs=[];for($e=0;$e<=4;$e++)$jobs[]=['day'=>$day,'edition'=>$e,'attempts'=>0];
        $responses=$fetch($jobs);
        // Like the server scan, keep probing supplements while the previous one exists.
        for($e=5;$e<=9&&(int)($responses[$day.':'.($e-1)]['code']??0)===200;$e++)
            $responses+=$fetch([['day'=>$day,'edition'=>$e,'attempts'=>0]]);
        foreach($responses as $key=>$response){
            [$d,$edition]=explode(':',$key);$code=(int)($response['code']??0);
            if($code===404&&(int)$edition>0&&empty($response['error'])){$items[$key]=['code'=>404,'body'=>''];continue;}
            if($code===200&&empty($response['error'])){
                try{tf_rg_parse((string)$response['body'],$d,(int)$edition);$items[$key]=['code'=>200,'body'=>(string)$response['body']];}
                catch(Throwable $error){$failures[$key]=$error->getMessage();}
                continue;
            }
            $failures[$key]='HTTP '.$code.(!empty($response['error'])?': '.mb_substr((string)$response['error'],0,120):'');
        }
    }
    ksort($items);
    return ['items'=>$items,'failures'=>$failures,'fetched_days'=>$need];
}
