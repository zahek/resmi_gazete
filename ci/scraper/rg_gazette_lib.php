<?php
require_once __DIR__.'/../official_http.php';
/** Resumable official bulletin discovery. Loading this file performs no I/O. */
if (!function_exists('rg_normalize_tr')) {
    /**
     * Türkçe karakterleri ASCII ve arama için güvenli küçük harfe normalize eder.
     */
    function rg_normalize_tr(string $text): string {
        $tr = [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u',
            'Ş' => 's', 'ş' => 's',
            'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ];
        $text = strtr($text, $tr);
        $text = str_replace(["\xCC\x87", "\xC4\xB0", "\xC4\xB1"], ['', 'i', 'i'], $text);
        return strtolower(trim($text));
    }
}


function tf_rg_filters(): array {
    $BLACKLIST_PATTERNS = [
    'avukatlik', 'arabuluculuk', 'bilirkisilik', 'uzlastirmaci', 'noterlik', 'cmk tarifesi', 'yargi tarifesi',
    'kamu ihale', 'ihale tebligi', 'ihalelere katilmaktan yasaklama', 'ihale kanunu', 'is bitirme belgeleri', 'muteahhitlik', 'proje ve kontrolluk', 'yapim isleri',
    'milli emlak', 'hazine tasinmaz', 'tasinmaz kultur', 'tasinmaz satis', 'arsa karsiligi', 'yapi yaklasik',
    'sosyal guvenlik', 'saglik uygulama tebligi', 'sgk', 'tibbi urunler ruhsatlandirma', 'ilac geri odeme', 'hastane tarifesi',
    'egitim destegi', 'ogrenim goren', 'ogrenim destegi', 'ozel okul', 'ozel mesleki', 'burs', 'ogrenci yurdu', 'anadolu lisesi',
    'cig sut', 'buzagi destegi', 'hayvancilik', 'kirsal kalkinma', 'tarimsal destekleme', 'aricilik', 'mazot ve gubre', 'yem destegi', 'kurbanlik', 'su urunleri destekleme',
    'cevreye duyarli tasarim', 'enerji etiketlemesi', 'eko-tasarim', 'ekotasarim', 'hazirda bekleme', 'sarj cihazlari ve usb', 'atik elektrikli',
    'yiyecek yardimi', 'harcirah', 'devlet memurlari', 'toplu sozlesme', 'sendika uyeligi',
    'vergi usul kanunu genel tebligi', 'gelir vergisi genel tebligi', 'motorlu tasitlar vergisi genel tebligi', 'veraset ve intikal vergisi', 'damga vergisi kanunu genel tebligi', 'emlak vergisi', 'harclar kanunu genel tebligi',
    'ceza muhakemesi', 'icra ve iflas', 'mudafi ve vekil', 'adli yargi',
    'akreditasyon kullanim ucreti', 'turkak',
    'cek defterlerinin', 'temerrut faiz', 'bankalarca', 'masak', 'mali suclari', 'mevduat ve katilim',
    'ulusal meslek standardi', 'meslek standartlarina', 'mesleki yeterlilik',
    'mimarlik ve muhendislik',
    'fiyat tarifeleri hakkinda yonetmelik', 'azami fiyat tarifeleri', 'yetki belgesi ve sertifika bedelleri', 'ekmek fiyat',
    'izin belgeleri listesi', 'izin belgelerinin listesi', 'iptal edilen dahilde isleme'
];

// 2. KESİN KABUL VE ÖNCELİK LİSTESİ (Pozitif Dış Ticaret Alanları)
// Yalnızca dış ticaret, gümrük, tarife, ithalat, ihracat ve gözetim/damping önlemleri içeren tebliğler kabul edilir.

// Kritik Önlemler (danger): Anti-Damping, Telafi Edici Vergi, İhracı Yasak Mallar, Ek Mali Yükümlülük, Korunma Önlemi
$KEYWORDS_CRITICAL = [
    'damping', 'anti-damping', 'haksiz rekabetin onlenmesi',
    'telafi edici vergi', 'subvansiyon',
    'ihraci yasak', 'ihraci on izne bagli', 'on izne bagli mallar',
    'ek mali yukumluluk', 'safeguard', 'korunma onlemi'
];

// Uyarı & Gözetim (warning): İthalatta Gözetim, Tarife Kontenjanı, İlave Gümrük Vergisi (İGV), İhracı Kayda Bağlı, Menşe
$KEYWORDS_WARNING = [
    'gozetim tebligi', 'ithalatta gozetim', 'kiymet bazli gozetim', 'tps-0964', 'gozetim belgesi',
    'ilave gumruk vergisi', 'igv',
    'tarife kontenjani', 'ithalat kotasi', 'kota ve tarife kontenjani', 'askiya alma karari',
    'ihraci kayda bagli', 'kayda bagli mallar',
    'mense ispat', 'dolasim belgesi', 'a.tr', 'eur.1', 'eur-med',
    'gumruk birligi'
];

// Bilgilendirme & Rejimler (info): Gümrük Kanunu, TAREKS, ÜGD, Dış Ticaret Standartları, Serbest Bölge, DFİF
$KEYWORDS_INFO = [
    'gumruk tarife', 'tarife pozisyonu', 'tarife cetveli', 'gumruk yonetmeligi', 'gumruk genel tebligi',
    'gumruk muafiyeti', 'gumruk uzlasma', 'ihtisas gumrugu', 'gumruksuz satis magaza', '4458 sayili gumruk kanunu',
    'ithalat rejimi', 'ihracat rejimi', 'ithalat denetimi', 'ihracat denetimi',
    'tareks', 'urun guvenligi ve denetimi', 'dis ticarette standardizasyon',
    'serbest bolge', 'ihtisas serbest bolge',
    'dfif', 'fiyat istikrar fonu',
    'dis ticaret sermaye sirketi',
    'gecici ithalat', 'dahilde isleme', 'haricte isleme'
];

    $KEYWORDS_INFO=array_merge($KEYWORDS_INFO,['ithalat','ihracat','ithali','gumruk','dis ticaret','ticaret politikasi','mense','urun guvenligi']);
    return [$KEYWORDS_CRITICAL,$KEYWORDS_WARNING,$KEYWORDS_INFO,$BLACKLIST_PATTERNS];
}

function tf_rg_url(string $date, int $edition=0): string {
    return 'https://www.resmigazete.gov.tr/eskiler/'.substr($date,0,4).'/'.substr($date,4,2).'/'.$date.($edition ? 'M'.$edition : '').'.htm';
}

function tf_rg_fihrist_url(string $date): string {
    if (!preg_match('/^[0-9]{8}$/D',$date)) throw new InvalidArgumentException('Geçersiz Resmî Gazete tarihi');
    $iso=substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2);
    return 'https://www.resmigazete.gov.tr/fihrist?'.http_build_query(['tarih'=>$iso],'','&',PHP_QUERY_RFC3986);
}

