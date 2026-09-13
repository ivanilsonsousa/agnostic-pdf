<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

use AgnosticPDF\Exceptions\PdfSignatureException;
use AgnosticPDF\Signatures\SignatureAppearance;
use DateTimeImmutable;
use Papier\Font\Encoding\WinAnsiEncoding;
use Papier\Graphics\Image\JpegImage;
use Papier\Graphics\Image\PngImage;
use Papier\Objects\PdfArray;
use Papier\Objects\PdfDictionary;
use Papier\Objects\PdfIndirectReference;
use Papier\Objects\PdfInteger;
use Papier\Objects\PdfName;
use Papier\Objects\PdfNull;
use Papier\Objects\PdfRaw;
use Papier\Objects\PdfReal;
use Papier\Objects\PdfStream;
use Papier\Objects\PdfString;
use Papier\Parser\ImportedPage;
use Papier\Parser\PdfParser;
use Papier\Writer\IncrementalUpdater;

final class IncrementalSignatureWriter
{
  /**
   * @param callable(string): string $produceCms
   */
  public function append(
    PdfParser $parser,
    string $signatureDictionary,
    int $capacity,
    callable $produceCms,
    ?SignatureAppearance $appearance,
    string $fieldName,
    ?string $author,
    DateTimeImmutable $modifiedAt,
  ): string {
    $catalogNumber = $parser->getXref()->getCatalogObjectNumber();
    $catalog       = $catalogNumber === null ? null : $parser->resolveObject($catalogNumber);
    $pages         = $parser->getPages();

    if (!$catalog instanceof PdfDictionary) {
      throw new PdfSignatureException('Cannot sign a PDF without a document catalog.');
    }

    if ($pages === []) {
      throw new PdfSignatureException('Cannot sign a PDF without pages.');
    }

    $pageNumber = $appearance?->pageNumber() ?? 1;
    $pageIndex  = $pageNumber === -1 ? count($pages) - 1 : $pageNumber - 1;

    if (!isset($pages[$pageIndex])) {
      throw new PdfSignatureException("Signature page {$pageNumber} does not exist.");
    }

    $targetPage       = $pages[$pageIndex];
    $targetPageNumber = $targetPage->getObjectNumber();

    if ($targetPageNumber === null) {
      throw new PdfSignatureException('The signature target page is not an indirect PDF object.');
    }

    $updater         = new IncrementalUpdater($parser);
    $signatureNumber = $updater->addObject(new PdfRaw($signatureDictionary));

    $appearanceNumber = $appearance === null
      ? null
      : $this->buildAppearance($updater, $appearance);
    $parentFieldNumber = null;

    if ($author !== null) {
      $parentFieldNumber = $updater->peekNextObjectNumber();
      $parentField       = new PdfDictionary();
      $parentField->set('T', PdfString::text($fieldName));
      $kids = new PdfArray();
      $kids->add(new PdfIndirectReference($parentFieldNumber + 1));
      $parentField->set('Kids', $kids);
      $updater->addObject($parentField);
    }

    $widget = new PdfDictionary();
    $widget->set('Type', new PdfName('Annot'));
    $widget->set('Subtype', new PdfName('Widget'));
    $widget->set('FT', new PdfName('Sig'));
    $widget->set('T', PdfString::text($author ?? $fieldName));
    $widget->set('V', new PdfIndirectReference($signatureNumber));
    $widget->set('P', new PdfIndirectReference($targetPageNumber));
    $widget->set('NM', PdfString::text($fieldName));

    if ($parentFieldNumber !== null) {
      $widget->set('Parent', new PdfIndirectReference($parentFieldNumber));
    }
    $widget->set('M', PdfString::literal(
      'D:' . $modifiedAt->setTimezone(new \DateTimeZone('UTC'))->format('YmdHis') . 'Z',
    ));

    if ($author !== null) {
      // /Name in the signature dictionary remains the signed identity. These
      // entries describe the widget to annotation-centric readers such as PDFKit.
      $widget->set('TU', PdfString::text($author));
      $widget->set('Subj', PdfString::text('Assinatura digital'));
      $widget->set('Contents', PdfString::text('Documento assinado digitalmente por ' . $author));
    }

    if ($appearance === null) {
      $widget->set('Rect', $this->rectangle([0.0, 0.0, 0.0, 0.0]));
      $widget->set('F', new PdfInteger(132));
    } else {
      [$x, $y, $width, $height] = $appearance->rectangle();
      $widget->set('Rect', $this->rectangle([$x, $y, $x + $width, $y + $height]));
      $widget->set('F', new PdfInteger(4));

      if ($appearanceNumber === null) {
        throw new PdfSignatureException('Unable to build the signature appearance.');
      }

      $normalAppearance = new PdfDictionary();
      $normalAppearance->set('N', new PdfIndirectReference($appearanceNumber));
      $widget->set('AP', $normalAppearance);
    }

    $widgetNumber = $updater->addObject($widget);
    $this->updateAcroForm($parser, $updater, $catalog, $parentFieldNumber ?? $widgetNumber);
    $updater->updateObject($catalogNumber, $catalog);

    $existingAnnotations = $this->resolveArray($parser, $targetPage->get('Annots'));
    $annotations         = new PdfArray();

    foreach ($existingAnnotations?->getItems() ?? [] as $annotation) {
      $annotations->add($annotation);
    }

    $annotations->add(new PdfIndirectReference($widgetNumber));
    $targetPage->set('Annots', $annotations);
    $updater->updateObject($targetPageNumber, $targetPage);

    return $this->patch($updater->build(), $capacity, $produceCms);
  }

