<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

use RuntimeException;

final class SecureTemporaryFiles
{
  /** @var list<string> */
  private array $paths = [];

  public function write(string $contents, string $prefix): string
  {
    $path = tempnam(sys_get_temp_dir(), $prefix);

    if ($path === false) {
      throw new RuntimeException('Unable to allocate a secure temporary file.');
    }

    $this->paths[] = $path;
    @chmod($path, 0600);

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
      throw new RuntimeException('Unable to write a secure temporary file.');
    }

    return $path;
  }

  public function empty(string $prefix): string
  {
    return $this->write('', $prefix);
  }

  public function cleanup(): void
  {
    foreach ($this->paths as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }

    $this->paths = [];
  }

  public function __destruct()
  {
    $this->cleanup();
  }
}
