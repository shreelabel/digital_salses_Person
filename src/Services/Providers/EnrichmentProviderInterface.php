<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

/**
 * Providers that enrich verified companies with people / emails
 * (Apollo for people, Hunter for email find/verify). No fabrication.
 */
interface EnrichmentProviderInterface
{
    public function slug(): string;

    public function isConfigured(): bool;

    /**
     * Find decision-maker people for a domain.
     * @return array{ok:bool,people?:array,error?:string}
     */
    public function findPeople(string $domain, array $titles, ProviderContext $ctx): array;

    /**
     * Find a verified email for a person (Hunter Email Finder).
     * Only used when an email is missing.
     * @return array{ok:bool,email?:?string,score?:?int,error?:string}
     */
    public function findEmail(string $domain, ?string $firstName, ?string $lastName, ?string $fullName, ProviderContext $ctx): array;

    public function ping(ProviderContext $ctx): array;
}
