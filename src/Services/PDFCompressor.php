<?php

namespace AgnosticPDF\Services;

use AgnosticPDF\Exceptions\PDFCompressException;
use AgnosticPDF\Traits\UsesTempFiles;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PDFCompressor
{
  use UsesTempFiles;

  public function reduce(string $inputPath): string
  {
    $temp     = $this->createTempFile('saida_fix_', '.pdf');
    $final    = $this->createTempFile('saida_fix_final_', '.pdf');
    $fontPath = '-sFONTPATH=/usr/share/fonts/**';

    try {
      // Primeiro passo: pdftocairo
      $process1 = new Process(['pdftocairo', '-pdf', $inputPath, $temp]);
      $process1->mustRun();

      // Segundo passo: ghostscript
      $cmd = [
        'gs',
        '-dEmbedAllFonts=true',
        $fontPath,
        '-sDEVICE=pdfwrite',
        '-dCompatibilityLevel=1.4',
        '-dQUIET',
        '-o',
        $final,
        $temp
      ];
      $process2 = new Process($cmd);
      $process2->mustRun();

      // Limpamos o temp intermediário
      @unlink($temp);

      return $final;
    } catch (ProcessFailedException $e) {
      $this->cleanupTempFiles();

      throw new PDFCompressException("Erro na compactação do PDF", [
        'error'   => $e->getMessage(),
        'command' => $e->getProcess()->getCommandLine(),
        'output'  => $e->getProcess()->getErrorOutput(),
        'path'    => $inputPath,
      ], $e->getCode(), $e);
    }
  }
}