function tf_rg_init(SQLite3 $db, string $today, int $now): void {
    // Legacy rg_scan_log did not verify individual issues, so it cannot prove coverage.
    $db->exec("CREATE TABLE IF NOT EXISTS rg_issue_state (
        day TEXT NOT NULL, edition INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'queued',
        checked_at INTEGER NOT NULL DEFAULT 0, next_retry INTEGER NOT NULL DEFAULT 0,
        attempts INTEGER NOT NULL DEFAULT 0, entries INTEGER NOT NULL DEFAULT 0,
        matched INTEGER NOT NULL DEFAULT 0, error TEXT NOT NULL DEFAULT '',
        PRIMARY KEY(day,edition));
        CREATE TABLE IF NOT EXISTS rg_notice_sources (
        day TEXT NOT NULL,edition INTEGER NOT NULL,href TEXT NOT NULL,notice_id INTEGER NOT NULL,
        PRIMARY KEY(day,edition,href));
        CREATE INDEX IF NOT EXISTS rg_issue_due ON rg_issue_state(next_retry,day)");
    $insert=$db->prepare('INSERT OR IGNORE INTO rg_issue_state(day,edition) VALUES (?,?)');
    $start=new DateTimeImmutable('2026-01-01',new DateTimeZone('Europe/Istanbul'));
    $end=DateTimeImmutable::createFromFormat('!Ymd',$today,new DateTimeZone('Europe/Istanbul'));
    $db->exec('BEGIN IMMEDIATE');
    try {
        for ($day=$start;$day<=$end;$day=$day->modify('+1 day')) {
            // M1 is the first supplementary issue. Previously it was omitted.
            for ($edition=0;$edition<=4;$edition++) {
                $insert->reset();$insert->bindValue(1,$day->format('Ymd'));$insert->bindValue(2,$edition,SQLITE3_INTEGER);$insert->execute();
            }
        }
        $db->exec('COMMIT');
    } catch(Throwable $e) {$db->exec('ROLLBACK');throw $e;}
}

