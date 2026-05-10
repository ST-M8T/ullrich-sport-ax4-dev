<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface DhlAuthenticationGateway
{
    /**
     * @return array{access_token?:string,token_type?:string,expires_in?:int}
     */
    public function getToken(string $responseType = 'access_token'): array;
}
