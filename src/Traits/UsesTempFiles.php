<?php

namespace AgnosticPDF\Traits;

trait UsesTempFiles
{
  /**
   * Lista de arquivos temporários criados.
   * @var array<string>
   */
  private array $tempFiles = [];

  /**
   * Cria um arquivo temporário e registra para cleanup.
   *
   * @return string caminho completo do arquivo temp
   */
  protected function createTempFile(string $prefix = 'tmp_', string $suffix = ''): string
  {
    $tmpDir   = sys_get_temp_dir();
    $filePath = tempnam($tmpDir, $prefix);

    // Se precisar, renomeia para ter o sufixo desejado (ex: .pdf)
    if ($suffix !== '') {
      $newPath = $filePath . $suffix;
      rename($filePath, $newPath);
      $filePath = $newPath;
    }

    $this->tempFiles[] = $filePath;

    return $filePath;
  }

  /**
   * Remove todos os arquivos temporários criados.
   */
  protected function cleanupTempFiles(): void
  {
    foreach ($this->tempFiles as $file) {
      if (file_exists($file)) {
        @unlink($file);
      }
    }

    // limpa a lista
    $this->tempFiles = [];
  }
}
