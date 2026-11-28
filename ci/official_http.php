<?php
/** Keep stored source identities stable while using the working official origin.
 * Both names belong to Resmî Gazete. Never redirect to an unverified mirror or
 * disable TLS validation. Callers still validate the date/document content.
 */
function tf_official_transport_url(string $url): string {
    $prefix='https://www.resmigazete.gov.tr/';
    return str_starts_with($url,$prefix)?'https://resmigazete.gov.tr/'.substr($url,strlen($prefix)):$url;
}
