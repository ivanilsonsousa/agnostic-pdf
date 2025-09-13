<?php

namespace AgnosticPDF\Contracts;

interface PDFClonerDriverInterface
{
  /**
   * Deve preparar o driver para clonar a partir de um arquivo.
   * @return int número de páginas
   */
  public function prepareClone(string $pathFile): int;

  /**
   * Deve clonar uma página específica.
   */
  public function clonePage(int $pageNo): void;
}
