<?php

namespace AgnosticPDF\Contracts;

interface PDFServiceInterface
{
  public function loadHtml(string $html): self;

  public function loadView(string $view, array $data = []): self;

  public function output(): string;

  public function download(string $filename): void;

  public function save(string $path): void;

  public function stream(string $filename): void;

  public function streamResponse(string $filename): \Illuminate\Http\Response;
}
