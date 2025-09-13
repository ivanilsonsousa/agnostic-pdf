<?php

return [
  /*
    |--------------------------------------------------------------------------
    | PDF Driver
    |--------------------------------------------------------------------------
    |
    | Drivers disponíveis: 'mpdf', 'dompdf'
    |
    */
  'driver' => env('PDF_DRIVER', 'mpdf'),

  /*
    |--------------------------------------------------------------------------
    | MPDF Configuration
    |--------------------------------------------------------------------------
    */
  'mpdf' => [
    'mode'          => 'UTF-8',
    'format'        => 'A4',
    'orientation'   => 'P',
    'margin_left'   => 15,
    'margin_right'  => 15,
    'margin_top'    => 16,
    'margin_bottom' => 16,
    'margin_header' => 9,
    'margin_footer' => 9,
    'tempDir'       => storage_path('app/pdf/temp'),
  ],

  /*
    |--------------------------------------------------------------------------
    | DomPDF Configuration
    |--------------------------------------------------------------------------
    */
  'dompdf' => [
    'paper'           => 'A4',
    'orientation'     => 'portrait',
    'isRemoteEnabled' => true,
    'isPhpEnabled'    => false,
    'chroot'          => public_path(),
  ],
];
