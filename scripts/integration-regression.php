<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

$root=dirname(__DIR__);$fail=[];
$contains=static function(string $file,string $needle)use($root):bool{$data=file_get_contents($root.'/'.$file);return is_string($data)&&str_contains($data,$needle);};
$absent=static function(string $file,string $needle)use($contains,&$fail):void{if($contains($file,$needle))$fail[]="$file contains forbidden $needle";};
$present=static function(string $file,string $needle)use($contains,&$fail):void{if(!$contains($file,$needle))$fail[]="$file missing $needle";};

$present('class-vis-bootstrapper.php','VIS_Module_Registry::all()');
$present('class-vis-bootstrapper.php','VIS_Integration_Bus::mount()');

if (is_file($root . '/includes/builder/inc/class-vgt-admin.php')) {
    $present('includes/builder/inc/class-vgt-admin.php','vgt.builder.published');
    $present('includes/builder/inc/class-vgt-admin.php','_vgt_asset_manifest');
}
if (is_file($root . '/includes/VLP/includes/modules/privacy-shield/class-vlp-gatekeeper.php')) {
    $present('includes/VLP/includes/modules/privacy-shield/class-vlp-gatekeeper.php','_vgt_asset_manifest');
}
if (is_file($root . '/includes/VisionGaiaSEO/includes/class-vg-api-service.php')) {
    $present('includes/VisionGaiaSEO/includes/class-vg-api-service.php','VG_SEO_Relevance::enforce');
    $present('includes/VisionGaiaSEO/includes/class-vg-geo-injector.php','JSON_HEX_TAG');
    $present('includes/VisionGaiaSEO/class-vg-seo-bootstrapper.php',"'[IDENTITÄT_NAME]'");
    $absent('includes/VisionGaiaSEO/includes/class-vg-api-service.php',"wp_remote_post('https://api.groq.com");
}
if (is_file($root . '/includes/VLP/vision-legal-pro.php')) {
    $absent('includes/VLP/vision-legal-pro.php',"['VLP_Lingua_Groq', 'ajax_process']");
}
if (is_file($root . '/includes/builder/inc/class-vgt-ajax.php')) {
    $absent('includes/builder/inc/class-vgt-ajax.php',"wp_remote_post('https://api.groq.com");
}

$gateway=file_get_contents($root.'/includes/core/class-vis-ai-gateway.php');
if(!is_string($gateway)||!str_contains($gateway,"'redirection'=>0")||!str_contains($gateway,"'sslverify'=>true")||!str_contains($gateway,"'limit_response_size'=>524288"))$fail[]='AI gateway transport policy incomplete';

if($fail!==[]){fwrite(STDERR,"VGT INTEGRATION REGRESSION: FAILED\n".implode("\n",$fail)."\n");exit(1);}
echo "VGT INTEGRATION REGRESSION: PASS\n";
