<?php

namespace Crestoff\ApplePay\Decoding\OpenSSL;

use Crestoff\ApplePay\Decoding\SignatureVerifier\Exception\SignatureException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OpenSslService
{
    /**
     * @param string $caCertificatePath
     * @param string $intermediateCertificatePath
     * @param string $leafCertificatePath
     * @return bool
     * @throws \RuntimeException
     */
    public function validateCertificateChain($caCertificatePath, $intermediateCertificatePath, $leafCertificatePath) {
        $verifyCertificateCommand = 'openssl verify -CAfile ' . escapeshellarg($caCertificatePath) . ' -untrusted ' . escapeshellarg($intermediateCertificatePath) . ' ' . escapeshellarg($leafCertificatePath);

        try {
            $this->runCommand($verifyCertificateCommand);
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException("Can't validate certificate chain", 0, $e);
        }

        return true;
    }

    /**
     * @param $signedAttributes
     * @param $signature
     * @param $publicKey
     * @return bool
     * @throws SignatureException
     */
    public function verifySignature($signedAttributes, $signature, $publicKey) {
        $verifyResult = openssl_verify($signedAttributes, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verifyResult === 1) {
            return true;
        }

        if ($verifyResult === -1) {
            throw new SignatureException(openssl_error_string());
        }

        throw new SignatureException('Invalid signature');
    }

    /**
     * @param $certificatePath
     * @return string
     * @throws \RuntimeException
     */
    public function getCertificatesFromPkcs7($certificatePath) {
        $getCertificatesCommand = 'openssl pkcs7 -inform DER -in ' . escapeshellarg($certificatePath) . ' -print_certs';

        try {
            $commandOutput = $this->runCommand($getCertificatesCommand);
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException("Can't get certificates", 0, $e);
        }

        return rtrim($commandOutput);
    }

    /**
     * @param $certificate
     * @return mixed
     * @throws \RuntimeException
     */
    public function getCertificateExtensions($certificate) {
        $certificateResource = @openssl_x509_read($certificate);

        if(empty($certificateResource)) {
            throw new \RuntimeException("Can't load x509 certificate");
        }
        $certificateData = openssl_x509_parse($certificateResource, false);
        return $certificateData['extensions'];
    }

    /**
     * @param $privateKeyFilePath
     * @param $publicKeyFilePath
     * @return null
     * @throws \RuntimeException
     */
    public function deriveKey($privateKeyFilePath, $publicKeyFilePath) {
        $command = 'openssl pkeyutl -derive -inkey '.escapeshellarg($privateKeyFilePath).' -peerkey '.escapeshellarg($publicKeyFilePath);

        try {
            $execOutput = $this->runCommand($command);
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException("Can't derive secret", 0, $e);
        }

        if (empty($execOutput)) {
            throw new \RuntimeException("Unexpected empty result");
        }

        return $execOutput;
    }

    /**
     * @param string $command
     * @return string
     * @throws ProcessFailedException
     */
    private function runCommand($command)
    {
        $process = new Process($command);
        $process->mustRun();

        return $process->getOutput();
    }
}
