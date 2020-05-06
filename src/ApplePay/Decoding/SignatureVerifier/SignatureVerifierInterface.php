<?php

namespace Crestoff\ApplePay\Decoding\SignatureVerifier;

interface SignatureVerifierInterface
{
    public function verify(array $paymentData);
}
