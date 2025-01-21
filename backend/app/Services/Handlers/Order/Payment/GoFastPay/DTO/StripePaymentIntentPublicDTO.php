<?php

namespace HiEvents\Services\Handlers\Order\Payment\Stripe\GoFastPay;

use HiEvents\DataTransferObjects\BaseDTO;

class GoFastPayPaymentIntentPublicDTO extends BaseDTO
{
    public function __construct(
        public string $MERCHANT_ID,
        public string $ACCESS_TOKEN,
        public string $NAME,
        public string $GENERATED_DATE_TIME
    ) {}
}
