<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class GeneralResponseValidator extends AbstractValidator
{
    private const RESULT_STATUS_SUCCESS = 'S';
    private const RESULT_STATUS_FAIL = 'F';
    private const RESULT_STATUS_UNKNOWN = 'U';

    public function __construct(ResultInterfaceFactory $resultFactory)
    {
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject): ResultInterface
    {
        $response = $validationSubject['response'] ?? [];
        $result = $response['result'] ?? [];

        $resultStatus = $result['resultStatus'] ?? '';
        $resultCode = $result['resultCode'] ?? '';
        $resultMessage = $result['resultMessage'] ?? '';

        $isValid = $resultStatus === self::RESULT_STATUS_SUCCESS;

        $errorMessages = [];
        $errorCodes = [];

        if (!$isValid) {
            $errorMessages[] = sprintf(
                'Antom API error: [%s] %s (status: %s)',
                $resultCode,
                $resultMessage,
                $resultStatus
            );
            $errorCodes[] = $resultCode;
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
