<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use AgnosticPDF\Contracts\CertificateIssuerInterface;
use AgnosticPDF\Exceptions\CertificateException;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

final readonly class Certificate
{
  /**
   * @param list<string> $chainPem
   */
  private function __construct(
    private string $certificatePem,
    private string $privateKeyPem,
    private array $chainPem = [],
  ) {
  }

  public static function create(?CertificateIssuerInterface $issuer = null): CertificateBuilder
  {
    return $issuer === null ? new CertificateBuilder() : new CertificateBuilder($issuer);
  }

  /**
   * Loads a certificate and matching private key from PEM strings or paths.
   *
   * @param list<string> $chainPem
   */
  public static function fromPem(
    string $certificate,
    string $privateKey,
    string $password = '',
    array $chainPem = [],
  ): self {
    $certificatePem  = self::readPem($certificate, 'certificate');
    $privateKeyValue = self::readPem($privateKey, 'private key');
    $x509            = openssl_x509_read($certificatePem);
    $key             = openssl_pkey_get_private($privateKeyValue, $password);

    if (!$x509 instanceof OpenSSLCertificate) {
      throw new CertificateException('The PEM certificate is invalid.');
    }

    if (!$key instanceof OpenSSLAsymmetricKey) {
      throw new CertificateException('The PEM private key or its password is invalid.');
    }

    if (!openssl_x509_check_private_key($x509, $key)) {
      throw new CertificateException('The private key does not match the certificate.');
    }

    if (!openssl_pkey_export($key, $normalizedPrivateKey)) {
      throw new CertificateException('Unable to normalize the private key.');
    }

    $normalizedChain = [];

    foreach ($chainPem as $chainCertificate) {
      $pem = self::readPem($chainCertificate, 'chain certificate');

      if (!openssl_x509_read($pem) instanceof OpenSSLCertificate) {
        throw new CertificateException('A certificate in the PEM chain is invalid.');
      }

      $normalizedChain[] = $pem;
    }

    return new self($certificatePem, $normalizedPrivateKey, $normalizedChain);
  }

  public static function fromPkcs12(string $pkcs12, string $password): self
  {
    if (!openssl_pkcs12_read($pkcs12, $store, $password)) {
      throw new CertificateException('Unable to load PKCS#12: the file or password is invalid.');
    }

    $certificate = $store['cert'] ?? null;
    $privateKey  = $store['pkey'] ?? null;

    if (!is_string($certificate) || !is_string($privateKey)) {
      throw new CertificateException('PKCS#12 does not contain a certificate and private key.');
    }

    $chain = array_values(array_filter(
      (array) ($store['extracerts'] ?? []),
      is_string(...),
    ));

    return self::fromPem($certificate, $privateKey, chainPem: $chain);
  }

  public static function fromPkcs12File(string $path, string $password): self
  {
    $contents = @file_get_contents($path);

    if ($contents === false) {
      throw new CertificateException("Unable to read PKCS#12 file: {$path}");
    }

    return self::fromPkcs12($contents, $password);
  }

  public function exportPkcs12(string $password): string
  {
    $x509 = openssl_x509_read($this->certificatePem);
    $key  = openssl_pkey_get_private($this->privateKeyPem);

    if (!$x509 instanceof OpenSSLCertificate || !$key instanceof OpenSSLAsymmetricKey) {
      throw new CertificateException('Certificate material is no longer readable by OpenSSL.');
    }

    $arguments = $this->chainPem === [] ? [] : ['extracerts' => $this->chainPem];

    if (!openssl_pkcs12_export($x509, $output, $key, $password, $arguments)) {
      throw new CertificateException('Unable to export PKCS#12.');
    }

    return $output;
  }

  public function certificatePem(): string
  {
    return $this->certificatePem;
  }

  public function privateKeyPem(): string
  {
    return $this->privateKeyPem;
  }

  /** @return list<string> */
  public function chainPem(): array
  {
    return $this->chainPem;
  }

  public function info(): CertificateInfo
  {
    return CertificateInfo::fromPem($this->certificatePem);
  }

  private static function readPem(string $value, string $label): string
  {
    if (str_contains($value, '-----BEGIN')) {
      return $value;
    }

    if (is_file($value)) {
      $contents = @file_get_contents($value);

      if ($contents !== false) {
        return $contents;
      }
    }

    throw new CertificateException("Unable to read PEM {$label}.");
  }
}
