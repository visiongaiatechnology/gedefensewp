<?php
// STATUS: DIAMANT VGT SUPREME
declare(strict_types=1);

if (!defined('ABSPATH')) exit('VGT_ACCESS_DENIED');

final class VIS_Integration_Bus {
    public const SCHEMA = 1;
    private const TYPES = [
        'vgt.content.saved','vgt.builder.published','vgt.builder.asset_manifest_changed',
        'vgt.vlp.dictionary_compiled','vgt.vlp.consent_policy_changed',
        'vgt.seo.metadata_generated','vgt.seo.schema_invalidated',
    ];
    private static bool $mounted = false;

    public static function mount(): void {
        if (self::$mounted) return;
        self::$mounted = true;
        add_action('vgt.integration.vgt.builder.published', [self::class, 'onBuilderPublished'], 10, 1);
    }

    public static function fingerprint(int $postId): string {
        $post = get_post($postId);
        if (!$post instanceof WP_Post) return '';
        $payload = implode("\0", [
            (string)$post->post_title, (string)$post->post_content,
            (string)get_post_meta($postId, '_vgt_html', true),
            (string)get_post_meta($postId, '_vgt_css', true),
        ]);
        return hash('sha256', $payload);
    }

    public static function emit(string $type, string $source, int $objectId, array $context = []): string {
        if (!in_array($type, self::TYPES, true)) throw new InvalidArgumentException('Integration event type validation failed.');
        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $source) !== 1 || $objectId < 0) throw new InvalidArgumentException('Integration event origin validation failed.');
        $id = bin2hex(random_bytes(16));
        $event = ['id'=>$id,'schema'=>self::SCHEMA,'type'=>$type,'source'=>$source,'object_id'=>$objectId,'timestamp'=>time(),'context'=>array_slice($context,0,20,true)];
        do_action('vgt.integration.' . $type, $event);
        if (class_exists('VIS_Event_Bus')) VIS_Event_Bus::emit($source, 'INTEGRATION', $type, ['event_id'=>$id,'object_id'=>$objectId], 2);
        return $id;
    }

    public static function onBuilderPublished(array $event): void {
        $postId = (int)($event['object_id'] ?? 0);
        if ($postId <= 0) return;
        delete_post_meta($postId, '_vgt_seo_content_fingerprint');
        delete_post_meta($postId, '_vgt_seo_source_fingerprint');
        self::purgeLingua($postId);
        self::emit('vgt.seo.schema_invalidated', 'builder', $postId, ['reason'=>'builder_publish']);
    }

    private static function purgeLingua(int $postId): void {
        $upload = wp_upload_dir(null, false);
        $dir = rtrim((string)($upload['basedir'] ?? ''), '/\\') . '/.vgt-keys/lingua';
        foreach (glob($dir . '/post_' . $postId . '_*.json') ?: [] as $file) {
            $realDir = realpath($dir); $realFile = realpath($file);
            if ($realDir !== false && $realFile !== false && str_starts_with(wp_normalize_path($realFile), rtrim(wp_normalize_path($realDir), '/') . '/')) {
                if (preg_match('/^post_' . $postId . '_([a-z]{2}(?:-[A-Z]{2})?)\.json$/D', basename($realFile), $match) === 1) delete_transient('vgt_lingua_post_' . $postId . '_' . $match[1]);
                @unlink($realFile);
            }
        }
    }
}
