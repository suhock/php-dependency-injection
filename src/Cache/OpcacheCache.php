<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Cache;

use InvalidArgumentException;
use Override;
use RuntimeException;
use UnitEnum;

use function chmod;
use function file_put_contents;
use function function_exists;
use function get_debug_type;
use function is_array;
use function is_dir;
use function is_file;
use function is_object;
use function is_scalar;
use function is_writable;
use function method_exists;
use function mkdir;
use function opcache_invalidate;
use function preg_replace;
use function rename;
use function tempnam;
use function unlink;
use function var_export;

/**
 * A {@see CacheInterface} that stores each entry as a PHP file loaded with <code>include</code>, so OPcache serves it
 * from shared memory as already-compiled opcodes with no serialization step. Without OPcache enabled the file is
 * compiled on every read, which costs about as much as compiling the graph, so {@see ApcuCache} is the better choice
 * there. The directory must be owned by the application, since anything written there is executed as code. It grows
 * by one file per distinct build fingerprint and should be cleared on deploy.
 */
final class OpcacheCache implements CacheInterface
{
    /**
     * @param string $directory The directory holding the cache files, created if it does not already exist
     *
     * @throws RuntimeException If the directory does not exist and could not be created, or is not writable
     */
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException("Cache directory \"$directory\" must exist and be writable");
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tryGet(string $id, mixed &$value): bool
    {
        $file = $this->pathFor($id);

        if (!is_file($file)) {
            return false;
        }

        // The closure keeps the included file from seeing or writing this method's variables.
        $value = (static fn(): mixed => include $file)();

        return true;
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidArgumentException If the value cannot be rendered as PHP source
     * @throws RuntimeException If the entry could not be written
     */
    #[Override]
    public function set(string $id, mixed $value): void
    {
        $this->assertExportable($value, $id);

        $file = $this->pathFor($id);
        $tempFile = tempnam($this->directory, 'cache');

        if (
            $tempFile === false
            || file_put_contents($tempFile, "<?php\n\nreturn " . var_export($value, true) . ";\n") === false
        ) {
            throw new RuntimeException("Failed to write cache entry \"$id\" in \"$this->directory\"");
        }

        // tempnam() creates the file readable only by its owner; the pool's web user must be able to include it.
        // Since it is executable, only the owner should be able to write to it.
        chmod($tempFile, 0644);

        // The rename is atomic, so a concurrent reader sees either the previous entry or the complete new one.
        if (!rename($tempFile, $file)) {
            unlink($tempFile);

            throw new RuntimeException("Failed to move cache entry \"$id\" into place at \"$file\"");
        }

        // A pool running with opcache.validate_timestamps=0 would otherwise keep serving the compiled previous entry.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    private function pathFor(string $id): string
    {
        return $this->directory . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $id) . '.php';
    }

    /**
     * @throws InvalidArgumentException If the value, or anything nested in it, is not something
     *     {@see var_export()} can render in a form PHP can read back
     */
    private function assertExportable(mixed $value, string $id): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertExportable($item, $id);
            }

            return;
        }

        if ($value instanceof UnitEnum || (is_object($value) && method_exists($value, '__set_state'))) {
            return;
        }

        throw new InvalidArgumentException(
            'Cannot cache a value of type ' . get_debug_type($value) . " under id \"$id\": " . self::class
            . ' stores entries as PHP source, so objects must implement __set_state() and resources and closures'
            . ' cannot be stored at all',
        );
    }
}
