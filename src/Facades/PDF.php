<?php

namespace AgnosticPDF\Facades;

use AgnosticPDF\Services\PDFManagerService;
use Illuminate\Support\Facades\Facade;

class PDF extends Facade
{
  protected static function getFacadeAccessor()
  {
    return PDFManagerService::class;
  }
}
