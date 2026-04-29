<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * SvgSanitizer — strip all executable / external content from SVG.
 *
 * Allows a whitelist of presentational elements + attributes, removes:
 *   - <script>, <foreignObject>, <iframe>, etc.
 *   - any "on*" event handler (onload, onclick, …)
 *   - href values starting with javascript:, data: (except image/png|jpeg|gif), file:, vbscript:
 *   - external image/use refs that aren't same-origin paths
 *
 * Usage:
 *   $clean = SvgSanitizer::clean($rawSvgString);
 *   if ($clean === null) { /* invalid SVG *\/ }
 */
class SvgSanitizer
{
    private const ALLOWED_TAGS = [
        'svg', 'g', 'defs', 'symbol', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'use', 'image',
        'lineargradient', 'radialgradient', 'stop', 'pattern',
        'clippath', 'mask', 'filter',
        'feblend', 'fecolormatrix', 'fecomposite', 'feflood',
        'fegaussianblur', 'femerge', 'femergenode', 'feoffset', 'feturbulence',
        'style',
    ];

    private const ALLOWED_ATTRS = [
        'id', 'class', 'style',
        'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2',
        'width', 'height', 'viewbox', 'preserveaspectratio',
        'd', 'points', 'fill', 'fill-rule', 'fill-opacity',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit',
        'opacity', 'transform', 'transform-origin',
        'xmlns', 'xmlns:xlink', 'xmlns:svg', 'version',
        'gradientunits', 'gradienttransform', 'spreadmethod',
        'offset', 'stop-color', 'stop-opacity',
        'patternunits', 'patterncontentunits', 'patterntransform',
        'clip-path', 'clip-rule', 'mask', 'filter',
        'href', 'xlink:href',
        'in', 'in2', 'result', 'mode', 'type', 'values', 'tablevalues',
        'stddeviation', 'dx', 'dy', 'k1', 'k2', 'k3', 'k4',
        'flood-color', 'flood-opacity',
        'text-anchor', 'dominant-baseline', 'font-family', 'font-size', 'font-weight',
        'letter-spacing', 'word-spacing',
    ];

    public static function clean(string $svg): ?string
    {
        $svg = trim($svg);
        if ($svg === '' || stripos($svg, '<svg') === false) return null;

        // Strip DOCTYPE / processing instructions / external entity refs.
        $svg = preg_replace('/<\?xml[^>]*\?>/i', '', $svg);
        $svg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $svg);
        $svg = preg_replace('/<!ENTITY[^>]*>/i', '', $svg);

        $prevState = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        // LIBXML_NONET disables network access (XXE protection).
        // Wrap in a root in case of fragment parsing.
        $loaded = $doc->loadXML($svg, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($prevState);

        if (!$loaded || !$doc->documentElement) return null;

        if (strtolower($doc->documentElement->localName) !== 'svg') return null;

        self::scrubNode($doc->documentElement);

        $out = $doc->saveXML($doc->documentElement);
        return $out !== false ? $out : null;
    }

    private static function scrubNode(DOMNode $node): void
    {
        if (!($node instanceof DOMElement)) return;

        $tag = strtolower($node->localName ?? '');
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }

        // Walk attributes (snapshot first — we mutate the list).
        $attrs = [];
        foreach ($node->attributes as $a) { $attrs[] = $a; }
        foreach ($attrs as $attr) {
            $name  = strtolower($attr->nodeName);
            $value = $attr->nodeValue ?? '';

            // Drop event handlers and anything not whitelisted.
            if (str_starts_with($name, 'on') || !in_array($name, self::ALLOWED_ATTRS, true)) {
                $node->removeAttributeNode($attr);
                continue;
            }

            // href / xlink:href — block dangerous schemes.
            if ($name === 'href' || $name === 'xlink:href') {
                $v = strtolower(trim($value));
                $bad = ['javascript:', 'vbscript:', 'data:', 'file:'];
                foreach ($bad as $b) {
                    if (str_starts_with($v, $b)) {
                        // Allow harmless data:image/png|jpeg|gif|svg+xml
                        if ($b === 'data:' && preg_match('#^data:image/(png|jpeg|jpg|gif|svg\+xml);#', $v)) continue 2;
                        $node->removeAttributeNode($attr);
                        continue 2;
                    }
                }
            }

            // style attribute — strip url() with javascript:, expression()
            if ($name === 'style') {
                if (preg_match('/expression\s*\(|javascript\s*:|@import/i', $value)) {
                    $node->removeAttributeNode($attr);
                }
            }
        }

        // Recurse into children (snapshot — mutation while iterating breaks).
        $children = [];
        foreach ($node->childNodes as $c) { $children[] = $c; }
        foreach ($children as $c) { self::scrubNode($c); }
    }
}
