<?php

declare(strict_types=1);

/**
 * HTML Purifier configuration for user-supplied rich text (question bodies, explanations).
 *
 * Restrictive allow-list: basic formatting, lists and code only.
 * No `style` attributes, no inline CSS, no iframes/embeds/media, no IDs,
 * and links limited to http/https/mailto (so `javascript:` and `data:` URIs are stripped).
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => implode(',', [
                'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
                'ul', 'ol', 'li',
                'blockquote', 'code', 'pre',
                'a[href|title]',
            ]),
            'CSS.AllowedProperties' => '',
            'HTML.SafeIframe' => false,
            'HTML.SafeEmbed' => false,
            'HTML.SafeObject' => false,
            'HTML.Trusted' => false,
            'Attr.EnableID' => false,
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ],
            'URI.DisableExternalResources' => true,
            'HTML.Nofollow' => true,
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],
    ],
];