function tf_rg_clean(string $text): string {
    $text=html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8');
    return trim((string)preg_replace('/[\s\x{00a0}]+/u',' ',$text));
}

function tf_rg_title_key(string $text): string {
    $text=(string)preg_replace('/\bteblig\s+no\b/','no',rg_normalize_tr($text));
    return (string)preg_replace('/[^a-z0-9]+/','',$text);
}

/** Only official document links for this exact date and edition are accepted. */
function tf_rg_document_url(string $href, string $day, int $edition): ?string {
    $href=html_entity_decode(trim($href),ENT_QUOTES|ENT_HTML5,'UTF-8');
    if (preg_match('#^https?://#i',$href)) {
        $host=strtolower((string)parse_url($href,PHP_URL_HOST));
        if (!in_array($host,['resmigazete.gov.tr','www.resmigazete.gov.tr'],true)) return null;
        $path=(string)parse_url($href,PHP_URL_PATH);
        if (!str_starts_with($path,'/eskiler/'.substr($day,0,4).'/'.substr($day,4,2).'/')) return null;
        $name=basename($path);
    } else {
        $path=(string)parse_url($href,PHP_URL_PATH);$name=basename($path);
        if (str_contains($path,'..') || (str_contains($path,'/') && $path!=='/eskiler/'.substr($day,0,4).'/'.substr($day,4,2).'/'.$name)) return null;
    }
    $stem=$day.($edition ? 'M'.$edition : '');
    if (!preg_match('/^'.preg_quote($stem,'/').'-[0-9]+\.(?:htm|html|pdf)$/i',$name)) return null;
    return dirname(tf_rg_url($day,$edition)).'/'.$name;
}

