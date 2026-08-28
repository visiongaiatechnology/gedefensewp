<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
function wp_strip_all_tags(string $value, bool $remove_breaks=false): string { $value=strip_tags($value);return $remove_breaks?(preg_replace('/[\r\n\t ]+/',' ',$value)??''):$value; }
require dirname(__DIR__) . '/includes/VisionGaiaSEO/includes/class-vg-seo-relevance.php';
$document=['canonical_title'=>'WordPress Firewall für Agenturen','slug'=>'wordpress-firewall-agenturen','site_name'=>'VisionGaia','headings'=>['Angriffe auf WordPress blockieren','WAF ohne Abhängigkeiten'],'content'=>'Diese Seite beschreibt eine WordPress Firewall und WAF-Schutz für Agenturen.'];
$wrong=VG_SEO_Relevance::enforce(['seo_title'=>'Luxusreisen und Hotels auf Mallorca','focus_keyword'=>'Mallorca Hotels'],$document);
if($wrong['seo_title']!=='WordPress Firewall für Agenturen | VisionGaia'||str_contains($wrong['focus_keyword'],'mallorca')){fwrite(STDERR,"VGT SEO RELEVANCE: FAILED unrelated fallback\n");exit(1);}
$right=VG_SEO_Relevance::enforce(['seo_title'=>'WordPress Firewall: WAF für Agenturen','focus_keyword'=>'WordPress Firewall'],$document);
if($right['seo_title']!=='WordPress Firewall: WAF für Agenturen'||$right['focus_keyword']!=='WordPress Firewall'){fwrite(STDERR,"VGT SEO RELEVANCE: FAILED relevant title\n");exit(1);}
echo "VGT SEO RELEVANCE: PASS\n";
