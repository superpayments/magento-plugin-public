<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Gateway\Validator;

class CompleteCheckoutSessionValidator extends ResponseValidator
{
    public const FAILED_PAYMENT_HTTP_CODE = 402;

    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $result = parent::validate($validationSubject);
        $response = $validationSubject['response'];
        $statusCode = $response['statusCode'];

        if (!$result->isValid() && $statusCode == self::FAILED_PAYMENT_HTTP_CODE) {
            $failedPaymentResult = [
                'isValid' => true,
                'failsDescription' => $result->getFailsDescription(),
                'errorCodes' => $result->getErrorCodes(),
            ];

            return $this->resultInterfaceFactory->create($failedPaymentResult);
        }

        return $result;
    }
}
