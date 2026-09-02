<?php

namespace KIC\Importer\Contract;

final class KicContract
{
    public const VERSION = 'KIC-1.0';
    public const TARGET = 'wp-kadence-importer';
    public const FINGERPRINT = 'cb45bc42bdeb60120e1ebf00168a427d4420520d578725077c1655434f83f63f';

    public const REQUIRED_FILES = array(
        'index.html',
        'site-manifest.json',
        'assets/css/global.css',
        'assets/css/components.css',
        'assets/css/responsive.css',
        'assets/js/main.js',
    );

    public const REQUIRED_DIRECTORIES = array(
        'pages',
        'assets/images',
        'assets/icons',
        'assets/fonts',
    );

    public const COMPONENTS = array(
        'site-header', 'site-footer', 'breadcrumb',
        'hero', 'content-section', 'image-text', 'services-grid',
        'features-grid', 'stats', 'testimonials', 'faq', 'cta',
        'team-grid', 'gallery', 'pricing-grid', 'logo-cloud',
        'contact-section', 'tabs', 'embed-section',
        'service-card', 'feature-card', 'stat-item', 'testimonial-card',
        'faq-item', 'team-card', 'pricing-card', 'logo-item', 'tab-item',
        'info-box', 'contact-form', 'icon-list', 'button-group', 'embed',
    );

    public const MAX_ARCHIVE_BYTES = 104857600;
    public const MAX_FILES = 2000;
    public const MAX_UNCOMPRESSED_BYTES = 524288000;

    private function __construct()
    {
    }
}
