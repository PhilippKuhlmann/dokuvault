<?php

namespace App\Support\Directory;

use RuntimeException;

/**
 * The directory could not be asked: not reachable, certificate refused,
 * service account rejected. Not "wrong password" - that is a null from
 * DirectoryClient::authenticate().
 */
class DirectoryUnavailable extends RuntimeException {}