  private function updateAcroForm(
    PdfParser $parser,
    IncrementalUpdater $updater,
    PdfDictionary $catalog,
    int $widgetNumber,
  ): void {
    $existing    = $catalog->get('AcroForm');
    $acroForm    = $existing === null ? null : $parser->resolve($existing);
    $newAcroForm = $acroForm instanceof PdfDictionary
      ? new PdfDictionary($acroForm->getEntries())
      : new PdfDictionary();
    $existingFields = $acroForm instanceof PdfDictionary
      ? $this->resolveArray($parser, $acroForm->get('Fields'))
      : null;
    $fields = new PdfArray();

    foreach ($existingFields?->getItems() ?? [] as $field) {
      $fields->add($field);
    }

    $fields->add(new PdfIndirectReference($widgetNumber));
    $newAcroForm->set('Fields', $fields);

    $flags = $acroForm instanceof PdfDictionary
      ? $parser->resolve($acroForm->get('SigFlags') ?? new PdfNull())
      : null;
    $newAcroForm->set('SigFlags', new PdfInteger(
      ($flags instanceof PdfInteger ? $flags->getValue() : 0) | 3,
    ));

    $acroFormNumber = $acroForm instanceof PdfDictionary ? $acroForm->getObjectNumber() : null;

    if ($acroFormNumber === null) {
      $acroFormNumber = $updater->addObject($newAcroForm);
    } else {
      $updater->updateObject($acroFormNumber, $newAcroForm);
    }

    $catalog->set('AcroForm', new PdfIndirectReference($acroFormNumber));
  }

  private function buildAppearance(IncrementalUpdater $updater, SignatureAppearance $appearance): int
  {
    [, , $width, $height] = $appearance->rectangle();
    $resources            = new PdfDictionary();
    $content              = "q\n";
    $imageBytes           = $appearance->imageBytes();
    $pdfBytes             = $appearance->pdfBytes();

    if ($pdfBytes !== null) {
      $parser = new PdfParser($pdfBytes);
      $parser->parse();
      $imported = ImportedPage::fromParser($parser, 1);
      $form     = $imported->getFormXObject();
      $this->promoteNestedStreams($updater, $form->getDictionary());
      $formNumber = $updater->addObject($form);
      $xObjects   = new PdfDictionary();
      $xObjects->set('Seal', new PdfIndirectReference($formNumber));
      $resources->set('XObject', $xObjects);
      $content .= $this->number($width / $imported->getWidth()) . ' 0 0 '
        . $this->number($height / $imported->getHeight()) . " 0 0 cm\n/Seal Do\n";
    } elseif ($imageBytes !== null) {
      $image = str_starts_with($imageBytes, "\x89PNG")
        ? new PngImage($imageBytes)
        : new JpegImage($imageBytes);

      if ($image instanceof PngImage && $image->getSMaskStream() !== null) {
        $maskNumber = $updater->addObject($image->getSMaskStream());
        $image->getStream()->getDictionary()->set('SMask', new PdfIndirectReference($maskNumber));
      }

      $imageNumber = $updater->addObject($image->getStream());
      $xObjects    = new PdfDictionary();
      $xObjects->set('Seal', new PdfIndirectReference($imageNumber));
      $resources->set('XObject', $xObjects);
      $content .= $this->number($width) . ' 0 0 ' . $this->number($height) . " 0 0 cm\n/Seal Do\n";
    } else {
      $content .= '0.55 0.55 0.55 RG 0.6 w 0 0 ' . $this->number($width) . ' ' . $this->number($height) . " re S\n";
    }

    $lines = $appearance->lines();

    if ($lines !== []) {
      $font = new PdfDictionary();
      $font->set('Type', new PdfName('Font'));
      $font->set('Subtype', new PdfName('Type1'));
      $font->set('BaseFont', new PdfName('Helvetica'));
      $font->set('Encoding', new PdfName('WinAnsiEncoding'));
      $fontNumber = $updater->addObject($font);
      $fonts      = new PdfDictionary();
      $fonts->set('Helv', new PdfIndirectReference($fontNumber));
      $resources->set('Font', $fonts);

      $fontSize = max(5.0, min(9.0, ($height - 6.0) / max(1, count($lines)) * 0.65));
      $content .= "BT\n/Helv " . $this->number($fontSize) . " Tf\n0 g\n";
      $cursor = $height - $fontSize - 3.0;

      foreach ($lines as $index => $line) {
        $encoded = WinAnsiEncoding::fromUtf8($line);
        $escaped = strtr($encoded, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => '']);
        $moveY   = $index === 0 ? $cursor : -($fontSize + 2.0);
        $content .= '4 ' . $this->number($moveY) . " Td\n({$escaped}) Tj\n";
      }

      $content .= "ET\n";
    }

