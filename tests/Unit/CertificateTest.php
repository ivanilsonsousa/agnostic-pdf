<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgnosticPDF\Exceptions\CertificateException;
use AgnosticPDF\Signatures\Certificate;
use PHPUnit\Framework\TestCase;

final class CertificateTest extends TestCase
{
  public function test_it_generates_a_self_signed_document_signing_certificate(): void
  {
    $certificate = Certificate::create()
      ->commonName('Internal PDF Signer')
      ->organization('Agnostic PDF')
      ->organizationalUnit('Documents')
      ->country('BR')
      ->validFor(30)
      ->generate();

    $info = $certificate->info();

    $this->assertSame('Internal PDF Signer', $info->subject['commonName']);
    $this->assertSame('Agnostic PDF', $info->subject['organizationName']);
    $this->assertTrue($info->selfSigned);
    $this->assertGreaterThan($info->validFrom, $info->validTo);

    $parsed = openssl_x509_parse($certificate->certificatePem());
    $this->assertIsArray($parsed);
    $this->assertSame('CA:FALSE', $parsed['extensions']['basicConstraints'] ?? null);
    $this->assertStringContainsString('Digital Signature', $parsed['extensions']['keyUsage'] ?? '');
  }

  public function test_it_exports_and_loads_pkcs12(): void
  {
    $generated = Certificate::create()->commonName('PKCS12 Test')->generate();
    $pkcs12    = $generated->exportPkcs12('correct horse battery staple');
    $loaded    = Certificate::fromPkcs12($pkcs12, 'correct horse battery staple');

    $this->assertSame('PKCS12 Test', $loaded->info()->subject['commonName']);
    $this->assertTrue(openssl_x509_check_private_key(
      openssl_x509_read($loaded->certificatePem()),
      openssl_pkey_get_private($loaded->privateKeyPem()),
    ));
  }

  public function test_it_rejects_an_incorrect_pkcs12_password(): void
  {
    $generated = Certificate::create()->commonName('PKCS12 Test')->generate();
    $pkcs12    = $generated->exportPkcs12('right-password');

    $this->expectException(CertificateException::class);
    $this->expectExceptionMessage('file or password is invalid');

    Certificate::fromPkcs12($pkcs12, 'wrong-password');
  }

  public function test_it_rejects_a_private_key_from_another_certificate(): void
  {
    $first  = Certificate::create()->commonName('First')->generate();
    $second = Certificate::create()->commonName('Second')->generate();

    $this->expectException(CertificateException::class);
    $this->expectExceptionMessage('does not match');

    Certificate::fromPem($first->certificatePem(), $second->privateKeyPem());
  }
}