function tf_rg_parse(string $html, string $day, int $edition): array {
    if (!mb_check_encoding($html,'UTF-8')) $html=mb_convert_encoding($html,'UTF-8','Windows-1254');
    // After transcoding, DOM must not reinterpret UTF-8 bytes using the old meta charset.
    $html=preg_replace_callback('/(<meta\b[^>]*charset\s*=\s*["\']?)[a-zA-Z0-9_-]+/i',fn($m)=>$m[1].'UTF-8',$html);
    $text=tf_rg_clean($html);$norm=rg_normalize_tr($text);
    // A 200 response can be an access challenge, navigation page or wrong date.
    if (preg_match('/access denied|captcha|request rejected|just a moment/i',$text) ||
        !preg_match('/resm.{0,5}\s*gazete|yurutme ve idare|ilan bolumu/',$norm))
        throw new RuntimeException('Yanıt Resmî Gazete fihristi olarak doğrulanamadı.');
    $months=['01'=>'ocak','02'=>'subat','03'=>'mart','04'=>'nisan','05'=>'mayis','06'=>'haziran','07'=>'temmuz','08'=>'agustos','09'=>'eylul','10'=>'ekim','11'=>'kasim','12'=>'aralik'];
    $datePattern='/(?<![0-9])(?:0?'.(int)substr($day,6,2).'\s+'.$months[substr($day,4,2)].'\s+'.substr($day,0,4).'|'.substr($day,6,2).'[.\/-]'.substr($day,4,2).'[.\/-]'.substr($day,0,4).')(?![0-9])/';
    if (!preg_match($datePattern,$norm)) throw new RuntimeException('Fihrist yayın tarihi istenen günle eşleşmedi.');
    if(preg_match('/bugun\s+resm.{0,5}\s*gazete\s+yay[iı](?:m|n)lanmamaktadir/',$norm))
        return ['entries'=>[],'issue'=>'','editions'=>[],'not_published'=>true];
    $issue='';
    if (preg_match('/(\d{4,6})\s+sayili\s+resm.{0,5}\s*gazete/',$norm,$m)) $issue=$m[1];
    elseif (preg_match('/say[iı]\s*[:\-]?\s*(\d{4,6})/ui',$text,$m)) $issue=$m[1];
    if ($issue==='') throw new RuntimeException('Resmî Gazete sayı bilgisi bulunamadı.');
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
    try {$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
    finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
    $entries=[];$editions=[];
    foreach ($dom->getElementsByTagName('a') as $a) {
        $href=$a->getAttribute('href');$name=basename((string)parse_url($href,PHP_URL_PATH));
        if (preg_match('/^'.preg_quote($day,'/').'M([1-9][0-9]?)\.htm$/i',$name,$m)) {
            $official=tf_rg_document_url(str_replace('.htm','-1.htm',$href),$day,(int)$m[1]);
            if ($official) $editions[]=(int)$m[1];
        }
        $url=tf_rg_document_url($href,$day,$edition);
        if (!$url) continue;
        $title=tf_rg_clean($a->textContent);
        $title=trim((string)preg_replace('/^[\s\-–—]+/u','',$title));
        if (mb_strlen($title)>=20 && mb_strlen($title)<=1000) $entries[$url]=['title'=>$title,'url'=>$url];
    }
    if (!$entries) throw new RuntimeException('Bu sayıya ait resmî metin bağlantıları bulunamadı; yeniden denenecek.');
    return ['entries'=>array_values($entries),'issue'=>$issue,'editions'=>array_unique($editions)];
}

function tf_rg_fetch_group(array $requests, float $deadline): array {
    if (!$requests) return [];
    if (!function_exists('curl_multi_init')) throw new RuntimeException('PHP cURL uzantısı gerekli.');
    static $pool=null;
    if($pool===null){$pool=curl_multi_init();curl_multi_setopt($pool,CURLMOPT_MAX_HOST_CONNECTIONS,5);}
    $multi=$pool;$handles=[];$responses=[];
    foreach ($requests as $key=>$url) {
        $ch=curl_init($url);$ms=max(100,min(8000,(int)(($deadline-microtime(true))*1000)));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_TIMEOUT_MS=>$ms,CURLOPT_CONNECTTIMEOUT_MS=>min(5000,$ms),
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_ENCODING=>'',
            CURLOPT_USERAGENT=>'Mozilla/5.0 Tariflex-RGSync/2.0',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml']]);
        if(PHP_OS_FAMILY==='Windows' && defined('CURLSSLOPT_NATIVE_CA'))curl_setopt($ch,CURLOPT_SSL_OPTIONS,CURLSSLOPT_NATIVE_CA);
        curl_multi_add_handle($multi,$ch);$handles[$key]=$ch;
    }
    try {
        do {
            do {$status=curl_multi_exec($multi,$active);} while ($status===CURLM_CALL_MULTI_PERFORM);
            if ($active) {if(curl_multi_select($multi,.2)===-1) usleep(10000);}
        } while ($active && $status===CURLM_OK && microtime(true)<$deadline);
        $finished=[];
        while($info=curl_multi_info_read($multi))$finished[spl_object_id($info['handle'])]=(int)$info['result'];
        foreach ($handles as $key=>$ch) {
            $errno=$finished[spl_object_id($ch)] ?? CURLE_OPERATION_TIMEDOUT;
            $error=curl_error($ch);if($errno && !$error)$error=curl_strerror($errno);
            $responses[$key]=['code'=>(int)curl_getinfo($ch,CURLINFO_HTTP_CODE),'body'=>(string)curl_multi_getcontent($ch),'error'=>$error];
        }
    } finally {foreach($handles as $ch){curl_multi_remove_handle($multi,$ch);curl_close($ch);}}
    return $responses;
}

function tf_rg_should_fallback(array $job, array $response): bool {
    if ((int)($job['edition'] ?? -1)!==0) return false;
    $code=(int)($response['code'] ?? 0);
    return !empty($response['error']) || $code===0 || in_array($code,[403,408,429],true) || $code>=500;
}

function tf_rg_fallback_result(array $primary, array $fallback, string $day): array {
    if ((int)($fallback['code'] ?? 0)===200 && empty($fallback['error'])) {
        try {
            tf_rg_parse((string)($fallback['body'] ?? ''),$day,0);
            return $fallback;
        } catch (RuntimeException $error) {
            $fallbackError='Tarihli resmî fihrist doğrulanamadı: '.$error->getMessage();
        }
    } else {
        $fallbackError='Tarihli resmî fihrist HTTP '.(int)($fallback['code'] ?? 0)
            .(!empty($fallback['error']) ? ': '.mb_substr((string)$fallback['error'],0,200) : '');
    }
    $primaryError=trim((string)($primary['error'] ?? ''));
    if ($primaryError==='') $primaryError='Arşiv isteği HTTP '.(int)($primary['code'] ?? 0);
    $primary['error']=$primaryError.'; '.$fallbackError;
    return $primary;
}

/** Strict-TLS official archive fetch with a validated official fihrist fallback for daily issues. */
function tf_rg_fetch(array $jobs, float $deadline): array {
    $requests=[];$fallbackRequests=[];$jobByKey=[];
    foreach ($jobs as $job) {
        $key=$job['day'].':'.$job['edition'];
        $jobByKey[$key]=$job;
        $requests[$key]=tf_official_transport_url(tf_rg_url($job['day'],(int)$job['edition']));
    }
    $responses=tf_rg_fetch_group($requests,$deadline);
    foreach ($jobByKey as $key=>$job) {
        if (tf_rg_should_fallback($job,$responses[$key] ?? [])) {
            $fallbackRequests[$key]=tf_rg_fihrist_url($job['day']);
        }
    }
    if ($fallbackRequests && microtime(true)<$deadline-0.5) {
        $fallbackResponses=tf_rg_fetch_group($fallbackRequests,$deadline);
        foreach ($fallbackRequests as $key=>$url) {
            $responses[$key]=tf_rg_fallback_result(
                $responses[$key] ?? [],
                $fallbackResponses[$key] ?? ['code'=>0,'body'=>'','error'=>'Fihrist yanıtı alınamadı'],
                (string)$jobByKey[$key]['day']
            );
        }
    }
    return $responses;
}

function tf_rg_summary(SQLite3 $db, string $today, int $now): array {
    $q=$db->prepare("SELECT day,COUNT(*) total,SUM(status IN ('success','missing','not_published')) checked,
        SUM(status='error') errors,SUM(checked_at=0) untouched,MIN(checked_at) checked_at
        FROM rg_issue_state WHERE day BETWEEN '20260101' AND ? GROUP BY day ORDER BY day");
    $q->bindValue(1,$today);$rows=$q->execute();$days=0;$completed=0;$errors=0;$issues=0;$pending=0;$through='';$next='';$gap=false;
    while($row=$rows->fetchArray(SQLITE3_ASSOC)) {
        $days++;$issues+=(int)$row['total'];$errors+=(int)$row['errors'];$pending+=(int)$row['total']-(int)$row['checked'];
        $done=(int)$row['total']===(int)$row['checked'];if($done)$completed++;
        if (!$done) {$gap=true;if($next==='')$next=$row['day'];}
        if (!$gap) $through=$row['day'];
    }
    $ready=(int)$db->querySingle("SELECT COUNT(*) FROM rg_issue_state WHERE day<='".$today."' AND next_retry<=".$now);
    $latest=(int)$db->querySingle('SELECT MAX(checked_at) FROM rg_issue_state');
    return ['start_date'=>'20260101','target_date'=>$today,'days_total'=>$days,'days_checked'=>$completed,
        'issues_total'=>$issues,'issues_pending'=>$pending,'errors_count'=>$errors,'verified_through'=>$through,
        'next_missing_day'=>$next,'ready'=>$ready,'last_checked_at'=>$latest];
}

function tf_rg_sync(SQLite3 $db, array $options=[]): array {
    $db->enableExceptions(true);$db->busyTimeout(2000);
    $now=(int)($options['now'] ?? time());$zone=new DateTimeZone('Europe/Istanbul');
    $today=(new DateTimeImmutable('@'.$now))->setTimezone($zone)->format('Ymd');
    if (isset($options['target_date'])) {
        $target=(string)$options['target_date'];
        $targetDate=preg_match('/^20[0-9]{6}$/D',$target)?DateTimeImmutable::createFromFormat('!Ymd',$target,$zone):false;
        if (!$targetDate || $targetDate->format('Ymd')!==$target || $target<'20260101' || $target>$today) throw new InvalidArgumentException('Geçersiz tarama hedef tarihi');
        $today=$target;
    }
    $deadline=microtime(true)+(float)($options['budget'] ?? 18);$max=(int)($options['limit'] ?? 40);
    tf_rg_init($db,$today,$now);$recent=(new DateTimeImmutable('@'.$now))->setTimezone($zone)->modify('-7 days')->format('Ymd');
    $added=0;$updated=0;$skipped=0;$processed=0;$failures=[];$warnings=[];$seen=[];$sourcePaused=false;
    [$critical,$warning,$info,$blacklist]=tf_rg_filters();
    // Do not begin a group with only a few milliseconds left and manufacture timeouts.
    while ($processed<$max && microtime(true)<$deadline-7.5) {
        // Latest days first in each group, then oldest unverified days. This prevents
        // both a long archive backlog and a recent failure from starving the other.
        $all=[];$q=$db->query("SELECT * FROM rg_issue_state WHERE day<='$today' AND next_retry<=$now ORDER BY CASE WHEN day>='$recent' THEN 0 ELSE 1 END,day DESC,edition");
        while($row=$q->fetchArray(SQLITE3_ASSOC)) if(!isset($seen[$row['day'].':'.$row['edition']]))$all[]=$row;
        if (!$all) break;
        $new=array_values(array_filter($all,fn($r)=>$r['day']>=$recent));
        $old=array_values(array_filter($all,fn($r)=>$r['day']<$recent));
        usort($old,fn($a,$b)=>[$a['day'],$a['edition']]<=>[$b['day'],$b['edition']]);
        $jobs=array_merge(array_slice($new,0,$old?2:5),array_slice($old,0,$new?3:5));
        $jobs=array_slice($jobs,0,min(5,$max-$processed));
        foreach($jobs as $job)$seen[$job['day'].':'.$job['edition']]=true;
        $responses=isset($options['fetch']) ? ($options['fetch'])($jobs,$deadline) : tf_rg_fetch($jobs,$deadline);
        $failureStart=count($failures);
        foreach ($jobs as $job) {
            $day=$job['day'];$edition=(int)$job['edition'];$key=$day.':'.$edition;$processed++;
            $response=$responses[$key] ?? ['code'=>0,'error'=>'Kaynak yanıtı yok'];$code=(int)($response['code'] ?? 0);
            $state='error';$entries=0;$matched=0;$error='';$retry=$now+min(28800,900*(2**min(5,(int)$job['attempts'])));
            $before=[$added,$updated,$skipped];$db->exec('BEGIN IMMEDIATE');
            try {
                if ($code===404 && $edition>0 && empty($response['error'])) {
                    // A missing supplementary issue is not a missing daily index.
                    $state='missing';$retry=$day===$today ? $now+3600 : ($day>=$recent ? $now+28800 : PHP_INT_MAX);
                } else {
                    if ($code!==200 || !empty($response['error'])) throw new RuntimeException('HTTP '.$code.(!empty($response['error']) ? ': '.mb_substr($response['error'],0,200) : ''));
                    $parsed=tf_rg_parse((string)($response['body'] ?? ''),$day,$edition);$entries=count($parsed['entries']);
                    $display=substr($day,6,2).'.'.substr($day,4,2).'.'.substr($day,0,4);
                    $existing=[];$q=$db->prepare('SELECT id,baslik,resmi_gazete_url FROM gtip_duyurulari WHERE resmigazete_tarih=?');$q->bindValue(1,$display);$rows=$q->execute();
                    while($row=$rows->fetchArray(SQLITE3_ASSOC))$existing[tf_rg_title_key($row['baslik'])]=$row;
                    foreach ($parsed['entries'] as $entry) {
                        $type=rg_classify_text($entry['title'],$critical,$warning,$info,$blacklist);if($type===null)continue;$matched++;
                        $source=$db->prepare('SELECT notice_id FROM rg_notice_sources WHERE day=? AND edition=? AND href=?');
                        foreach([$day,$edition,$entry['url']] as $i=>$v)$source->bindValue($i+1,$v);$sourceRow=$source->execute()->fetchArray(SQLITE3_NUM);$id=(int)($sourceRow[0] ?? 0);
                        $known=$existing[tf_rg_title_key($entry['title'])] ?? null;
                        if (!$id && $known) $id=(int)$known['id'];
                        if ($id && !(int)$db->querySingle('SELECT COUNT(*) FROM gtip_duyurulari WHERE id='.$id)) {
                            // An administrator removed a previously discovered notice; respect that.
                            $skipped++;continue;
                        }
                        if (!$id) {
                            // A heading alone does not establish all affected GTIPs. Leave scope general.
                            $gtip='GENEL';if(preg_match('/\b(\d{4}(?:\.\d{2}){4}|\d{12})\b/',$entry['title'],$m))$gtip=str_replace('.','',$m[1]);
                            $q=$db->prepare("INSERT INTO gtip_duyurulari(gtip,baslik,icerik,tur,aktif,kaynak_adi,resmi_gazete_url,resmigazete_tarih,resmigazete_sayi,eklenme_tarihi,guncellenme_tarihi)
                                VALUES (?,?,?,?,1,'T.C. Resmî Gazete (Otomatik)',?,?,?,datetime('now'),datetime('now'))");
                            $content='T.C. Resmî Gazete’de '.$display.' tarihinde, '.$parsed['issue'].' sayılı'.($edition?' ('.$edition.'. mükerrer)':'').' nüshada yayımlanan düzenleme. Ayrıntılar için resmî metni açınız.';
                            foreach([$gtip,$entry['title'],$content,$type,$entry['url'],$display,$parsed['issue']] as $i=>$v)$q->bindValue($i+1,$v);
                            $q->execute();$id=$db->lastInsertRowID();$added++;$existing[tf_rg_title_key($entry['title'])]=['id'=>$id];
                        } else {
                            $skipped++;
                            // Upgrade old links to the exact official document without overwriting edited text/visibility.
                            $q=$db->prepare("UPDATE gtip_duyurulari SET resmi_gazete_url=?,resmigazete_sayi=?,guncellenme_tarihi=datetime('now') WHERE id=? AND (COALESCE(resmi_gazete_url,'')<>? OR COALESCE(resmigazete_sayi,'')<>?)");
                            foreach([$entry['url'],$parsed['issue'],$id,$entry['url'],$parsed['issue']] as $i=>$v)$q->bindValue($i+1,$v);$q->execute();$updated+=$db->changes();
                        }
                        $q=$db->prepare('INSERT OR IGNORE INTO rg_notice_sources(day,edition,href,notice_id) VALUES (?,?,?,?)');foreach([$day,$edition,$entry['url'],$id] as $i=>$v)$q->bindValue($i+1,$v);$q->execute();
                    }
                    $extraEditions=$parsed['editions'];
                    // If a fourth (or later) supplement exists, probe the next one
                    // even when an older index does not link all supplements.
                    if($edition>=4 && $edition<99 && empty($parsed['not_published']))$extraEditions[]=$edition+1;
                    foreach(array_unique($extraEditions) as $extra) {
                        $q=$db->prepare('INSERT OR IGNORE INTO rg_issue_state(day,edition) VALUES (?,?)');$q->bindValue(1,$day);$q->bindValue(2,$extra,SQLITE3_INTEGER);$q->execute();
                    }
                    $state=!empty($parsed['not_published'])?'not_published':'success';$retry=$day===$today ? $now+3600 : ($day>=$recent ? $now+28800 : PHP_INT_MAX);
                }
                $q=$db->prepare('UPDATE rg_issue_state SET status=?,checked_at=?,next_retry=?,attempts=0,entries=?,matched=?,error=? WHERE day=? AND edition=?');
                foreach([$state,$now,$retry,$entries,$matched,'',$day,$edition] as $i=>$v)$q->bindValue($i+1,$v,is_int($v)?SQLITE3_INTEGER:SQLITE3_TEXT);$q->execute();
                $db->exec('COMMIT');
            } catch(Throwable $e) {
                $db->exec('ROLLBACK');[$added,$updated,$skipped]=$before;$error=$e->getMessage();$failures[]=$key.' '.$error;
                $q=$db->prepare("UPDATE rg_issue_state SET status='error',next_retry=?,attempts=attempts+1,error=? WHERE day=? AND edition=?");
                foreach([$retry,$error,$day,$edition] as $i=>$v)$q->bindValue($i+1,$v,is_int($v)?SQLITE3_INTEGER:SQLITE3_TEXT);$q->execute();
            }
        }
        // Do not hammer a blocked/unreachable source through hundreds of dates.
        if (count($failures)-$failureStart>=3) {$sourcePaused=true;break;}
        if(!isset($options['fetch']))usleep(150000);
    }
    $summary=tf_rg_summary($db,$today,$now);$continuing=$summary['ready']>0 && !$sourcePaused;
    if ($failures) $warnings[]='Kaynak/ayrıştırma hataları var; eksik sayılar tamamlandı sayılmadı.';
    $message='Bu grupta '.$processed.' sayı kontrol edildi; '.$added.' yeni tebliğ, '.$updated.' kaynak bağlantısı güncellendi. '.$summary['days_checked'].' / '.$summary['days_total'].' gün kontrol edildi.';
    return array_merge($summary,['engine'=>'gazette_v2','added'=>$added,'updated'=>$updated,'skipped'=>$skipped,'items'=>[],
        'processed_this_call'=>$processed,'continue'=>$continuing,'cycle_complete'=>!$continuing && !$summary['issues_pending'],
        'retry_after'=>$failures?900:60,'upstream_paused'=>$sourcePaused,
        'errors'=>$failures,'warnings'=>$warnings,'message'=>$message]);
}
if (!function_exists('rg_classify_text')) {
/**
 * Metni kesin kara liste ve pozitif dış ticaret anahtar kelimeleriyle filtreleyip sınıflandırır.
 * İlgisiz tebliğler anında elenir ve null döner.
 */
function rg_classify_text(string $text, array $kw_critical, array $kw_warning, array $kw_info, array $blacklist = []): ?string {
    $norm = rg_normalize_tr($text);
    // Company permit lists and financial issuance are not general customs rules.
    if(preg_match('/izin belgeleri(?:nin)?\b.*\blistesi\b|(?:elektronik para|pay|tahvil) ihraci/',$norm))return null;

    // 1. Kara liste kontrolü: Dış ticaret dışı iç mevzuat ise kesinlikle REDDET
    $explicitTrade=(bool)preg_match('/ithalat|ihracat|gumruk|dahilde isleme|haricte isleme|tarife kontenjani|haksiz rekabetin onlenmesi/',$norm);
    foreach ($blacklist as $bad_pattern) {
        if (!$explicitTrade && strpos($norm, rg_normalize_tr($bad_pattern)) !== false) {
            return null;
        }
    }

    // 2. Tehlike / Kritik Önlemler (Damping, Korunma, Ek Mali Yükümlülük, İhracat Yasağı)
    foreach ($kw_critical as $kw) {
        if (strpos($norm, rg_normalize_tr($kw)) !== false) {
            return 'danger';
        }
    }

    // 3. Uyarı & Gözetim (Gözetim, Kontenjan, İGV, Kayda Bağlı İhracat, Menşe)
    foreach ($kw_warning as $kw) {
        if (strpos($norm, rg_normalize_tr($kw)) !== false) {
            return 'warning';
        }
    }

    // 4. Bilgilendirme (Gümrük Rejimleri, TAREKS, Serbest Bölgeler, DFİF)
    foreach ($kw_info as $kw) {
        if (strpos($norm, rg_normalize_tr($kw)) !== false) {
            return 'info';
        }
    }

    // Pozitif dış ticaret listesinde yer almıyorsa REDDET
    return null;
}
}