    $content .= 'Q';
    $stream = new PdfStream();
    $stream->getDictionary()->set('Type', new PdfName('XObject'));
    $stream->getDictionary()->set('Subtype', new PdfName('Form'));
    $stream->getDictionary()->set('BBox', $this->rectangle([0.0, 0.0, $width, $height]));
    $stream->getDictionary()->set('Resources', $resources);
    $stream->setData($content)->compress();

    return $updater->addObject($stream);
  }

  /**
   * Streams cannot be direct values. Imported resources are self-contained,
   * so promote their nested streams into the incremental revision.
   */
  private function promoteNestedStreams(IncrementalUpdater $updater, PdfDictionary $dictionary): void
  {
    foreach ($dictionary->getEntries() as $key => $value) {
      if ($value instanceof PdfStream) {
        $this->promoteNestedStreams($updater, $value->getDictionary());
        $dictionary->set($key, new PdfIndirectReference($updater->addObject($value)));
      } elseif ($value instanceof PdfDictionary) {
        $this->promoteNestedStreams($updater, $value);
      } elseif ($value instanceof PdfArray) {
        $this->promoteNestedStreamsInArray($updater, $value);
      }
    }
  }

  private function promoteNestedStreamsInArray(IncrementalUpdater $updater, PdfArray $array): void
  {
    foreach ($array->getItems() as $index => $value) {
      if ($value instanceof PdfStream) {
        $this->promoteNestedStreams($updater, $value->getDictionary());
        $array->set($index, new PdfIndirectReference($updater->addObject($value)));
      } elseif ($value instanceof PdfDictionary) {
        $this->promoteNestedStreams($updater, $value);
      } elseif ($value instanceof PdfArray) {
        $this->promoteNestedStreamsInArray($updater, $value);
      }
    }
  }

  /**
   * @param array{float, float, float, float} $values
   */
  private function rectangle(array $values): PdfArray
  {
    $rectangle = new PdfArray();

    foreach ($values as $value) {
      $rectangle->add(new PdfReal($value));
    }

    return $rectangle;
  }

  /**
   * @param callable(string): string $produceCms
   */
  private function patch(string $pdf, int $capacity, callable $produceCms): string
  {
    $contentsMarker   = '/Contents <';
    $contentsPosition = strrpos($pdf, $contentsMarker);

    if ($contentsPosition === false) {
      throw new PdfSignatureException('The signature placeholder was not written to the PDF.');
    }

    $openingBracket      = $contentsPosition + strlen($contentsMarker) - 1;
    $closingBracket      = $openingBracket   + 1 + ($capacity * 2);
    $secondRangeStart    = $closingBracket   + 1;
    $secondRangeLength   = strlen($pdf) - $secondRangeStart;
    $placeholder         = '/ByteRange [0000000000 0000000000 0000000000 0000000000]';
    $placeholderPosition = strrpos($pdf, $placeholder);

    if ($placeholderPosition === false) {
      throw new PdfSignatureException('The ByteRange placeholder was not written to the PDF.');
    }

    foreach ([$openingBracket, $secondRangeStart, $secondRangeLength] as $number) {
      if ($number > 9999999999) {
        throw new PdfSignatureException('The PDF is too large for the configured ByteRange placeholder.');
      }
    }

    $byteRange = sprintf(
      '/ByteRange [%010d %010d %010d %010d]',
      0,
      $openingBracket,
      $secondRangeStart,
      $secondRangeLength,
    );
    $pdf           = substr_replace($pdf, $byteRange, $placeholderPosition, strlen($placeholder));
    $signedContent = substr($pdf, 0, $openingBracket) . substr($pdf, $secondRangeStart);
    $cms           = $produceCms($signedContent);
    $hex           = bin2hex($cms);

    if (strlen($hex) > $capacity * 2) {
      throw new PdfSignatureException('The CMS signature exceeds the reserved PDF capacity.');
    }

    $hex = str_pad($hex, $capacity * 2, '0');

    return substr_replace($pdf, $hex, $openingBracket + 1, $capacity * 2);
  }

  private function resolveArray(PdfParser $parser, mixed $object): ?PdfArray
  {
    if (!$object instanceof \Papier\Objects\PdfObject) {
      return null;
    }

    $resolved = $parser->resolve($object);

    return $resolved instanceof PdfArray ? $resolved : null;
  }

  private function number(float $value): string
  {
    return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
  }
}
