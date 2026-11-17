<?php

namespace App\Support\Directory;

/**
 * An account the directory confirmed: password right, not disabled there.
 * Only what DokuVault needs - who it is and which groups it is in.
 */
final class DirectoryUser
{
    /**
     * @param  string  $guid  objectGUID as hex - stays when the account is renamed
     * @param  string  $username  sAMAccountName
     * @param  array<int, array{dn: string, name: ?string, cn: ?string}>  $groups  every group, nested ones included
     */
    public function __construct(
        public readonly string $guid,
        public readonly string $username,
        public readonly ?string $upn,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly array $groups,
    ) {}
}
