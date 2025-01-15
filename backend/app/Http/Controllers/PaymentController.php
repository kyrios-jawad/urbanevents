<?php

namespace HiEvents\Http\Controllers;

use HiEvents\Services\PaymentService;
use HiEvents\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createSession(Request $request): JsonResponse
    {
        $order = $request->get('order');
        $session = $this->paymentService->createPaymentSession($order);
        
        // Store basket_id in order
        Order::where('id', $order->id)->update([
            'basket_id' => $session['session_data']['BASKET_ID']
        ]);

        return response()->json($session);
    }

    public function handleCallback(Request $request): JsonResponse
    {
        $result = $this->paymentService->verifyPayment($request->all());
        
        if ($result['verified']) {
            $order = Order::where('basket_id', $result['basket_id'])->first();
            
            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found'
                ], 404);
            }

            $order->update([
                'status' => 'completed',
                'transaction_id' => $result['transaction_id']
            ]);

            return response()->json([
                'status' => 'success',
                'redirect_url' => config('app.frontend_url') . "/payment/success/{$order->id}"
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'message' => $result['message'],
            'redirect_url' => config('app.frontend_url') . "/payment/failed/{$order->id}"
        ], 400);
    }
} 