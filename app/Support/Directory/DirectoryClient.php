<?php

namespace App\Support\Directory;

/**
 * Talks to the directory. One real implementation (PhpLdapClient); tests
 * bind a fake in its place - there is no domain controller in CI.
 */
interface DirectoryClient
{
    /**
     * The account behind this login with this password - or null when it
     * does not exist, the password is wrong or the account is disabled in
     * the directory. Those three look the same on purpose.
     *
     * $login: "name", "DOMAIN\name" or "name@domain".
     *
     * @throws DirectoryUnavailable when the directory cannot be asked at all
     */
    public function authenticate(string $login, #[\SensitiveParameter] string $password): ?DirectoryUser;

    /**
     * The enabled account with this objectGUID (hex) and its groups - or
     * null when it was deleted or disabled. No password: the hourly sync
     * asks this with the service account.
     *
     * @throws DirectoryUnavailable
     */
    public function find(string $guid): ?DirectoryUser;

    /**
     * Every group below the base DN, sorted by name - for picking one in
     * "Gruppe -> Rolle" instead of typing its name.
     *
     * @return array<int, array{dn: string, name: string}>
     *
     * @throws DirectoryUnavailable
     */
    public function groups(): array;

    /**
     * Connects with the service account and reads the base DN. Returns a
     * short confirmation.
     *
     * @throws DirectoryUnavailable with a reason a person can act on
     */
    public function test(): string;
}
