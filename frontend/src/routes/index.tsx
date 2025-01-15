const routes = [
    // Add these to your existing routes
    {
        path: '/payment/success/:orderId',
        element: <PaymentStatus status="success" />
    },
    {
        path: '/payment/failed/:orderId',
        element: <PaymentStatus status="failed" />
    }
]; 