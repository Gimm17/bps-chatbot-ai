<?php

namespace App\Bps;

/**
 * DTO citation path BPS resmi — selalu verified:true.
 * url dari domain_url (field BPS) atau pdf field (view/publication).
 */
final class BpsCitation
{
    public function __construct(
        public readonly string $sourceId,
        public readonly string $title,
        public readonly ?string $url,
        public readonly ?string $snippet,
        public readonly ?string $domain = null,
        public readonly ?string $period = null,
        public readonly bool $verified = true,
    ) {}
}
