interface OrderState {
    // Add these to your existing state
    paymentProvider: 'stripe' | 'payfast';
    basketId?: string;
    transactionId?: string;
}

// Add actions for PayFast
const updatePaymentProvider = (provider: 'stripe' | 'payfast') => {
    set({ paymentProvider: provider });
};