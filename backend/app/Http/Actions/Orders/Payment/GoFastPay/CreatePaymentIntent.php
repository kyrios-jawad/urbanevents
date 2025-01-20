<?php

namespace HiEvents\Http\Actions\Orders\Payment\GoFastPay;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\Order\Payment\GoFastPay\CreatePaymentIntentHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreatePaymentIntent extends BaseAction
{

    private CreatePaymentIntentHandler $createPaymentIntentHandler;

    public function __construct(CreatePaymentIntentHandler $createPaymentIntentHandler)
    {
        $this->createPaymentIntentHandler = $createPaymentIntentHandler;
    }

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        $createIntent = $this->createPaymentIntentHandler->handle($orderShortId);
        // try {
        // } catch (CreatePaymentIntentFailedException $e) {
        //     return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        // }

        return $this->jsonResponse([
            'client_secret' =>  $eventId,
            'account_id' => $orderShortId,
        ]);
    }
}
