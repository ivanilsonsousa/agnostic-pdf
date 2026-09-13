<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

use Papier\Objects\PdfArray;
use Papier\Objects\PdfDictionary;
use Papier\Objects\PdfInteger;
use Papier\Objects\PdfName;
use Papier\Objects\PdfNull;
use Papier\Objects\PdfObject;
use Papier\Objects\PdfReal;
use Papier\Objects\PdfString;
use Papier\Parser\PdfParser;

final class PdfSignatureLocator
{
  /** @return list<PdfSignatureRecord> */
  public function locate(PdfParser $parser): array
  {
    $catalogNumber = $parser->getXref()->getCatalogObjectNumber();
    $catalog       = $catalogNumber === null ? null : $parser->resolveObject($catalogNumber);

    if (!$catalog instanceof PdfDictionary) {
      return [];
    }

    $acroForm = $this->resolveDictionary($parser, $catalog->get('AcroForm'));
    $fields   = $acroForm === null ? null : $this->resolveArray($parser, $acroForm->get('Fields'));

    if ($fields === null) {
      return [];
    }

    $records = [];
    $seen    = [];

    foreach ($fields->getItems() as $field) {
      $this->collect($parser, $field, '', '', null, $records, $seen);
    }

    usort(
      $records,
      fn (PdfSignatureRecord $left, PdfSignatureRecord $right): int => $left->byteRange[2] + $left->byteRange[3]
        <=> $right->byteRange[2]                                                           + $right->byteRange[3],
    );

    return $records;
  }

  /**
   * Returns the active DocMDP permission (1, 2 or 3), or null for an approval-only PDF.
   */
  public function docMdpPermission(PdfParser $parser): ?int
  {
    $catalogNumber = $parser->getXref()->getCatalogObjectNumber();
    $catalog       = $catalogNumber === null ? null : $parser->resolveObject($catalogNumber);

    if (!$catalog instanceof PdfDictionary) {
      return null;
    }

    $permissions = $this->resolveDictionary($parser, $catalog->get('Perms'));
    $signature   = $permissions === null ? null : $this->resolveDictionary($parser, $permissions->get('DocMDP'));
    $references  = $signature   === null ? null : $this->resolveArray($parser, $signature->get('Reference'));

    if ($references === null) {
      return null;
    }

    foreach ($references->getItems() as $reference) {
      $dictionary = $this->resolveDictionary($parser, $reference);

      if ($dictionary === null || $this->name($parser, $dictionary->get('TransformMethod')) !== 'DocMDP') {
        continue;
      }

      $parameters = $this->resolveDictionary($parser, $dictionary->get('TransformParams'));
      $permission = $parameters === null ? null : $this->integer($parser, $parameters->get('P'));

      return in_array($permission, [1, 2, 3], true) ? $permission : 2;
    }

    return null;
  }

  /**
   * @param list<PdfSignatureRecord> $records
   * @param array<string, true> $seen
   */
  private function collect(
    PdfParser $parser,
    PdfObject $object,
    string $parentName,
    string $inheritedType,
    ?PdfObject $inheritedValue,
    array &$records,
    array &$seen,
  ): void {
    $field = $parser->resolve($object);

    if (!$field instanceof PdfDictionary) {
      return;
    }

    $partialName = $this->text($parser, $field->get('T')) ?? '';
    $fieldName   = $parentName === '' ? $partialName : ($partialName === '' ? $parentName : $parentName . '.' . $partialName);
    $type        = $this->name($parser, $field->get('FT')) ?? $inheritedType;
    $value       = $field->get('V')                        ?? $inheritedValue;

    if ($type === 'Sig' && $value !== null) {
      $signature = $this->resolveDictionary($parser, $value);

      if ($signature !== null) {
        $record = $this->record($parser, $signature, $fieldName);

        if ($record !== null) {
          $identity = implode(':', $record->byteRange) . ':' . hash('sha256', $record->cms);

          if (!isset($seen[$identity])) {
            $seen[$identity] = true;
            $records[]       = $record;
          }
        }
      }
    }

    $kids = $this->resolveArray($parser, $field->get('Kids'));

    if ($kids === null) {
      return;
    }

    foreach ($kids->getItems() as $kid) {
      $this->collect($parser, $kid, $fieldName, $type, $value, $records, $seen);
    }
  }

  private function record(PdfParser $parser, PdfDictionary $signature, string $fieldName): ?PdfSignatureRecord
  {
    $range    = $this->resolveArray($parser, $signature->get('ByteRange'));
    $contents = $parser->resolve($signature->get('Contents') ?? new PdfNull());

    if ($range === null || !$contents instanceof PdfString || count($range) !== 4) {
      return null;
    }

    $values = [];

    foreach ($range->getItems() as $item) {
      $value = $this->integer($parser, $item);

      if ($value === null || $value < 0) {
        return null;
      }

      $values[] = $value;
    }

    /** @var array{int, int, int, int} $byteRange */
    $byteRange = $values;

    return new PdfSignatureRecord(
      fieldName: $fieldName,
      byteRange: $byteRange,
      cms: $this->derValue($contents->getValue()),
      signedAt: $this->text($parser, $signature->get('M')),
      signerName: $this->text($parser, $signature->get('Name')),
      reason: $this->text($parser, $signature->get('Reason')),
      location: $this->text($parser, $signature->get('Location')),
    );
  }

  private function derValue(string $value): string
  {
    if (strlen($value) < 2 || ord($value[0]) !== 0x30) {
      return rtrim($value, "\0");
    }

    $lengthOctet = ord($value[1]);

    if (($lengthOctet & 0x80) === 0) {
      return substr($value, 0, min(strlen($value), 2 + $lengthOctet));
    }

    $octets = $lengthOctet & 0x7f;

    if ($octets < 1 || $octets > 4 || strlen($value) < 2 + $octets) {
      return rtrim($value, "\0");
    }

    $length = 0;

    for ($index = 0; $index < $octets; $index++) {
      $length = ($length << 8) | ord($value[2 + $index]);
    }

    return substr($value, 0, min(strlen($value), 2 + $octets + $length));
  }

  private function resolveDictionary(PdfParser $parser, ?PdfObject $object): ?PdfDictionary
  {
    if ($object === null) {
      return null;
    }

    $resolved = $parser->resolve($object);

    return $resolved instanceof PdfDictionary ? $resolved : null;
  }

  private function resolveArray(PdfParser $parser, ?PdfObject $object): ?PdfArray
  {
    if ($object === null) {
      return null;
    }

    $resolved = $parser->resolve($object);

    return $resolved instanceof PdfArray ? $resolved : null;
  }

  private function integer(PdfParser $parser, ?PdfObject $object): ?int
  {
    if ($object === null) {
      return null;
    }

    $resolved = $parser->resolve($object);

    return match (true) {
      $resolved instanceof PdfInteger => $resolved->getValue(),
      $resolved instanceof PdfReal    => (int) $resolved->getValue(),
      default                         => null,
    };
  }

  private function name(PdfParser $parser, ?PdfObject $object): ?string
  {
    if ($object === null) {
      return null;
    }

    $resolved = $parser->resolve($object);

    return $resolved instanceof PdfName ? $resolved->getValue() : null;
  }

  private function text(PdfParser $parser, ?PdfObject $object): ?string
  {
    if ($object === null) {
      return null;
    }

    $resolved = $parser->resolve($object);

    if (!$resolved instanceof PdfString) {
      return null;
    }

    $value = $resolved->getValue();

    if (str_starts_with($value, "\xFE\xFF")) {
      return mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16BE');
    }

    return $value;
  }
}
